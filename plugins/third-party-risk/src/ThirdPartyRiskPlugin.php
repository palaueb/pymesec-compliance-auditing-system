<?php

namespace PymeSec\Plugins\ThirdPartyRisk;

use Illuminate\Support\Facades\DB;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Core\Security\ContextualReferenceValidator;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\UI\ScreenDefinition;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\UI\ToolbarAction;
use PymeSec\Core\Workflows\Contracts\WorkflowRegistryInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowDefinition;
use PymeSec\Core\Workflows\WorkflowTransitionDefinition;

class ThirdPartyRiskPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(ThirdPartyRiskRepository::class, fn ($app) => new ThirdPartyRiskRepository(
            $app->make(ContextualReferenceValidator::class),
            $app->make(AuditTrailInterface::class),
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
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManage = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.third-party-risk.vendors.manage',
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
        $reviewProfilesById = [];
        $questionnaireTemplatesById = [];

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

        $vendors = [];

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

            $vendors[] = [
                ...$vendor,
                'current_review' => [
                    ...$currentReview,
                    'review_profile' => $reviewProfilesById[$currentReview['review_profile_id']] ?? null,
                    'questionnaire_template' => $questionnaireTemplatesById[$currentReview['questionnaire_template_id']] ?? null,
                    'state' => $instance->currentState,
                    'state_label' => $this->stateLabel($instance->currentState),
                    'transitions' => $canManage ? $this->transitionsForState($instance->currentState) : [],
                    'history' => $workflow->history('plugin.third-party-risk.review-lifecycle', 'vendor-review', $currentReview['id']),
                    'artifacts' => array_map(
                        static fn ($artifact): array => $artifact->toArray(),
                        $artifacts->forSubject('vendor-review', $currentReview['id'], $organizationId, $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null, 10),
                    ),
                    'questionnaire_items' => array_map(function (array $item) use ($vendor, $currentReview): array {
                        return [
                            ...$item,
                            'update_route' => route('plugin.third-party-risk.questionnaire-items.update', [
                                'vendorId' => $vendor['id'],
                                'reviewId' => $currentReview['id'],
                                'itemId' => $item['id'],
                            ]),
                        ];
                    }, $repository->questionnaireItemsForReview($currentReview['id'])),
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
                    }, $repository->externalLinksForReview($currentReview['id'])),
                    'owner_assignments' => $this->ownerAssignments($actors, $currentReview['id'], $organizationId, $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null),
                    'transition_route' => route('plugin.third-party-risk.transition', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id'], 'transitionKey' => '__TRANSITION__']),
                    'artifact_upload_route' => route('plugin.third-party-risk.artifacts.store', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id']]),
                    'questionnaire_store_route' => route('plugin.third-party-risk.questionnaire-items.store', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id']]),
                    'questionnaire_apply_template_route' => route('plugin.third-party-risk.questionnaire-items.apply-template', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id']]),
                    'external_link_issue_route' => route('plugin.third-party-risk.external.links.issue', ['vendorId' => $vendor['id'], 'reviewId' => $currentReview['id']]),
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

        $selectedVendorId = is_string($screenContext->query['vendor_id'] ?? null) && $screenContext->query['vendor_id'] !== ''
            ? (string) $screenContext->query['vendor_id']
            : null;
        $selectedVendor = null;

        if (is_string($selectedVendorId)) {
            foreach ($vendors as $vendor) {
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
            'selected_vendor' => $selectedVendor,
            'can_manage_vendors' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $baseQuery,
            'create_route' => route('plugin.third-party-risk.store'),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
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
            'vendors_list_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.third-party-risk.root']),
        ];
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
