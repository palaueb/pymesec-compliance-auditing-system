<?php

namespace PymeSec\Plugins\DataFlowsPrivacy;

use Illuminate\Support\Facades\DB;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\UI\ScreenDefinition;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\UI\ToolbarAction;
use PymeSec\Core\Workflows\Contracts\WorkflowRegistryInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowDefinition;
use PymeSec\Core\Workflows\WorkflowTransitionDefinition;

class DataFlowsPrivacyPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(DataFlowsPrivacyRepository::class, fn () => new DataFlowsPrivacyRepository());

        $context->app()->make(WorkflowRegistryInterface::class)->register(new WorkflowDefinition(
            key: 'plugin.data-flows-privacy.data-flow-lifecycle',
            owner: 'data-flows-privacy',
            label: 'Privacy data flow lifecycle',
            initialState: 'draft',
            states: ['draft', 'review', 'active', 'retired'],
            transitions: [
                new WorkflowTransitionDefinition(
                    key: 'submit-review',
                    fromStates: ['draft', 'active'],
                    toState: 'review',
                    permission: 'plugin.data-flows-privacy.records.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'activate',
                    fromStates: ['review'],
                    toState: 'active',
                    permission: 'plugin.data-flows-privacy.records.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'send-back',
                    fromStates: ['review'],
                    toState: 'draft',
                    permission: 'plugin.data-flows-privacy.records.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'retire',
                    fromStates: ['active'],
                    toState: 'retired',
                    permission: 'plugin.data-flows-privacy.records.manage',
                ),
            ],
        ));

        $context->app()->make(WorkflowRegistryInterface::class)->register(new WorkflowDefinition(
            key: 'plugin.data-flows-privacy.processing-activity-lifecycle',
            owner: 'data-flows-privacy',
            label: 'Privacy processing activity lifecycle',
            initialState: 'draft',
            states: ['draft', 'review', 'active', 'retired'],
            transitions: [
                new WorkflowTransitionDefinition(
                    key: 'submit-review',
                    fromStates: ['draft', 'active'],
                    toState: 'review',
                    permission: 'plugin.data-flows-privacy.records.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'activate',
                    fromStates: ['review'],
                    toState: 'active',
                    permission: 'plugin.data-flows-privacy.records.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'send-back',
                    fromStates: ['review'],
                    toState: 'draft',
                    permission: 'plugin.data-flows-privacy.records.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'retire',
                    fromStates: ['active'],
                    toState: 'retired',
                    permission: 'plugin.data-flows-privacy.records.manage',
                ),
            ],
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.data-flows-privacy.root',
            owner: 'data-flows-privacy',
            titleKey: 'plugin.data-flows-privacy.screen.register.title',
            subtitleKey: 'plugin.data-flows-privacy.screen.register.subtitle',
            viewPath: $context->path('resources/views/register.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->registerData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->baseQuery($screenContext, false);

                if (is_string($screenContext->query['flow_id'] ?? null) && ($screenContext->query['flow_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to data flows',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.data-flows-privacy.root']),
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Processing activities',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.data-flows-privacy.activities']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'Add data flow',
                        url: '#data-flow-editor',
                        variant: 'primary',
                    ),
                    new ToolbarAction(
                        label: 'Processing activities',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.data-flows-privacy.activities']),
                        variant: 'secondary',
                    ),
                ];
            },
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.data-flows-privacy.activities',
            owner: 'data-flows-privacy',
            titleKey: 'plugin.data-flows-privacy.screen.activities.title',
            subtitleKey: 'plugin.data-flows-privacy.screen.activities.subtitle',
            viewPath: $context->path('resources/views/activities.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->activitiesData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->baseQuery($screenContext, false);

                if (is_string($screenContext->query['activity_id'] ?? null) && ($screenContext->query['activity_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to activities',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.data-flows-privacy.activities']),
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Data flows register',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.data-flows-privacy.root']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'Add processing activity',
                        url: '#privacy-activity-editor',
                        variant: 'primary',
                    ),
                    new ToolbarAction(
                        label: 'Data flows register',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.data-flows-privacy.root']),
                        variant: 'secondary',
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
        $repository = $context->app()->make(DataFlowsPrivacyRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManage = $this->canManage($authorization, $screenContext, $organizationId);
        $baseQuery = $this->baseQuery($screenContext, false);
        $assetOptions = $this->linkedOptions('assets', 'id', 'name', $organizationId, $screenContext->scopeId);
        $assetLabels = [];
        $riskOptions = $this->linkedOptions('risks', 'id', 'title', $organizationId, $screenContext->scopeId);
        $riskLabels = [];

        foreach ($assetOptions as $option) {
            $assetLabels[$option['id']] = $option['label'];
        }

        foreach ($riskOptions as $option) {
            $riskLabels[$option['id']] = $option['label'];
        }

        $dataFlows = [];

        foreach ($repository->allDataFlows($organizationId, $screenContext->scopeId) as $flow) {
            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.data-flows-privacy.data-flow-lifecycle',
                subjectType: 'privacy-data-flow',
                subjectId: $flow['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $dataFlows[] = [
                ...$flow,
                'owner_assignment' => $this->ownerAssignment($actors, 'privacy-data-flow', $flow['id'], $organizationId, $screenContext->scopeId),
                'artifacts' => array_map(
                    static fn ($artifact): array => $artifact->toArray(),
                    $artifacts->forSubject('privacy-data-flow', $flow['id'], $organizationId, $screenContext->scopeId, 5),
                ),
                'state' => $instance->currentState,
                'transitions' => $canManage ? $this->transitionsForState($instance->currentState) : [],
                'transition_route' => route('plugin.data-flows-privacy.transition', ['flowId' => $flow['id'], 'transitionKey' => '__TRANSITION__']),
                'artifact_upload_route' => route('plugin.data-flows-privacy.artifacts.store', ['flowId' => $flow['id']]),
                'update_route' => route('plugin.data-flows-privacy.update', ['flowId' => $flow['id']]),
                'linked_asset_label' => $assetLabels[$flow['linked_asset_id']] ?? null,
                'linked_risk_label' => $riskLabels[$flow['linked_risk_id']] ?? null,
                'linked_asset_url' => $flow['linked_asset_id'] !== ''
                    ? route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.asset-catalog.root', 'asset_id' => $flow['linked_asset_id']])
                    : null,
                'linked_risk_url' => $flow['linked_risk_id'] !== ''
                    ? route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.risk-management.root', 'risk_id' => $flow['linked_risk_id']])
                    : null,
                'history' => $workflow->history('plugin.data-flows-privacy.data-flow-lifecycle', 'privacy-data-flow', $flow['id']),
                'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.data-flows-privacy.root', 'flow_id' => $flow['id']]),
            ];
        }

        $selectedFlowId = is_string($screenContext->query['flow_id'] ?? null) && ($screenContext->query['flow_id'] ?? '') !== ''
            ? (string) $screenContext->query['flow_id']
            : null;
        $selectedFlow = null;

        if (is_string($selectedFlowId)) {
            foreach ($dataFlows as $flow) {
                if ($flow['id'] === $selectedFlowId) {
                    $selectedFlow = $flow;
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
            'data_flows' => $dataFlows,
            'selected_flow' => $selectedFlow,
            'can_manage_privacy' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $baseQuery,
            'create_route' => route('plugin.data-flows-privacy.store'),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'asset_options' => $assetOptions,
            'risk_options' => $riskOptions,
            'data_flows_list_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.data-flows-privacy.root']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activitiesData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(DataFlowsPrivacyRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManage = $this->canManage($authorization, $screenContext, $organizationId);
        $baseQuery = $this->baseQuery($screenContext, false);
        $dataFlowOptions = $this->linkedOptions('privacy_data_flows', 'id', 'title', $organizationId, $screenContext->scopeId);
        $dataFlowLabels = [];
        $riskOptions = $this->linkedOptions('risks', 'id', 'title', $organizationId, $screenContext->scopeId);
        $riskLabels = [];
        $policyOptions = $this->linkedOptions('policies', 'id', 'title', $organizationId, $screenContext->scopeId);
        $policyLabels = [];
        $findingOptions = $this->linkedOptions('findings', 'id', 'title', $organizationId, $screenContext->scopeId);
        $findingLabels = [];

        foreach ($dataFlowOptions as $option) {
            $dataFlowLabels[$option['id']] = $option['label'];
        }

        foreach ($riskOptions as $option) {
            $riskLabels[$option['id']] = $option['label'];
        }

        foreach ($policyOptions as $option) {
            $policyLabels[$option['id']] = $option['label'];
        }

        foreach ($findingOptions as $option) {
            $findingLabels[$option['id']] = $option['label'];
        }

        $activities = [];

        foreach ($repository->allProcessingActivities($organizationId, $screenContext->scopeId) as $activity) {
            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.data-flows-privacy.processing-activity-lifecycle',
                subjectType: 'privacy-processing-activity',
                subjectId: $activity['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $activities[] = [
                ...$activity,
                'owner_assignment' => $this->ownerAssignment($actors, 'privacy-processing-activity', $activity['id'], $organizationId, $screenContext->scopeId),
                'artifacts' => array_map(
                    static fn ($artifact): array => $artifact->toArray(),
                    $artifacts->forSubject('privacy-processing-activity', $activity['id'], $organizationId, $screenContext->scopeId, 5),
                ),
                'state' => $instance->currentState,
                'transitions' => $canManage ? $this->transitionsForState($instance->currentState) : [],
                'transition_route' => route('plugin.data-flows-privacy.activities.transition', ['activityId' => $activity['id'], 'transitionKey' => '__TRANSITION__']),
                'artifact_upload_route' => route('plugin.data-flows-privacy.activities.artifacts.store', ['activityId' => $activity['id']]),
                'update_route' => route('plugin.data-flows-privacy.activities.update', ['activityId' => $activity['id']]),
                'linked_data_flow_label' => $dataFlowLabels[$activity['linked_data_flow_ids']] ?? null,
                'linked_risk_label' => $riskLabels[$activity['linked_risk_ids']] ?? null,
                'linked_policy_label' => $policyLabels[$activity['linked_policy_id']] ?? null,
                'linked_finding_label' => $findingLabels[$activity['linked_finding_id']] ?? null,
                'linked_data_flow_url' => $activity['linked_data_flow_ids'] !== ''
                    ? route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.data-flows-privacy.root', 'flow_id' => $activity['linked_data_flow_ids']])
                    : null,
                'linked_risk_url' => $activity['linked_risk_ids'] !== ''
                    ? route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.risk-management.root', 'risk_id' => $activity['linked_risk_ids']])
                    : null,
                'linked_policy_url' => $activity['linked_policy_id'] !== ''
                    ? route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.policy-exceptions.root', 'policy_id' => $activity['linked_policy_id']])
                    : null,
                'linked_finding_url' => $activity['linked_finding_id'] !== ''
                    ? route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.findings-remediation.root', 'finding_id' => $activity['linked_finding_id']])
                    : null,
                'history' => $workflow->history('plugin.data-flows-privacy.processing-activity-lifecycle', 'privacy-processing-activity', $activity['id']),
                'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.data-flows-privacy.activities', 'activity_id' => $activity['id']]),
            ];
        }

        $selectedActivityId = is_string($screenContext->query['activity_id'] ?? null) && ($screenContext->query['activity_id'] ?? '') !== ''
            ? (string) $screenContext->query['activity_id']
            : null;
        $selectedActivity = null;

        if (is_string($selectedActivityId)) {
            foreach ($activities as $activity) {
                if ($activity['id'] === $selectedActivityId) {
                    $selectedActivity = $activity;
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
            'activities' => $activities,
            'selected_activity' => $selectedActivity,
            'can_manage_privacy' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $baseQuery,
            'create_route' => route('plugin.data-flows-privacy.activities.store'),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'data_flow_options' => $dataFlowOptions,
            'risk_options' => $riskOptions,
            'policy_options' => $policyOptions,
            'finding_options' => $findingOptions,
            'activities_list_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.data-flows-privacy.activities']),
        ];
    }

    private function canManage(
        AuthorizationServiceInterface $authorization,
        ScreenRenderContext $screenContext,
        string $organizationId,
    ): bool {
        return $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.data-flows-privacy.records.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
        ))->allowed();
    }

    /**
     * @return array<int, string>
     */
    private function transitionsForState(string $state): array
    {
        return match ($state) {
            'draft' => ['submit-review'],
            'review' => ['activate', 'send-back'],
            'active' => ['submit-review', 'retire'],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function baseQuery(ScreenRenderContext $context, bool $includeSelection = true): array
    {
        $query = $context->query;
        $query['principal_id'] = $context->principal?->id ?? ($query['principal_id'] ?? 'principal-org-a');
        $query['organization_id'] = $context->organizationId ?? ($query['organization_id'] ?? 'org-a');
        $query['locale'] = $context->locale;

        if (! $includeSelection) {
            unset($query['flow_id'], $query['activity_id']);
        }

        if ($context->scopeId !== null) {
            $query['scope_id'] = $context->scopeId;
        }

        foreach ($context->memberships as $membership) {
            $query['membership_ids'][] = $membership->id;
        }

        return $query;
    }

    /**
     * @return array<string, string>|null
     */
    private function ownerAssignment(
        FunctionalActorServiceInterface $actors,
        string $domainObjectType,
        string $domainObjectId,
        string $organizationId,
        ?string $scopeId,
    ): ?array {
        foreach ($actors->assignmentsFor($domainObjectType, $domainObjectId, $organizationId, $scopeId) as $assignment) {
            if ($assignment->assignmentType !== 'owner') {
                continue;
            }

            $actor = $actors->findActor($assignment->functionalActorId);

            if ($actor === null) {
                return null;
            }

            return [
                'id' => $actor->id,
                'display_name' => $actor->displayName,
                'kind' => $actor->kind,
            ];
        }

        return null;
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
