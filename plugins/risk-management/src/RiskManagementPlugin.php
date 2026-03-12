<?php

namespace PymeSec\Plugins\RiskManagement;

use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Core\UI\ScreenDefinition;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\UI\ToolbarAction;
use PymeSec\Core\Workflows\Contracts\WorkflowRegistryInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowDefinition;
use PymeSec\Core\Workflows\WorkflowTransitionDefinition;

class RiskManagementPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(RiskRepository::class, fn () => new RiskRepository());

        $context->app()->make(WorkflowRegistryInterface::class)->register(new WorkflowDefinition(
            key: 'plugin.risk-management.risk-lifecycle',
            owner: 'risk-management',
            label: 'Risk lifecycle',
            initialState: 'identified',
            states: ['identified', 'assessing', 'treated', 'accepted'],
            transitions: [
                new WorkflowTransitionDefinition(
                    key: 'start-assessment',
                    fromStates: ['identified', 'accepted'],
                    toState: 'assessing',
                    permission: 'plugin.risk-management.risks.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'submit-treatment',
                    fromStates: ['assessing'],
                    toState: 'treated',
                    permission: 'plugin.risk-management.risks.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'accept',
                    fromStates: ['treated'],
                    toState: 'accepted',
                    permission: 'plugin.risk-management.risks.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'reopen',
                    fromStates: ['accepted'],
                    toState: 'identified',
                    permission: 'plugin.risk-management.risks.manage',
                ),
            ],
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.risk-management.root',
            owner: 'risk-management',
            titleKey: 'plugin.risk-management.screen.register.title',
            subtitleKey: 'plugin.risk-management.screen.register.subtitle',
            viewPath: $context->path('resources/views/register.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->registerData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->baseQuery($screenContext);

                return [
                    new ToolbarAction(
                        label: 'Risk board',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.risk-management.board']),
                        variant: 'secondary',
                    ),
                    new ToolbarAction(
                        label: 'Plugin route',
                        url: route('plugin.risk-management.index', $query),
                        variant: 'primary',
                        target: '_self',
                    ),
                ];
            },
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.risk-management.board',
            owner: 'risk-management',
            titleKey: 'plugin.risk-management.screen.board.title',
            subtitleKey: 'plugin.risk-management.screen.board.subtitle',
            viewPath: $context->path('resources/views/board.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->boardData($context, $screenContext),
            toolbarResolver: fn (ScreenRenderContext $screenContext): array => [
                new ToolbarAction(
                    label: 'Risk register',
                    url: route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.risk-management.root']),
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
    private function registerData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(RiskRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManage = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.risk-management.risks.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
        ))->allowed();
        $risks = [];

        foreach ($repository->all($organizationId, $screenContext->scopeId) as $risk) {
            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.risk-management.risk-lifecycle',
                subjectType: 'risk',
                subjectId: $risk['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $risks[] = [
                ...$risk,
                'owner_assignment' => $this->ownerAssignment($actors, $risk['id'], $organizationId, $screenContext->scopeId),
                'artifacts' => array_map(
                    static fn ($artifact): array => $artifact->toArray(),
                    $artifacts->forSubject('risk', $risk['id'], $organizationId, $screenContext->scopeId, 5),
                ),
                'state' => $instance->currentState,
                'transitions' => $canManage ? $this->transitionsForState($instance->currentState) : [],
                'transition_route' => route('plugin.risk-management.transition', ['riskId' => $risk['id'], 'transitionKey' => '__TRANSITION__']),
                'artifact_upload_route' => route('plugin.risk-management.artifacts.store', ['riskId' => $risk['id']]),
            ];
        }

        return [
            'risks' => $risks,
            'can_manage_risks' => $canManage,
            'query' => $this->baseQuery($screenContext),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boardData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(RiskRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $rows = [];

        foreach ($repository->all($organizationId, $screenContext->scopeId) as $risk) {
            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.risk-management.risk-lifecycle',
                subjectType: 'risk',
                subjectId: $risk['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $rows[] = [
                'risk' => $risk,
                'instance' => $instance,
                'artifacts' => array_map(
                    static fn ($artifact): array => $artifact->toArray(),
                    $artifacts->forSubject('risk', $risk['id'], $organizationId, $screenContext->scopeId, 10),
                ),
                'history' => $workflow->history('plugin.risk-management.risk-lifecycle', 'risk', $risk['id']),
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
            'identified' => ['start-assessment'],
            'assessing' => ['submit-treatment'],
            'treated' => ['accept'],
            'accepted' => ['reopen'],
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
        string $riskId,
        string $organizationId,
        ?string $scopeId,
    ): ?array {
        foreach ($actors->assignmentsFor('risk', $riskId, $organizationId, $scopeId) as $assignment) {
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
}
