<?php

namespace PymeSec\Plugins\RiskManagement;

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
                $query = $this->baseQuery($screenContext, false);

                if (is_string($screenContext->query['risk_id'] ?? null) && ($screenContext->query['risk_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to risks',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.risk-management.root']),
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Risk board',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.risk-management.board']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'Add risk',
                        url: '#risk-editor',
                        variant: 'primary',
                    ),
                    new ToolbarAction(
                        label: 'Risk board',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.risk-management.board']),
                        variant: 'secondary',
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
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManage = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.risk-management.risks.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
        ))->allowed();
        $baseQuery = $this->baseQuery($screenContext, false);
        $assetOptions = $this->linkedOptions('assets', 'id', 'name', $organizationId, $screenContext->scopeId);
        $assetLabels = [];
        $controlOptions = $this->linkedOptions('controls', 'id', 'name', $organizationId, $screenContext->scopeId);
        $controlLabels = [];

        foreach ($assetOptions as $option) {
            $assetLabels[$option['id']] = $option['label'];
        }

        foreach ($controlOptions as $option) {
            $controlLabels[$option['id']] = $option['label'];
        }

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
                'update_route' => route('plugin.risk-management.update', ['riskId' => $risk['id']]),
                'linked_asset_label' => $assetLabels[$risk['linked_asset_id']] ?? null,
                'linked_control_label' => $controlLabels[$risk['linked_control_id']] ?? null,
                'history' => $workflow->history('plugin.risk-management.risk-lifecycle', 'risk', $risk['id']),
                'linked_asset_url' => $risk['linked_asset_id'] !== ''
                    ? route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.asset-catalog.root'])
                    : null,
                'linked_control_url' => $risk['linked_control_id'] !== ''
                    ? route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.controls-catalog.root'])
                    : null,
                'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.risk-management.root', 'risk_id' => $risk['id']]),
            ];
        }

        $selectedRiskId = is_string($screenContext->query['risk_id'] ?? null) && $screenContext->query['risk_id'] !== ''
            ? (string) $screenContext->query['risk_id']
            : null;
        $selectedRisk = null;

        if (is_string($selectedRiskId)) {
            foreach ($risks as $risk) {
                if ($risk['id'] === $selectedRiskId) {
                    $selectedRisk = $risk;
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
            'risks' => $risks,
            'selected_risk' => $selectedRisk,
            'can_manage_risks' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $baseQuery,
            'create_route' => route('plugin.risk-management.store'),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'asset_options' => $assetOptions,
            'control_options' => $controlOptions,
            'risks_list_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.risk-management.root']),
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
    private function baseQuery(ScreenRenderContext $context, bool $includeSelection = true): array
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

        if (! $includeSelection) {
            unset($query['risk_id']);
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
