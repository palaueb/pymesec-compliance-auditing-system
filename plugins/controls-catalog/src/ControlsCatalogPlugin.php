<?php

namespace PymeSec\Plugins\ControlsCatalog;

use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Notifications\Contracts\NotificationServiceInterface;
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
        $context->app()->singleton(ControlsCatalogRepository::class, fn () => new ControlsCatalogRepository());

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
                        label: 'Review queue',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.controls-catalog.reviews']),
                        variant: 'secondary',
                    ),
                ];
            },
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
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $frameworks = $repository->frameworks($organizationId);
        $requirements = $repository->requirements($organizationId);
        $catalog = $repository->all($organizationId, $screenContext->scopeId);
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

        $scopeContext = $tenancy->resolveContext(
            principalId: $screenContext->principal?->id,
            requestedOrganizationId: $organizationId,
            requestedScopeId: $screenContext->scopeId,
            requestedMembershipIds: array_map(static fn ($membership): string => $membership->id, $screenContext->memberships),
        );

        return [
            'controls' => $controls,
            'selected_control' => $selectedControl,
            'frameworks' => $frameworks,
            'requirements' => $requirements,
            'can_manage_controls' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $listQuery,
            'create_route' => route('plugin.controls-catalog.store'),
            'create_framework_route' => route('plugin.controls-catalog.frameworks.store'),
            'create_requirement_route' => route('plugin.controls-catalog.requirements.store'),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'framework_options' => array_map(static fn (array $framework): array => [
                'id' => $framework['id'],
                'label' => sprintf('%s · %s', $framework['code'], $framework['name']),
            ], $frameworks),
            'requirement_options' => array_map(static fn (array $requirement): array => [
                'id' => $requirement['id'],
                'label' => sprintf('%s · %s', $requirement['code'], $requirement['title']),
            ], $requirements),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'controls_list_url' => route('core.shell.index', [...$listQuery, 'menu' => 'plugin.controls-catalog.root']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(ControlsCatalogRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $notifications = $context->app()->make(NotificationServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $rows = [];

        foreach ($repository->all($organizationId, $screenContext->scopeId) as $control) {
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

        $query['principal_id'] = $context->principal?->id ?? ($query['principal_id'] ?? 'principal-org-a');
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
