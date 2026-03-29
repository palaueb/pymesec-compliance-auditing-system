<?php

namespace PymeSec\Plugins\ContinuityBcm;

use Illuminate\Support\Facades\DB;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
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

class ContinuityBcmPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(ContinuityBcmRepository::class, fn ($app) => new ContinuityBcmRepository(
            $app->make(ContextualReferenceValidator::class),
        ));

        $context->app()->make(WorkflowRegistryInterface::class)->register(new WorkflowDefinition(
            key: 'plugin.continuity-bcm.service-lifecycle',
            owner: 'continuity-bcm',
            label: 'Continuity service lifecycle',
            initialState: 'draft',
            states: ['draft', 'review', 'active', 'retired'],
            transitions: [
                new WorkflowTransitionDefinition(
                    key: 'submit-review',
                    fromStates: ['draft', 'active'],
                    toState: 'review',
                    permission: 'plugin.continuity-bcm.plans.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'activate',
                    fromStates: ['review'],
                    toState: 'active',
                    permission: 'plugin.continuity-bcm.plans.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'send-back',
                    fromStates: ['review'],
                    toState: 'draft',
                    permission: 'plugin.continuity-bcm.plans.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'retire',
                    fromStates: ['active'],
                    toState: 'retired',
                    permission: 'plugin.continuity-bcm.plans.manage',
                ),
            ],
        ));

        $context->app()->make(WorkflowRegistryInterface::class)->register(new WorkflowDefinition(
            key: 'plugin.continuity-bcm.plan-lifecycle',
            owner: 'continuity-bcm',
            label: 'Continuity recovery plan lifecycle',
            initialState: 'draft',
            states: ['draft', 'review', 'active', 'retired'],
            transitions: [
                new WorkflowTransitionDefinition(
                    key: 'submit-review',
                    fromStates: ['draft', 'active'],
                    toState: 'review',
                    permission: 'plugin.continuity-bcm.plans.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'activate',
                    fromStates: ['review'],
                    toState: 'active',
                    permission: 'plugin.continuity-bcm.plans.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'send-back',
                    fromStates: ['review'],
                    toState: 'draft',
                    permission: 'plugin.continuity-bcm.plans.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'retire',
                    fromStates: ['active'],
                    toState: 'retired',
                    permission: 'plugin.continuity-bcm.plans.manage',
                ),
            ],
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.continuity-bcm.root',
            owner: 'continuity-bcm',
            titleKey: 'plugin.continuity-bcm.screen.register.title',
            subtitleKey: 'plugin.continuity-bcm.screen.register.subtitle',
            viewPath: $context->path('resources/views/register.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->registerData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->baseQuery($screenContext, false);

                if (is_string($screenContext->query['service_id'] ?? null) && ($screenContext->query['service_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to services',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.continuity-bcm.root']),
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Recovery plans',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.continuity-bcm.plans']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'Add continuity service',
                        url: '#toggle-continuity-service-editor',
                        variant: 'primary',
                    ),
                    new ToolbarAction(
                        label: 'Recovery plans',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.continuity-bcm.plans']),
                        variant: 'secondary',
                    ),
                ];
            },
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.continuity-bcm.plans',
            owner: 'continuity-bcm',
            titleKey: 'plugin.continuity-bcm.screen.plans.title',
            subtitleKey: 'plugin.continuity-bcm.screen.plans.subtitle',
            viewPath: $context->path('resources/views/plans.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->plansData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $actions = [
                    new ToolbarAction(
                        label: 'Choose service',
                        url: route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.continuity-bcm.root']).'#continuity-service-plans',
                        variant: 'primary',
                    ),
                    new ToolbarAction(
                        label: 'Continuity services',
                        url: route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.continuity-bcm.root']),
                        variant: 'secondary',
                    ),
                ];

                if (is_string($screenContext->query['plan_id'] ?? null) && ($screenContext->query['plan_id'] ?? '') !== '') {
                    array_unshift($actions, new ToolbarAction(
                        label: 'Back to plans',
                        url: route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.continuity-bcm.plans']),
                        variant: 'secondary',
                    ));
                }

                return $actions;
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
        $repository = $context->app()->make(ContinuityBcmRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $objectAccess = $context->app()->make(ObjectAccessService::class);
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
        $serviceCatalog = $objectAccess->filterRecords(
            $repository->allServices($organizationId, $screenContext->scopeId),
            'id',
            $screenContext->principal?->id,
            $organizationId,
            $screenContext->scopeId,
            'continuity-service',
        );
        $dependenciesByService = $repository->dependenciesForServices(array_map(
            static fn (array $service): string => $service['id'],
            $serviceCatalog,
        ));

        foreach ($assetOptions as $option) {
            $assetLabels[$option['id']] = $option['label'];
        }

        foreach ($riskOptions as $option) {
            $riskLabels[$option['id']] = $option['label'];
        }

        $services = [];

        foreach ($serviceCatalog as $service) {
            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.continuity-bcm.service-lifecycle',
                subjectType: 'continuity-service',
                subjectId: $service['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $plans = $repository->plansForService($service['id']);

            $services[] = [
                ...$service,
                'impact_tier_label' => ContinuityReferenceData::impactTierLabel($service['impact_tier']),
                'owner_assignments' => $this->ownerAssignments($actors, 'continuity-service', $service['id'], $organizationId, $screenContext->scopeId),
                'artifacts' => array_map(
                    static fn ($artifact): array => $artifact->toArray(),
                    $artifacts->forSubject('continuity-service', $service['id'], $organizationId, $screenContext->scopeId, 5),
                ),
                'state' => $instance->currentState,
                'transitions' => $canManage ? $this->transitionsForState($instance->currentState) : [],
                'transition_route' => route('plugin.continuity-bcm.transition', ['serviceId' => $service['id'], 'transitionKey' => '__TRANSITION__']),
                'artifact_upload_route' => route('plugin.continuity-bcm.artifacts.store', ['serviceId' => $service['id']]),
                'update_route' => route('plugin.continuity-bcm.update', ['serviceId' => $service['id']]),
                'owner_remove_route' => route('plugin.continuity-bcm.owners.destroy', ['serviceId' => $service['id'], 'assignmentId' => '__ASSIGNMENT__']),
                'plan_store_route' => route('plugin.continuity-bcm.plans.store', ['serviceId' => $service['id']]),
                'dependency_store_route' => route('plugin.continuity-bcm.dependencies.store', ['serviceId' => $service['id']]),
                'plan_count' => count($plans),
                'linked_asset_label' => $assetLabels[$service['linked_asset_id']] ?? null,
                'linked_assets' => $service['linked_asset_id'] !== '' ? [[
                    'id' => $service['linked_asset_id'],
                    'label' => $assetLabels[$service['linked_asset_id']] ?? $service['linked_asset_id'],
                    'url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.asset-catalog.root']),
                ]] : [],
                'linked_risks' => $service['linked_risk_id'] !== '' ? [[
                    'id' => $service['linked_risk_id'],
                    'label' => $riskLabels[$service['linked_risk_id']] ?? $service['linked_risk_id'],
                    'url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.risk-management.root']),
                ]] : [],
                'dependencies' => array_map(static fn (array $dependency): array => [
                    ...$dependency,
                    'dependency_kind_label' => ContinuityReferenceData::dependencyKindLabel($dependency['dependency_kind']),
                ], $dependenciesByService[$service['id']] ?? []),
                'plans' => $plans,
                'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.continuity-bcm.root', 'service_id' => $service['id']]),
            ];
        }

        $selectedServiceId = is_string($screenContext->query['service_id'] ?? null) && $screenContext->query['service_id'] !== ''
            ? (string) $screenContext->query['service_id']
            : null;
        $selectedService = null;

        if (is_string($selectedServiceId)) {
            foreach ($services as $service) {
                if ($service['id'] !== $selectedServiceId) {
                    continue;
                }

                $service['plans'] = array_map(fn (array $plan): array => [
                    ...$plan,
                    'open_url' => route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.continuity-bcm.plans', 'plan_id' => $plan['id']]),
                ], $service['plans']);
                $selectedService = $service;
                break;
            }
        }

        $scopeContext = $tenancy->resolveContext(
            principalId: $screenContext->principal?->id,
            requestedOrganizationId: $organizationId,
            requestedScopeId: $screenContext->scopeId,
            requestedMembershipIds: array_map(static fn ($membership): string => $membership->id, $screenContext->memberships),
        );

        return [
            'services' => $services,
            'selected_service' => $selectedService,
            'can_manage_continuity' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $this->baseQuery($screenContext, false),
            'create_route' => route('plugin.continuity-bcm.store'),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'asset_options' => $assetOptions,
            'risk_options' => $this->linkedOptions('risks', 'id', 'title', $organizationId, $screenContext->scopeId),
            'policy_options' => $this->linkedOptions('policies', 'id', 'title', $organizationId, $screenContext->scopeId),
            'finding_options' => $this->linkedOptions('findings', 'id', 'title', $organizationId, $screenContext->scopeId),
            'impact_tier_options' => ContinuityReferenceData::optionsFor('impact_tier'),
            'dependency_kind_options' => ContinuityReferenceData::optionsFor('dependency_kind'),
            'service_options' => array_map(static fn (array $service): array => [
                'id' => $service['id'],
                'label' => sprintf('%s [%s]', $service['title'], $service['id']),
            ], $serviceCatalog),
            'services_list_url' => route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.continuity-bcm.root']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function plansData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(ContinuityBcmRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $objectAccess = $context->app()->make(ObjectAccessService::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManage = $this->canManage($authorization, $screenContext, $organizationId);
        $baseQuery = $this->baseQuery($screenContext, false);
        $planCatalog = $objectAccess->filterRecords(
            $repository->allPlans($organizationId, $screenContext->scopeId),
            'id',
            $screenContext->principal?->id,
            $organizationId,
            $screenContext->scopeId,
            'continuity-plan',
        );
        $exercisesByPlan = $repository->exercisesForPlans(array_map(
            static fn (array $plan): string => $plan['id'],
            $planCatalog,
        ));
        $executionsByPlan = $repository->testExecutionsForPlans(array_map(
            static fn (array $plan): string => $plan['id'],
            $planCatalog,
        ));
        $serviceOptions = $this->linkedOptions('continuity_services', 'id', 'title', $organizationId, $screenContext->scopeId);
        $policyOptions = $this->linkedOptions('policies', 'id', 'title', $organizationId, $screenContext->scopeId);
        $findingOptions = $this->linkedOptions('findings', 'id', 'title', $organizationId, $screenContext->scopeId);
        $serviceLabels = [];
        $policyLabels = [];
        $findingLabels = [];

        foreach ($serviceOptions as $option) {
            $serviceLabels[$option['id']] = $option['label'];
        }

        foreach ($policyOptions as $option) {
            $policyLabels[$option['id']] = $option['label'];
        }

        foreach ($findingOptions as $option) {
            $findingLabels[$option['id']] = $option['label'];
        }

        $plans = [];

        foreach ($planCatalog as $plan) {
            $service = $repository->findService($plan['service_id']);

            if ($service === null) {
                continue;
            }

            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.continuity-bcm.plan-lifecycle',
                subjectType: 'continuity-plan',
                subjectId: $plan['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $plans[] = [
                ...$plan,
                'service' => $service,
                'owner_assignments' => $this->ownerAssignments($actors, 'continuity-plan', $plan['id'], $organizationId, $screenContext->scopeId),
                'artifacts' => array_map(
                    static fn ($artifact): array => $artifact->toArray(),
                    $artifacts->forSubject('continuity-plan', $plan['id'], $organizationId, $screenContext->scopeId, 5),
                ),
                'state' => $instance->currentState,
                'transitions' => $canManage ? $this->transitionsForState($instance->currentState) : [],
                'transition_route' => route('plugin.continuity-bcm.plans.transition', ['planId' => $plan['id'], 'transitionKey' => '__TRANSITION__']),
                'artifact_upload_route' => route('plugin.continuity-bcm.plans.artifacts.store', ['planId' => $plan['id']]),
                'update_route' => route('plugin.continuity-bcm.plans.update', ['planId' => $plan['id']]),
                'owner_remove_route' => route('plugin.continuity-bcm.plans.owners.destroy', ['planId' => $plan['id'], 'assignmentId' => '__ASSIGNMENT__']),
                'exercise_store_route' => route('plugin.continuity-bcm.plans.exercises.store', ['planId' => $plan['id']]),
                'execution_store_route' => route('plugin.continuity-bcm.plans.executions.store', ['planId' => $plan['id']]),
                'exercises' => array_map(static fn (array $exercise): array => [
                    ...$exercise,
                    'exercise_type_label' => ContinuityReferenceData::exerciseTypeLabel($exercise['exercise_type']),
                    'outcome_label' => ContinuityReferenceData::exerciseOutcomeLabel($exercise['outcome']),
                ], $exercisesByPlan[$plan['id']] ?? []),
                'executions' => array_map(static fn (array $execution): array => [
                    ...$execution,
                    'execution_type_label' => ContinuityReferenceData::executionTypeLabel($execution['execution_type']),
                    'status_label' => ContinuityReferenceData::executionStatusLabel($execution['status']),
                ], $executionsByPlan[$plan['id']] ?? []),
                'service_link' => [
                    'label' => $service['title'],
                    'url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.continuity-bcm.root', 'service_id' => $service['id']]),
                ],
                'linked_policy' => $plan['linked_policy_id'] !== '' ? [
                    'id' => $plan['linked_policy_id'],
                    'label' => $policyLabels[$plan['linked_policy_id']] ?? $plan['linked_policy_id'],
                    'url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.policy-exceptions.root', 'policy_id' => $plan['linked_policy_id']]),
                ] : null,
                'linked_finding' => $plan['linked_finding_id'] !== '' ? [
                    'id' => $plan['linked_finding_id'],
                    'label' => $findingLabels[$plan['linked_finding_id']] ?? $plan['linked_finding_id'],
                    'url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.findings-remediation.root', 'finding_id' => $plan['linked_finding_id']]),
                ] : null,
                'open_url' => route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.continuity-bcm.plans', 'plan_id' => $plan['id']]),
            ];
        }

        $selectedPlanId = is_string($screenContext->query['plan_id'] ?? null) && $screenContext->query['plan_id'] !== ''
            ? (string) $screenContext->query['plan_id']
            : null;
        $selectedPlan = null;

        if (is_string($selectedPlanId)) {
            foreach ($plans as $plan) {
                if ($plan['id'] === $selectedPlanId) {
                    $selectedPlan = $plan;
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
            'plans' => $plans,
            'selected_plan' => $selectedPlan,
            'can_manage_continuity' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $this->baseQuery($screenContext, false),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'service_options' => $serviceOptions,
            'policy_options' => $policyOptions,
            'finding_options' => $findingOptions,
            'exercise_type_options' => ContinuityReferenceData::optionsFor('exercise_type'),
            'exercise_outcome_options' => ContinuityReferenceData::optionsFor('exercise_outcome'),
            'execution_type_options' => ContinuityReferenceData::optionsFor('execution_type'),
            'execution_status_options' => ContinuityReferenceData::optionsFor('execution_status'),
            'plans_list_url' => route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.continuity-bcm.plans']),
        ];
    }

    private function canManage(
        AuthorizationServiceInterface $authorization,
        ScreenRenderContext $screenContext,
        string $organizationId,
    ): bool {
        return $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.continuity-bcm.plans.manage',
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
        $query['organization_id'] = $context->organizationId ?? ($query['organization_id'] ?? 'org-a');
        $query['locale'] = $context->locale;

        if ($context->scopeId !== null) {
            $query['scope_id'] = $context->scopeId;
        }

        foreach ($context->memberships as $membership) {
            $query['membership_ids'][] = $membership->id;
        }

        if (! $includeSelection) {
            unset($query['plan_id']);
            unset($query['service_id']);
        }

        return $query;
    }

    /**
     * @return array<int, array{id: string, assignment_id: string, display_name: string, kind: string}>
     */
    private function ownerAssignments(
        FunctionalActorServiceInterface $actors,
        string $domainObjectType,
        string $domainObjectId,
        string $organizationId,
        ?string $scopeId,
    ): array {
        $owners = [];

        foreach ($actors->assignmentsFor($domainObjectType, $domainObjectId, $organizationId, $scopeId) as $assignment) {
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
