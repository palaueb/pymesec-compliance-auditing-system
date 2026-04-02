<?php

namespace PymeSec\Plugins\ThirdPartyRisk;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Collaboration\Contracts\CollaborationEngineInterface;
use PymeSec\Core\Collaboration\Contracts\CollaborationStoreInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Core\Questionnaires\Contracts\QuestionnaireEngineInterface;
use PymeSec\Core\Questionnaires\Contracts\QuestionnaireStoreInterface;
use PymeSec\Core\Security\ContextualReferenceValidator;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\UI\ScreenDefinition;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\UI\ToolbarAction;
use PymeSec\Core\Workflows\Contracts\WorkflowRegistryInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowDefinition;
use PymeSec\Core\Workflows\WorkflowTransitionDefinition;
use PymeSec\Core\Workflows\WorkflowTransitionRecord;
use PymeSec\Plugins\FindingsRemediation\FindingsRemediationRepository;

class ThirdPartyRiskPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(ThirdPartyRiskRepository::class, fn ($app) => new ThirdPartyRiskRepository(
            $app->make(ContextualReferenceValidator::class),
            $app->make(AuditTrailInterface::class),
            $app->make(QuestionnaireStoreInterface::class),
            $app->make(CollaborationStoreInterface::class),
        ));

        $context->app()->make(WorkflowRegistryInterface::class)->register(new WorkflowDefinition(
            key: 'plugin.third-party-risk.review-lifecycle',
            owner: 'third-party-risk',
            label: 'Vendor review lifecycle',
            initialState: 'prospective',
            states: ['prospective', 'in-review', 'approved', 'approved-with-conditions', 'rejected'],
            transitions: [
                new WorkflowTransitionDefinition(
                    key: 'start-review',
                    fromStates: ['prospective', 'rejected', 'approved', 'approved-with-conditions'],
                    toState: 'in-review',
                    permission: 'plugin.third-party-risk.vendors.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'approve',
                    fromStates: ['in-review'],
                    toState: 'approved',
                    permission: 'plugin.third-party-risk.vendors.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'approve-with-conditions',
                    fromStates: ['in-review'],
                    toState: 'approved-with-conditions',
                    permission: 'plugin.third-party-risk.vendors.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'reject',
                    fromStates: ['in-review'],
                    toState: 'rejected',
                    permission: 'plugin.third-party-risk.vendors.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'reopen',
                    fromStates: ['approved', 'approved-with-conditions', 'rejected'],
                    toState: 'prospective',
                    permission: 'plugin.third-party-risk.vendors.manage',
                ),
            ],
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.third-party-risk.root',
            owner: 'third-party-risk',
            titleKey: 'plugin.third-party-risk.screen.register.title',
            subtitleKey: 'plugin.third-party-risk.screen.register.subtitle',
            viewPath: $context->path('resources/views/register.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->registerData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->baseQuery($screenContext, false);

                if (is_string($screenContext->query['vendor_id'] ?? null) && ($screenContext->query['vendor_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to vendors',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.third-party-risk.root']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'Add vendor',
                        url: '#vendor-editor',
                        variant: 'primary',
                    ),
                ];
            },
        ));
    }

    public function boot(PluginContext $context): void
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    private function registerData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(ThirdPartyRiskRepository::class);
        $findings = $context->app()->make(FindingsRemediationRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $questionnaires = $context->app()->make(QuestionnaireEngineInterface::class);
        $questionnaireStore = $context->app()->make(QuestionnaireStoreInterface::class);
        $collaboration = $context->app()->make(CollaborationEngineInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManage = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.third-party-risk.vendors.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
        ))->allowed();
        $canManageEvidence = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.evidence-management.evidence.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
        ))->allowed();
        $baseQuery = $this->baseQuery($screenContext, false);
        $assetOptions = $this->linkedOptions('assets', 'id', 'name', $organizationId, $screenContext->scopeId);
        $assetLabels = [];
        $controlOptions = $this->linkedOptions('controls', 'id', 'name', $organizationId, $screenContext->scopeId);
        $controlLabels = [];
        $riskOptions = $this->linkedOptions('risks', 'id', 'title', $organizationId, $screenContext->scopeId);
        $riskLabels = [];
        $findingOptions = $this->linkedOptions('findings', 'id', 'title', $organizationId, $screenContext->scopeId);
        $findingLabels = [];
        $reviewProfileOptions = $repository->allReviewProfiles($organizationId, $screenContext->scopeId);
        $questionnaireTemplateOptions = $repository->allQuestionnaireTemplates($organizationId, $screenContext->scopeId);
        $actorOptions = $this->actorOptions($actors, $organizationId, $screenContext->scopeId);
        $actorLabels = [];
        $reviewProfilesById = [];
        $questionnaireTemplatesById = [];
        $today = Carbon::today();
        $principalActorIds = $screenContext->principal !== null
            ? array_map(static fn ($actor): string => $actor->id, $actors->actorsForPrincipal($screenContext->principal->id, $organizationId))
            : [];

        foreach ($assetOptions as $option) {
            $assetLabels[$option['id']] = $option['label'];
        }

        foreach ($controlOptions as $option) {
            $controlLabels[$option['id']] = $option['label'];
        }

        foreach ($riskOptions as $option) {
            $riskLabels[$option['id']] = $option['label'];
        }

        foreach ($findingOptions as $option) {
            $findingLabels[$option['id']] = $option['label'];
        }

        foreach ($reviewProfileOptions as $profile) {
            $reviewProfilesById[$profile['id']] = $profile;
        }

        foreach ($questionnaireTemplateOptions as $template) {
            $questionnaireTemplatesById[$template['id']] = $template;
        }

        foreach ($actorOptions as $actor) {
            $actorLabels[$actor['id']] = $actor['label'];
        }

        $allVendors = [];

        foreach ($repository->all($organizationId, $screenContext->scopeId) as $vendor) {
            $reviews = $repository->reviewsForVendor($vendor['id']);
            $currentReview = $reviews[0] ?? null;

            if ($currentReview === null) {
                continue;
            }

            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.third-party-risk.review-lifecycle',
                subjectType: 'vendor-review',
                subjectId: $currentReview['id'],
                organizationId: $organizationId,
                scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
            );

            $questionnaireItems = $repository->questionnaireItemsForReview($currentReview['id']);
            $questionnaireItems = array_map(function (array $item) use ($vendor, $currentReview, $questionnaires, $questionnaireStore, $artifacts): array {
                $itemArtifacts = array_map(function ($artifact): array {
                    $record = $artifact->toArray();

                    return [
                        ...$record,
                        'promote_route' => route('plugin.evidence-management.promote', ['artifactId' => $record['id']]),
                    ];
                }, $artifacts->forSubject(
                    'questionnaire-subject-item',
                    $item['id'],
                    $vendor['organization_id'],
                    $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
                    5,
                ));

                return [
                    ...$item,
                    'response_type_label' => $questionnaires->responseTypeLabel($item['response_type']),
                    'response_status_label' => $questionnaires->responseStatusLabel($item['response_status']),
                    'attachment_mode_label' => $questionnaires->attachmentModeLabel($item['attachment_mode']),
                    'attachment_upload_profile_label' => $this->attachmentUploadProfileLabel($item['attachment_upload_profile']),
                    'answer_library_entries' => $questionnaireStore->answerLibraryEntries(
                        organizationId: $vendor['organization_id'],
                        scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
                        ownerComponent: 'third-party-risk',
                        subjectType: 'vendor-review',
                        responseType: $item['response_type'],
                        prompt: $item['prompt'],
                        limit: 3,
                    ),
                    'update_route' => route('plugin.third-party-risk.questionnaire-items.update', [
                        'vendorId' => $vendor['id'],
                        'reviewId' => $currentReview['id'],
                        'itemId' => $item['id'],
                    ]),
                    'review_route' => route('plugin.third-party-risk.questionnaire-items.review', [
                        'vendorId' => $vendor['id'],
                        'reviewId' => $currentReview['id'],
                        'itemId' => $item['id'],
                    ]),
                    'artifact_upload_route' => route('plugin.third-party-risk.questionnaire-items.artifacts.store', [
                        'vendorId' => $vendor['id'],
                        'reviewId' => $currentReview['id'],
                        'itemId' => $item['id'],
                    ]),
                    'external_artifact_upload_route' => route('plugin.third-party-risk.external.questionnaire-items.artifacts.store', [
                        'token' => '__TOKEN__',
                        'itemId' => $item['id'],
                    ]),
                    'supports_attachments' => $item['attachment_mode'] !== 'none',
                    'artifacts' => $itemArtifacts,
                ];
            }, $questionnaireItems);
            $reviewArtifacts = array_map(
                static fn ($artifact): array => $artifact->toArray(),
                $artifacts->forSubject('vendor-review', $currentReview['id'], $organizationId, $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null, 10),
            );
            $workflowHistory = $workflow->history('plugin.third-party-risk.review-lifecycle', 'vendor-review', $currentReview['id']);
            $externalLinks = $repository->externalLinksForReview($currentReview['id']);
            $collaborationComments = array_map(function (array $comment) use ($actorLabels, $principalActorIds): array {
                $mentionedActorIds = $this->csvList($comment['mentioned_actor_ids']);

                return [
                    ...$comment,
                    'mentioned_actor_ids_list' => $mentionedActorIds,
                    'mentioned_actor_labels' => array_values(array_map(
                        static fn (string $actorId) => $actorLabels[$actorId] ?? $actorId,
                        $mentionedActorIds,
                    )),
                    'has_mention_cue' => count(array_intersect($mentionedActorIds, $principalActorIds)) > 0,
                ];
            }, $repository->commentsForReview($currentReview['id']));
            $collaborationDrafts = array_map(function (array $draft) use ($vendor, $currentReview, $collaboration, $actorLabels, $principalActorIds): array {
                $mentionedActorIds = $this->csvList($draft['mentioned_actor_ids']);

                return [
                    ...$draft,
                    'draft_type_label' => $collaboration->draftTypeLabel($draft['draft_type']),
                    'priority_label' => $collaboration->requestPriorityLabel($draft['priority']),
                    'handoff_state_label' => $collaboration->handoffStateLabel($draft['handoff_state']),
                    'mentioned_actor_ids_list' => $mentionedActorIds,
                    'mentioned_actor_labels' => array_values(array_map(
                        static fn (string $actorId) => $actorLabels[$actorId] ?? $actorId,
                        $mentionedActorIds,
                    )),
                    'assigned_actor_label' => $actorLabels[$draft['assigned_actor_id']] ?? ($draft['assigned_actor_id'] !== '' ? $draft['assigned_actor_id'] : ''),
                    'has_assignment_cue' => $draft['assigned_actor_id'] !== '' && in_array($draft['assigned_actor_id'], $principalActorIds, true),
                    'has_mention_cue' => count(array_intersect($mentionedActorIds, $principalActorIds)) > 0,
                    'update_route' => route('plugin.third-party-risk.collaboration.drafts.update', [
                        'vendorId' => $vendor['id'],
                        'reviewId' => $currentReview['id'],
                        'draftId' => $draft['id'],
                    ]),
                    'promote_comment_route' => route('plugin.third-party-risk.collaboration.drafts.promote-comment', [
                        'vendorId' => $vendor['id'],
                        'reviewId' => $currentReview['id'],
                        'draftId' => $draft['id'],
                    ]),
                    'promote_request_route' => route('plugin.third-party-risk.collaboration.drafts.promote-request', [
                        'vendorId' => $vendor['id'],
                        'reviewId' => $currentReview['id'],
                        'draftId' => $draft['id'],
                    ]),
                ];
            }, $repository->collaborationDraftsForReview($currentReview['id']));
            $collaborationRequests = array_map(function (array $request) use ($vendor, $currentReview, $collaboration, $actorLabels, $principalActorIds): array {
                $mentionedActorIds = $this->csvList($request['mentioned_actor_ids']);

                return [
                    ...$request,
                    'status_label' => $collaboration->requestStatusLabel($request['status']),
                    'priority_label' => $collaboration->requestPriorityLabel($request['priority']),
                    'handoff_state_label' => $collaboration->handoffStateLabel($request['handoff_state']),
                    'mentioned_actor_ids_list' => $mentionedActorIds,
                    'mentioned_actor_labels' => array_values(array_map(
                        static fn (string $actorId) => $actorLabels[$actorId] ?? $actorId,
                        $mentionedActorIds,
                    )),
                    'assigned_actor_label' => $actorLabels[$request['assigned_actor_id']] ?? ($request['assigned_actor_id'] !== '' ? $request['assigned_actor_id'] : ''),
                    'has_assignment_cue' => $request['assigned_actor_id'] !== '' && in_array($request['assigned_actor_id'], $principalActorIds, true),
                    'has_mention_cue' => count(array_intersect($mentionedActorIds, $principalActorIds)) > 0,
                    'update_route' => route('plugin.third-party-risk.collaboration.requests.update', [
                        'vendorId' => $vendor['id'],
                        'reviewId' => $currentReview['id'],
                        'requestId' => $request['id'],
                    ]),
                ];
            }, $repository->collaborationRequestsForReview($currentReview['id']));
            $brokeredRequests = array_map(function (array $request) use ($vendor, $currentReview): array {
                return [
                    ...$request,
                    'collection_channel_label' => $this->brokeredCollectionChannelLabel($request['collection_channel']),
                    'collection_status_label' => $this->brokeredCollectionStatusLabel($request['collection_status']),
                    'update_route' => route('plugin.third-party-risk.brokered-requests.update', [
                        'vendorId' => $vendor['id'],
                        'reviewId' => $currentReview['id'],
                        'requestId' => $request['id'],
                    ]),
                ];
            }, $repository->brokeredRequestsForReview($currentReview['id']));
            $openQuestionnaireCount = count(array_filter(
                $questionnaireItems,
                static fn (array $item): bool => in_array($item['response_status'], ['draft', 'sent', 'submitted', 'under-review', 'needs-follow-up'], true),
            ));
            $linkedFinding = $this->linkedFindingSummary(
                findings: $findings,
                review: $currentReview,
                baseQuery: $baseQuery,
                today: $today,
            );
            $dueDate = $currentReview['next_review_due_on'] !== '' ? Carbon::parse($currentReview['next_review_due_on']) : null;
            $isOverdue = $dueDate !== null && $dueDate->lt($today);
            $isDueSoon = $dueDate !== null && ! $isOverdue && $dueDate->lte($today->copy()->addDays(14));
            $isDecisionPending = in_array($instance->currentState, ['prospective', 'in-review'], true);

            $allVendors[] = [
                ...$vendor,
                'current_review' => [
                    ...$currentReview,
                    'review_profile' => $reviewProfilesById[$currentReview['review_profile_id']] ?? null,
                    'questionnaire_template' => $questionnaireTemplatesById[$currentReview['questionnaire_template_id']] ?? null,
                    'state' => $instance->currentState,
                    'state_label' => $this->stateLabel($instance->currentState),
                    'is_decision_pending' => $isDecisionPending,
                    'is_due_soon' => $isDueSoon,
                    'is_overdue' => $isOverdue,
                    'open_questionnaire_count' => $openQuestionnaireCount,
                    'open_action_count' => $linkedFinding['open_action_count'] ?? 0,
                    'transitions' => $canManage ? $this->transitionsForState($instance->currentState) : [],
                    'history' => $workflowHistory,
                    'artifacts' => $reviewArtifacts,
                    'questionnaire_items' => $questionnaireItems,
                    'questionnaire_sections' => $questionnaires->groupItemsBySection($questionnaireItems),
                    'linked_finding' => $linkedFinding,
                    'external_links' => array_map(function (array $link) use ($vendor, $currentReview): array {
                        return [
                            ...$link,
                            'portal_url' => route('plugin.third-party-risk.external.portal.show', ['token' => '__TOKEN__']),
                            'revoke_route' => route('plugin.third-party-risk.external.links.revoke', [
                                'vendorId' => $vendor['id'],
                                'reviewId' => $currentReview['id'],
                                'linkId' => $link['id'],
                            ]),
                        ];
                    }, $externalLinks),
                    'brokered_requests' => $brokeredRequests,
                    'collaboration_drafts' => $collaborationDrafts,
                    'collaboration_comments' => $collaborationComments,
                    'collaboration_requests' => $collaborationRequests,
                    'collaboration_draft_assignment_cue_count' => count(array_filter($collaborationDrafts, static fn (array $draft): bool => (bool) ($draft['has_assignment_cue'] ?? false))),
                    'collaboration_draft_mention_cue_count' => count(array_filter($collaborationDrafts, static fn (array $draft): bool => (bool) ($draft['has_mention_cue'] ?? false))),
                    'collaboration_assignment_cue_count' => count(array_filter($collaborationRequests, static fn (array $request): bool => (bool) ($request['has_assignment_cue'] ?? false))),
                    'collaboration_mention_cue_count' => count(array_filter($collaborationComments, static fn (array $comment): bool => (bool) ($comment['has_mention_cue'] ?? false)))
                        + count(array_filter($collaborationRequests, static fn (array $request): bool => (bool) ($request['has_mention_cue'] ?? false))),
                    'activity_timeline' => $this->reviewActivityTimeline(
                        audit: $context->app()->make(AuditTrailInterface::class),
                        review: $currentReview,
                        workflowHistory: $workflowHistory,
                        artifacts: $reviewArtifacts,
                        questionnaireItems: $questionnaireItems,
                        externalLinks: $externalLinks,
                    ),
                    'owner_assignments' => $this->ownerAssignments($actors, $currentReview['id'], $organizationId, $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null),
                    'transition_route' => route('plugin.third-party-risk.transition', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id'], 'transitionKey' => '__TRANSITION__']),
                    'artifact_upload_route' => route('plugin.third-party-risk.artifacts.store', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id']]),
                    'questionnaire_store_route' => route('plugin.third-party-risk.questionnaire-items.store', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id']]),
                    'questionnaire_apply_template_route' => route('plugin.third-party-risk.questionnaire-items.apply-template', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id']]),
                    'external_link_issue_route' => route('plugin.third-party-risk.external.links.issue', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id']]),
                    'brokered_request_issue_route' => route('plugin.third-party-risk.brokered-requests.issue', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id']]),
                    'collaboration_draft_store_route' => route('plugin.third-party-risk.collaboration.drafts.store', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id']]),
                    'collaboration_comment_store_route' => route('plugin.third-party-risk.collaboration.comments.store', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id']]),
                    'collaboration_request_store_route' => route('plugin.third-party-risk.collaboration.requests.store', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id']]),
                    'owner_remove_route' => route('plugin.third-party-risk.owners.destroy', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id'], 'assignmentId' => '__ASSIGNMENT__']),
                    'linked_asset_label' => $assetLabels[$currentReview['linked_asset_id']] ?? null,
                    'linked_control_label' => $controlLabels[$currentReview['linked_control_id']] ?? null,
                    'linked_risk_label' => $riskLabels[$currentReview['linked_risk_id']] ?? null,
                    'linked_finding_label' => $findingLabels[$currentReview['linked_finding_id']] ?? null,
                ],
                'update_route' => route('plugin.third-party-risk.update', ['vendorId' => $vendor['id']]),
                'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.third-party-risk.root', 'vendor_id' => $vendor['id']]),
            ];
        }

        $registerFilter = (string) ($screenContext->query['register_filter'] ?? 'all');
        $selectedVendorId = is_string($screenContext->query['vendor_id'] ?? null) && $screenContext->query['vendor_id'] !== ''
            ? (string) $screenContext->query['vendor_id']
            : null;
        $vendors = $selectedVendorId === null
            ? $this->applyRegisterFilter($allVendors, $registerFilter)
            : $allVendors;

        $selectedVendor = null;

        if (is_string($selectedVendorId)) {
            foreach ($allVendors as $vendor) {
                if ($vendor['id'] === $selectedVendorId) {
                    $selectedVendor = $vendor;
                    break;
                }
            }
        }

        $scopeContext = $tenancy->resolveContext(
            principalId: $screenContext->principal?->id,
            requestedOrganizationId: $organizationId,
            requestedScopeId: $screenContext->scopeId,
            requestedMembershipIds: array_map(static fn ($membership): string => $membership->id, $screenContext->memberships),
        );

        return [
            'vendors' => $vendors,
            'all_vendors' => $allVendors,
            'selected_vendor' => $selectedVendor,
            'can_manage_vendors' => $canManage,
            'can_manage_evidence' => $canManageEvidence,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $baseQuery,
            'register_filter' => in_array($registerFilter, ['all', 'decision-pending', 'due-soon', 'overdue'], true) ? $registerFilter : 'all',
            'register_filters' => [
                [
                    'id' => 'all',
                    'label' => 'All vendors',
                    'count' => count($allVendors),
                    'url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.third-party-risk.root', 'register_filter' => 'all']),
                ],
                [
                    'id' => 'decision-pending',
                    'label' => 'Decision pending',
                    'count' => count(array_filter($allVendors, static fn (array $vendor): bool => (bool) ($vendor['current_review']['is_decision_pending'] ?? false))),
                    'url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.third-party-risk.root', 'register_filter' => 'decision-pending']),
                ],
                [
                    'id' => 'due-soon',
                    'label' => 'Due soon',
                    'count' => count(array_filter($allVendors, static fn (array $vendor): bool => (bool) ($vendor['current_review']['is_due_soon'] ?? false))),
                    'url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.third-party-risk.root', 'register_filter' => 'due-soon']),
                ],
                [
                    'id' => 'overdue',
                    'label' => 'Overdue',
                    'count' => count(array_filter($allVendors, static fn (array $vendor): bool => (bool) ($vendor['current_review']['is_overdue'] ?? false))),
                    'url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.third-party-risk.root', 'register_filter' => 'overdue']),
                ],
            ],
            'create_route' => route('plugin.third-party-risk.store'),
            'owner_actor_options' => $actorOptions,
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'asset_options' => $assetOptions,
            'control_options' => $controlOptions,
            'risk_options' => $riskOptions,
            'finding_options' => $findingOptions,
            'review_profile_options' => array_map(function (array $profile): array {
                return [
                    'id' => $profile['id'],
                    'label' => sprintf(
                        '%s [%s tier%s]',
                        $profile['name'],
                        ucfirst($profile['tier']),
                        $profile['review_interval_days'] !== '' ? ' · '.$profile['review_interval_days'].'d' : ''
                    ),
                ];
            }, $reviewProfileOptions),
            'questionnaire_template_options' => array_map(function (array $template) use ($reviewProfilesById): array {
                $profileName = $reviewProfilesById[$template['profile_id']]['name'] ?? $template['profile_id'];

                return [
                    'id' => $template['id'],
                    'label' => sprintf('%s [%s]', $template['name'], $profileName),
                ];
            }, $questionnaireTemplateOptions),
            'questionnaire_response_type_options' => $questionnaires->responseTypes(),
            'questionnaire_response_status_options' => $questionnaires->responseStatuses(),
            'questionnaire_attachment_mode_options' => $questionnaires->attachmentModes(),
            'questionnaire_attachment_upload_profile_options' => [
                'documents_only' => 'Documents only',
                'documents_and_spreadsheets' => 'Documents and spreadsheets',
                'images_only' => 'Images only',
                'review_artifacts' => 'Mixed review artifacts',
            ],
            'collaboration_request_status_options' => $collaboration->requestStatuses(),
            'collaboration_request_priority_options' => $collaboration->requestPriorities(),
            'collaboration_request_handoff_state_options' => $collaboration->handoffStates(),
            'current_principal_actor_ids' => $principalActorIds,
            'vendors_list_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.third-party-risk.root']),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $vendors
     * @return array<int, array<string, mixed>>
     */
    private function applyRegisterFilter(array $vendors, string $filter): array
    {
        return match ($filter) {
            'decision-pending' => array_values(array_filter($vendors, static fn (array $vendor): bool => (bool) ($vendor['current_review']['is_decision_pending'] ?? false))),
            'due-soon' => array_values(array_filter($vendors, static fn (array $vendor): bool => (bool) ($vendor['current_review']['is_due_soon'] ?? false))),
            'overdue' => array_values(array_filter($vendors, static fn (array $vendor): bool => (bool) ($vendor['current_review']['is_overdue'] ?? false))),
            default => $vendors,
        };
    }

    /**
     * @return array<int, string>
     */
    private function transitionsForState(string $state): array
    {
        return match ($state) {
            'prospective' => ['start-review'],
            'in-review' => ['approve', 'approve-with-conditions', 'reject'],
            'approved', 'approved-with-conditions', 'rejected' => ['reopen'],
            default => [],
        };
    }

    private function stateLabel(string $state): string
    {
        return ucwords(str_replace('-', ' ', $state));
    }

    /**
     * @param  array<string, string>  $review
     * @param  array<string, mixed>  $baseQuery
     * @return array<string, mixed>|null
     */
    private function linkedFindingSummary(
        FindingsRemediationRepository $findings,
        array $review,
        array $baseQuery,
        Carbon $today,
    ): ?array {
        if (($review['linked_finding_id'] ?? '') === '') {
            return null;
        }

        $finding = $findings->findFinding($review['linked_finding_id']);

        if ($finding === null) {
            return null;
        }

        $actions = array_map(function (array $action) use ($today): array {
            return [
                ...$action,
                'status_label' => ucwords(str_replace('-', ' ', $action['status'])),
                'is_overdue' => $action['due_on'] !== '' && Carbon::parse($action['due_on'])->lt($today),
            ];
        }, $findings->actionsForFinding($finding['id']));

        $openActionCount = count(array_filter($actions, static fn (array $action): bool => $action['status'] !== 'done'));

        return [
            ...$finding,
            'severity_label' => ucfirst($finding['severity']),
            'actions' => $actions,
            'open_action_count' => $openActionCount,
            'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.findings-remediation.root', 'finding_id' => $finding['id']]),
        ];
    }

    /**
     * @param  array<string, string>  $review
     * @param  array<int, WorkflowTransitionRecord>  $workflowHistory
     * @param  array<int, array<string, mixed>>  $artifacts
     * @param  array<int, array<string, string>>  $questionnaireItems
     * @param  array<int, array<string, string>>  $externalLinks
     * @return array<int, array<string, string>>
     */
    private function reviewActivityTimeline(
        AuditTrailInterface $audit,
        array $review,
        array $workflowHistory,
        array $artifacts,
        array $questionnaireItems,
        array $externalLinks,
    ): array {
        $entries = [];

        if (($review['created_at'] ?? '') !== '') {
            $entries[] = [
                'at' => $review['created_at'],
                'title' => 'Review created',
                'detail' => $review['title'],
                'kind' => 'review',
            ];
        }

        foreach ($workflowHistory as $record) {
            $entries[] = [
                'at' => $record->createdAt,
                'title' => 'Workflow transition',
                'detail' => sprintf('%s: %s -> %s', ucwords(str_replace('-', ' ', $record->transitionKey)), $this->stateLabel($record->fromState), $this->stateLabel($record->toState)),
                'kind' => 'workflow',
            ];
        }

        foreach ($artifacts as $artifact) {
            $entries[] = [
                'at' => (string) ($artifact['created_at'] ?? ''),
                'title' => 'Evidence attached',
                'detail' => sprintf('%s (%s)', (string) $artifact['label'], (string) $artifact['original_filename']),
                'kind' => 'artifact',
            ];
        }

        foreach ($questionnaireItems as $item) {
            if (($item['updated_at'] ?? '') === '') {
                continue;
            }

            $entries[] = [
                'at' => $item['updated_at'],
                'title' => 'Questionnaire updated',
                'detail' => sprintf('%s. %s [%s]', $item['position'], $item['prompt'], ucwords(str_replace('-', ' ', $item['response_status']))),
                'kind' => 'questionnaire',
            ];
        }

        $linkIds = array_values(array_filter(array_map(static fn (array $link): string => $link['id'], $externalLinks)));

        foreach ($audit->latest(200, [
            'organization_id' => $review['organization_id'],
            'scope_id' => $review['scope_id'] !== '' ? $review['scope_id'] : null,
            'origin_component' => 'third-party-risk',
        ]) as $record) {
            $recordReviewId = (string) ($record->summary['review_id'] ?? '');

            if ($recordReviewId !== $review['id'] && ! in_array((string) ($record->summary['external_link_id'] ?? ''), $linkIds, true)) {
                continue;
            }

            if ($record->targetType === 'vendor_review_external_link' && ! in_array((string) $record->targetId, $linkIds, true)) {
                continue;
            }

            if ($record->targetType === 'vendor-review' && (string) $record->targetId !== $review['id']) {
                continue;
            }

            if ($record->targetType === 'artifact' && $recordReviewId !== $review['id']) {
                continue;
            }

            $label = match ($record->eventType) {
                'plugin.third-party-risk.external-link.issued' => 'External link issued',
                'plugin.third-party-risk.external-link.revoked' => 'External link revoked',
                'plugin.third-party-risk.external-link.accessed' => 'External portal accessed',
                'plugin.third-party-risk.external-link.delivered' => 'Invitation email sent',
                'plugin.third-party-risk.external-link.delivery-failed' => 'Invitation email failed',
                'plugin.third-party-risk.external-link.delivery-skipped' => 'Invitation email skipped',
                'plugin.third-party-risk.external-link.questionnaire-submitted' => 'External questionnaire submitted',
                'plugin.third-party-risk.external-link.artifact-submitted' => 'External evidence uploaded',
                'plugin.third-party-risk.questionnaire-item.artifact-uploaded' => 'Questionnaire attachment uploaded',
                'plugin.third-party-risk.external-link.questionnaire-artifact-submitted' => 'External questionnaire attachment uploaded',
                'plugin.third-party-risk.brokered-request.issued' => 'Brokered request issued',
                'plugin.third-party-risk.brokered-request.updated' => 'Brokered request updated',
                'plugin.third-party-risk.brokered-request.started' => 'Brokered request started',
                'plugin.third-party-risk.brokered-request.submitted' => 'Brokered request submitted',
                'plugin.third-party-risk.brokered-request.completed' => 'Brokered request completed',
                'plugin.third-party-risk.brokered-request.cancelled' => 'Brokered request cancelled',
                'plugin.third-party-risk.collaboration-comment.added' => 'Comment added',
                'plugin.third-party-risk.collaboration-draft.saved' => 'Shared draft saved',
                'plugin.third-party-risk.collaboration-draft.updated' => 'Shared draft updated',
                'plugin.third-party-risk.collaboration-draft.promoted-comment' => 'Draft promoted to comment',
                'plugin.third-party-risk.collaboration-draft.promoted-request' => 'Draft promoted to follow-up request',
                'plugin.third-party-risk.collaboration-request.created' => 'Follow-up request created',
                'plugin.third-party-risk.collaboration-request.updated' => 'Follow-up request updated',
                'plugin.third-party-risk.collaboration-request.started' => 'Follow-up request started',
                'plugin.third-party-risk.collaboration-request.waiting' => 'Follow-up request waiting',
                'plugin.third-party-risk.collaboration-request.completed' => 'Follow-up request completed',
                'plugin.third-party-risk.collaboration-request.cancelled' => 'Follow-up request cancelled',
                default => null,
            };

            if ($label === null) {
                continue;
            }

            $detailParts = [];

            if (is_string($record->summary['contact_email'] ?? null) && $record->summary['contact_email'] !== '') {
                $detailParts[] = $record->summary['contact_email'];
            }

            if (is_string($record->summary['label'] ?? null) && $record->summary['label'] !== '') {
                $detailParts[] = $record->summary['label'];
            }

            if (is_string($record->summary['questionnaire_item_id'] ?? null) && $record->summary['questionnaire_item_id'] !== '') {
                $detailParts[] = (string) $record->summary['questionnaire_item_id'];
            }

            if (is_string($record->summary['reason'] ?? null) && $record->summary['reason'] !== '') {
                $detailParts[] = (string) $record->summary['reason'];
            }

            if (is_string($record->summary['error'] ?? null) && $record->summary['error'] !== '') {
                $detailParts[] = (string) $record->summary['error'];
            }

            if (is_string($record->summary['contact_name'] ?? null) && $record->summary['contact_name'] !== '') {
                $detailParts[] = (string) $record->summary['contact_name'];
            }

            if (is_string($record->summary['collection_channel'] ?? null) && $record->summary['collection_channel'] !== '') {
                $detailParts[] = ucwords(str_replace('-', ' ', (string) $record->summary['collection_channel']));
            }

            $entries[] = [
                'at' => $record->createdAt,
                'title' => $label,
                'detail' => implode(' · ', $detailParts),
                'kind' => 'external',
            ];
        }

        usort($entries, static function (array $left, array $right): int {
            return strcmp((string) $right['at'], (string) $left['at']);
        });

        return array_slice(array_values(array_filter($entries, static fn (array $entry): bool => $entry['at'] !== '')), 0, 20);
    }

    private function brokeredCollectionStatusLabel(string $status): string
    {
        return match ($status) {
            'queued' => 'Queued',
            'in-progress' => 'In progress',
            'submitted' => 'Submitted',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucwords(str_replace('-', ' ', $status)),
        };
    }

    private function brokeredCollectionChannelLabel(string $channel): string
    {
        return match ($channel) {
            'email' => 'Email',
            'meeting' => 'Meeting',
            'call' => 'Call',
            'uploaded-docs' => 'Uploaded docs',
            'broker-note' => 'Broker note',
            default => ucwords(str_replace('-', ' ', $channel)),
        };
    }

    private function attachmentUploadProfileLabel(string $profile): string
    {
        return match ($profile) {
            'documents_only' => 'Documents only',
            'documents_and_spreadsheets' => 'Documents and spreadsheets',
            'images_only' => 'Images only',
            'review_artifacts' => 'Mixed review artifacts',
            default => $profile !== '' ? ucwords(str_replace('-', ' ', $profile)) : 'Default review artifacts',
        };
    }

    /**
     * @return array<int, string>
     */
    private function csvList(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', $value),
        )));
    }

    /**
     * @return array<string, mixed>
     */
    private function baseQuery(ScreenRenderContext $context, bool $includeSelection = true): array
    {
        $query = $context->query;
        $query['organization_id'] = $context->organizationId ?? ($query['organization_id'] ?? 'org-a');
        $query['locale'] = $context->locale;

        if ($context->scopeId !== null) {
            $query['scope_id'] = $context->scopeId;
        }

        foreach ($context->memberships as $membership) {
            $query['membership_ids'][] = $membership->id;
        }

        if (! $includeSelection) {
            unset($query['vendor_id']);
        }

        return $query;
    }

    /**
     * @return array<int, array{id: string, assignment_id: string, display_name: string, kind: string}>
     */
    private function ownerAssignments(
        FunctionalActorServiceInterface $actors,
        string $reviewId,
        string $organizationId,
        ?string $scopeId,
    ): array {
        $owners = [];

        foreach ($actors->assignmentsFor('vendor-review', $reviewId, $organizationId, $scopeId) as $assignment) {
            if ($assignment->assignmentType !== 'owner') {
                continue;
            }

            $actor = $actors->findActor($assignment->functionalActorId);

            if ($actor === null) {
                continue;
            }

            $owners[] = [
                'id' => $actor->id,
                'assignment_id' => $assignment->id,
                'display_name' => $actor->displayName,
                'kind' => $actor->kind,
            ];
        }

        return $owners;
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function actorOptions(
        FunctionalActorServiceInterface $actors,
        string $organizationId,
        ?string $scopeId,
    ): array {
        return array_map(static fn ($actor): array => [
            'id' => $actor->id,
            'label' => sprintf('%s (%s)', $actor->displayName, $actor->kind),
        ], $actors->actors($organizationId, $scopeId));
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function linkedOptions(
        string $table,
        string $idColumn,
        string $labelColumn,
        string $organizationId,
        ?string $scopeId,
    ): array {
        $query = DB::table($table)
            ->where('organization_id', $organizationId)
            ->orderBy($labelColumn);

        if ($scopeId !== null && $scopeId !== '') {
            $query->where(function ($nested) use ($scopeId): void {
                $nested->where('scope_id', $scopeId)->orWhereNull('scope_id');
            });
        }

        return $query->get([$idColumn, $labelColumn])
            ->map(static fn ($row): array => [
                'id' => (string) $row->{$idColumn},
                'label' => sprintf('%s [%s]', (string) $row->{$labelColumn}, (string) $row->{$idColumn}),
            ])->all();
    }

}
