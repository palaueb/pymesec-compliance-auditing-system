<?php

namespace PymeSec\Plugins\ControlsCatalog;

use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Notifications\Contracts\NotificationServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
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

class ControlsCatalogPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(ControlsCatalogRepository::class);

        $context->app()->make(WorkflowRegistryInterface::class)->register(new WorkflowDefinition(
            key: 'plugin.controls-catalog.control-lifecycle',
            owner: 'controls-catalog',
            label: 'Control lifecycle',
            initialState: 'draft',
            states: ['draft', 'review', 'approved', 'deprecated'],
            transitions: [
                new WorkflowTransitionDefinition(
                    key: 'submit-review',
                    fromStates: ['draft', 'approved'],
                    toState: 'review',
                    permission: 'plugin.controls-catalog.controls.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'approve',
                    fromStates: ['review'],
                    toState: 'approved',
                    permission: 'plugin.controls-catalog.controls.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'rework',
                    fromStates: ['review'],
                    toState: 'draft',
                    permission: 'plugin.controls-catalog.controls.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'deprecate',
                    fromStates: ['approved'],
                    toState: 'deprecated',
                    permission: 'plugin.controls-catalog.controls.manage',
                ),
            ],
        ));

        $context->subscribeToEvent('plugin.controls-catalog.workflows.transitioned', function (PublicEvent $event) use ($context): void {
            if (($event->payload['to_state'] ?? null) !== 'review') {
                return;
            }

            $subjectId = $event->payload['subject_id'] ?? null;

            if (! is_string($subjectId) || $subjectId === '') {
                return;
            }

            $actors = $context->app()->make(FunctionalActorServiceInterface::class);
            $notifications = $context->app()->make(NotificationServiceInterface::class);
            $organizationId = $event->organizationId;

            if (! is_string($organizationId) || $organizationId === '') {
                return;
            }

            foreach ($actors->assignmentsFor('control', $subjectId, $organizationId, $event->scopeId) as $assignment) {
                if ($assignment->assignmentType !== 'owner') {
                    continue;
                }

                foreach ($actors->linksForActor($assignment->functionalActorId) as $link) {
                    $notifications->notify(
                        type: 'plugin.controls-catalog.review-requested',
                        title: 'Control review requested',
                        body: sprintf('Control [%s] entered review.', $subjectId),
                        principalId: $link->principalId,
                        functionalActorId: $assignment->functionalActorId,
                        organizationId: $organizationId,
                        scopeId: $event->scopeId,
                        sourceEventName: $event->name,
                        metadata: [
                            'control_id' => $subjectId,
                            'transition_key' => $event->payload['transition_key'] ?? null,
                        ],
                        deliverAt: now()->toDateTimeString(),
                    );
                }
            }
        });

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.controls-catalog.root',
            owner: 'controls-catalog',
            titleKey: 'plugin.controls-catalog.screen.catalog.title',
            subtitleKey: 'plugin.controls-catalog.screen.catalog.subtitle',
            viewPath: $context->path('resources/views/catalog.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->catalogData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->baseQuery($screenContext);
                unset($query['control_id']);

                if (is_string($screenContext->query['control_id'] ?? null) && ($screenContext->query['control_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to controls',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.controls-catalog.root']),
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Framework adoption',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.controls-catalog.framework-adoption']),
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Review queue',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.controls-catalog.reviews']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'New control',
                        url: '#control-editor',
                        variant: 'primary',
                    ),
                    new ToolbarAction(
                        label: 'Framework adoption',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.controls-catalog.framework-adoption']),
                        variant: 'secondary',
                    ),
                    new ToolbarAction(
                        label: 'Review queue',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.controls-catalog.reviews']),
                        variant: 'secondary',
                    ),
                ];
            },
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.controls-catalog.framework-adoption',
            owner: 'controls-catalog',
            titleKey: 'plugin.controls-catalog.screen.framework_adoption.title',
            subtitleKey: 'plugin.controls-catalog.screen.framework_adoption.subtitle',
            viewPath: $context->path('resources/views/framework-adoption.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->frameworkAdoptionData($context, $screenContext),
            toolbarResolver: fn (ScreenRenderContext $screenContext): array => [
                new ToolbarAction(
                    label: 'Control catalog',
                    url: route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.controls-catalog.root']),
                    variant: 'secondary',
                ),
                new ToolbarAction(
                    label: 'Review queue',
                    url: route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.controls-catalog.reviews']),
                    variant: 'secondary',
                ),
            ],
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.controls-catalog.reviews',
            owner: 'controls-catalog',
            titleKey: 'plugin.controls-catalog.screen.reviews.title',
            subtitleKey: 'plugin.controls-catalog.screen.reviews.subtitle',
            viewPath: $context->path('resources/views/reviews.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->reviewData($context, $screenContext),
            toolbarResolver: fn (ScreenRenderContext $screenContext): array => [
                new ToolbarAction(
                    label: 'Control catalog',
                    url: route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.controls-catalog.root']),
                    variant: 'secondary',
                ),
                new ToolbarAction(
                    label: 'Framework adoption',
                    url: route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.controls-catalog.framework-adoption']),
                    variant: 'secondary',
                ),
            ],
        ));
    }

    public function boot(PluginContext $context): void
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(ControlsCatalogRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $objectAccess = $context->app()->make(ObjectAccessService::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $catalog = $objectAccess->filterRecords(
            $repository->all($organizationId, $screenContext->scopeId),
            'id',
            $screenContext->principal?->id,
            $organizationId,
            $screenContext->scopeId,
            'control',
        );
        $requirementsByControl = $repository->requirementsForControls(array_map(
            static fn (array $control): string => $control['id'],
            $catalog,
        ));
        $controls = [];
        $canManage = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.controls-catalog.controls.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
        ))->allowed();

        foreach ($catalog as $control) {
            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.controls-catalog.control-lifecycle',
                subjectType: 'control',
                subjectId: $control['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $controls[] = [
                ...$control,
                'open_url' => route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.controls-catalog.root', 'control_id' => $control['id']]),
                'owner_assignment' => $this->ownerAssignment($actors, $control['id'], $organizationId, $screenContext->scopeId),
                'artifacts' => array_map(
                    static fn ($artifact): array => $artifact->toArray(),
                    $artifacts->forSubject('control', $control['id'], $organizationId, $screenContext->scopeId, 5),
                ),
                'state' => $instance->currentState,
                'transitions' => $canManage ? $this->transitionsForState($instance->currentState) : [],
                'transition_route' => route('plugin.controls-catalog.transition', ['controlId' => $control['id'], 'transitionKey' => '__TRANSITION__']),
                'artifact_upload_route' => route('plugin.controls-catalog.artifacts.store', ['controlId' => $control['id']]),
                'update_route' => route('plugin.controls-catalog.update', ['controlId' => $control['id']]),
                'attach_requirement_route' => route('plugin.controls-catalog.requirements.attach', ['controlId' => $control['id']]),
                'requirements' => $requirementsByControl[$control['id']] ?? [],
            ];
        }

        $selectedControlId = is_string($screenContext->query['control_id'] ?? null) && $screenContext->query['control_id'] !== ''
            ? (string) $screenContext->query['control_id']
            : null;
        $selectedControl = null;

        if (is_string($selectedControlId)) {
            foreach ($controls as $control) {
                if (($control['id'] ?? null) === $selectedControlId) {
                    $selectedControl = $control;
                    break;
                }
            }
        }

        $listQuery = $this->baseQuery($screenContext);
        unset($listQuery['control_id']);
        $frameworkWorkspace = $this->frameworkWorkspaceData($context, $screenContext, $controls);

        return [
            'controls' => $controls,
            'selected_control' => $selectedControl,
            'frameworks' => $frameworkWorkspace['frameworks'],
            'requirements' => $frameworkWorkspace['requirements'],
            'can_manage_controls' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $listQuery,
            'create_route' => route('plugin.controls-catalog.store'),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'framework_options' => array_map(static fn (array $framework): array => [
                'id' => $framework['id'],
                'label' => sprintf('%s · %s', $framework['code'], $framework['name']),
            ], $frameworkWorkspace['frameworks']),
            'adopted_framework_options' => $repository->adoptedFrameworkOptions($organizationId, $screenContext->scopeId),
            'requirement_options' => array_values(array_map(
                static fn (array $requirement): array => [
                    'id' => $requirement['id'],
                    'label' => sprintf('%s · %s · %s', $requirement['framework_code'], $requirement['code'], $requirement['title']),
                ],
                array_filter($frameworkWorkspace['requirements'], static fn (array $req): bool => ($req['element_type'] ?? '') !== 'domain'),
            )),
            'scope_options' => $frameworkWorkspace['scope_options'],
            'controls_list_url' => route('core.shell.index', [...$listQuery, 'menu' => 'plugin.controls-catalog.root']),
            'frameworks_list_url' => route('core.shell.index', [...$listQuery, 'menu' => 'plugin.controls-catalog.framework-adoption']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function frameworkAdoptionData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(ControlsCatalogRepository::class);
        $objectAccess = $context->app()->make(ObjectAccessService::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $catalog = $objectAccess->filterRecords(
            $repository->all($organizationId, $screenContext->scopeId),
            'id',
            $screenContext->principal?->id,
            $organizationId,
            $screenContext->scopeId,
            'control',
        );
        $requirementsByControl = $repository->requirementsForControls(array_map(
            static fn (array $control): string => $control['id'],
            $catalog,
        ));
        $controls = array_map(static function (array $control) use ($requirementsByControl): array {
            return [
                ...$control,
                'requirements' => $requirementsByControl[$control['id']] ?? [],
            ];
        }, $catalog);
        $frameworkWorkspace = $this->frameworkWorkspaceData($context, $screenContext, $controls);

        return [
            'frameworks' => $frameworkWorkspace['frameworks'],
            'requirements' => $frameworkWorkspace['requirements'],
            'scope_options' => $frameworkWorkspace['scope_options'],
            'query' => $this->baseQuery($screenContext),
            'can_manage_controls' => $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
                principal: $screenContext->principal,
                permission: 'plugin.controls-catalog.controls.manage',
                memberships: $screenContext->memberships,
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            ))->allowed(),
            'create_framework_route' => route('plugin.controls-catalog.frameworks.store'),
            'create_requirement_route' => route('plugin.controls-catalog.requirements.store'),
            'framework_adoption_status_options' => [
                'active' => 'Active',
                'in-progress' => 'In progress',
                'inactive' => 'Inactive',
            ],
            'framework_target_level_options' => [
                'basic' => 'Basic',
                'medium' => 'Medium',
                'high' => 'High',
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $frameworks
     * @param  array<int, array<string, mixed>>  $requirements
     * @param  array<int, array<string, mixed>>  $controls
     * @return array<int, array<string, mixed>>
     */
    private function frameworkCoverageSummary(array $frameworks, array $requirements, array $controls): array
    {
        $requirementCounts = [];
        $mappedRequirementIds = [];
        $linkedControlIds = [];

        foreach ($requirements as $requirement) {
            $frameworkId = (string) ($requirement['framework_id'] ?? '');

            if ($frameworkId === '' || ($requirement['element_type'] ?? '') === 'domain') {
                continue;
            }

            $requirementCounts[$frameworkId] = ($requirementCounts[$frameworkId] ?? 0) + 1;
        }

        foreach ($controls as $control) {
            $controlId = (string) ($control['id'] ?? '');
            $controlFrameworkId = (string) ($control['framework_id'] ?? '');

            if ($controlFrameworkId !== '' && $controlId !== '') {
                $linkedControlIds[$controlFrameworkId][$controlId] = true;
            }

            foreach (($control['requirements'] ?? []) as $requirement) {
                $frameworkId = (string) ($requirement['framework_id'] ?? '');
                $requirementId = (string) ($requirement['requirement_id'] ?? '');

                if ($frameworkId === '' || $requirementId === '') {
                    continue;
                }

                $mappedRequirementIds[$frameworkId][$requirementId] = true;

                if ($controlId !== '') {
                    $linkedControlIds[$frameworkId][$controlId] = true;
                }
            }
        }

        return array_map(static function (array $framework) use ($requirementCounts, $mappedRequirementIds, $linkedControlIds): array {
            $frameworkId = (string) ($framework['id'] ?? '');
            $requirementCount = $requirementCounts[$frameworkId] ?? 0;
            $mappedCount = count($mappedRequirementIds[$frameworkId] ?? []);
            $linkedControlCount = count($linkedControlIds[$frameworkId] ?? []);

            return [
                ...$framework,
                'requirement_count' => $requirementCount,
                'mapped_requirement_count' => $mappedCount,
                'linked_control_count' => $linkedControlCount,
                'coverage_percent' => $requirementCount > 0
                    ? (int) round(($mappedCount / $requirementCount) * 100)
                    : 0,
                'source_label' => (($framework['organization_id'] ?? '') === '')
                    ? 'Global pack'
                    : 'Custom framework',
            ];
        }, $frameworks);
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(ControlsCatalogRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $objectAccess = $context->app()->make(ObjectAccessService::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $notifications = $context->app()->make(NotificationServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $rows = [];

        foreach ($objectAccess->filterRecords(
            $repository->all($organizationId, $screenContext->scopeId),
            'id',
            $screenContext->principal?->id,
            $organizationId,
            $screenContext->scopeId,
            'control',
        ) as $control) {
            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.controls-catalog.control-lifecycle',
                subjectType: 'control',
                subjectId: $control['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $rows[] = [
                'control' => $control,
                'instance' => $instance,
                'artifacts' => array_map(
                    static fn ($artifact): array => $artifact->toArray(),
                    $artifacts->forSubject('control', $control['id'], $organizationId, $screenContext->scopeId, 10),
                ),
                'history' => $workflow->history('plugin.controls-catalog.control-lifecycle', 'control', $control['id']),
                'notifications' => $notifications->latest(10, [
                    'organization_id' => $organizationId,
                    'source_event_name' => 'plugin.controls-catalog.workflows.transitioned',
                ]),
            ];
        }

        return [
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $controls
     * @return array<string, mixed>
     */
    private function frameworkWorkspaceData(PluginContext $context, ScreenRenderContext $screenContext, array $controls): array
    {
        $repository = $context->app()->make(ControlsCatalogRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $frameworks = $repository->frameworks($organizationId);
        $frameworkAdoptions = $repository->frameworkAdoptionMap($organizationId, $screenContext->scopeId);
        $requirements = $repository->requirements($organizationId);
        $requirementsByFramework = [];

        foreach ($requirements as $requirement) {
            if (($requirement['element_type'] ?? '') === 'domain') {
                continue;
            }

            $frameworkId = (string) ($requirement['framework_id'] ?? '');

            if ($frameworkId === '') {
                continue;
            }

            $requirementsByFramework[$frameworkId][] = $requirement;
        }

        $scopeContext = $tenancy->resolveContext(
            principalId: $screenContext->principal?->id,
            requestedOrganizationId: $organizationId,
            requestedScopeId: $screenContext->scopeId,
            requestedMembershipIds: array_map(static fn ($membership): string => $membership->id, $screenContext->memberships),
        );

        $frameworkRows = array_map(function (array $framework) use (
            $frameworkAdoptions,
            $scopeContext,
            $artifacts,
            $organizationId,
            $requirementsByFramework
        ): array {
            $adoption = $frameworkAdoptions[$framework['id']] ?? null;
            $scopeLabel = 'Not adopted';
            $mandateArtifacts = [];

            if (is_array($adoption)) {
                $scopeLabel = 'Organization-wide';

                if (($adoption['scope_id'] ?? '') !== '') {
                    foreach ($scopeContext->scopes as $scope) {
                        if ($scope->id === $adoption['scope_id']) {
                            $scopeLabel = $scope->name;
                            break;
                        }
                    }
                }

                $adoptionScopeId = ($adoption['scope_id'] ?? '') !== '' ? (string) $adoption['scope_id'] : null;
                $mandateArtifacts = array_values(array_filter(
                    array_map(
                        static fn ($artifact): array => $artifact->toArray(),
                        $artifacts->forSubject('framework-adoption', (string) $adoption['id'], $organizationId, $adoptionScopeId, 10),
                    ),
                    static fn (array $artifact): bool => ($artifact['artifact_type'] ?? '') === 'mandate-document',
                ));
            }

            return [
                ...$framework,
                'adoption_id' => is_array($adoption) ? (string) ($adoption['id'] ?? '') : '',
                'adoption_status' => is_array($adoption) ? (string) ($adoption['status'] ?? 'not-adopted') : 'not-adopted',
                'adoption_scope_id' => is_array($adoption) ? (string) ($adoption['scope_id'] ?? '') : '',
                'adoption_scope_label' => $scopeLabel,
                'target_level' => is_array($adoption) ? (string) ($adoption['target_level'] ?? '') : '',
                'adopted_at' => is_array($adoption) ? (string) ($adoption['adopted_at'] ?? '') : '',
                'adoption_update_route' => route('plugin.controls-catalog.frameworks.adoption.upsert', ['frameworkId' => $framework['id']]),
                'requirements' => $requirementsByFramework[$framework['id']] ?? [],
                'mandate_document' => $mandateArtifacts[0] ?? null,
                'mandate_document_count' => count($mandateArtifacts),
            ];
        }, $frameworks);

        return [
            'frameworks' => $this->frameworkCoverageSummary($frameworkRows, $requirements, $controls),
            'requirements' => $requirements,
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function transitionsForState(string $state): array
    {
        return match ($state) {
            'draft' => ['submit-review'],
            'review' => ['approve', 'rework'],
            'approved' => ['submit-review', 'deprecate'],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function baseQuery(ScreenRenderContext $context): array
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

        return $query;
    }

    /**
     * @return array<string, string> | null
     */
    private function ownerAssignment(
        FunctionalActorServiceInterface $actors,
        string $controlId,
        string $organizationId,
        ?string $scopeId,
    ): ?array {
        foreach ($actors->assignmentsFor('control', $controlId, $organizationId, $scopeId) as $assignment) {
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
}
