<?php

namespace PymeSec\Plugins\FindingsRemediation;

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

class FindingsRemediationPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(FindingsRemediationRepository::class, fn () => new FindingsRemediationRepository());

        $context->app()->make(WorkflowRegistryInterface::class)->register(new WorkflowDefinition(
            key: 'plugin.findings-remediation.finding-lifecycle',
            owner: 'findings-remediation',
            label: 'Finding lifecycle',
            initialState: 'open',
            states: ['open', 'triaged', 'remediating', 'resolved'],
            transitions: [
                new WorkflowTransitionDefinition(
                    key: 'triage',
                    fromStates: ['open'],
                    toState: 'triaged',
                    permission: 'plugin.findings-remediation.findings.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'start-remediation',
                    fromStates: ['triaged'],
                    toState: 'remediating',
                    permission: 'plugin.findings-remediation.findings.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'resolve',
                    fromStates: ['remediating'],
                    toState: 'resolved',
                    permission: 'plugin.findings-remediation.findings.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'reopen',
                    fromStates: ['resolved'],
                    toState: 'triaged',
                    permission: 'plugin.findings-remediation.findings.manage',
                ),
            ],
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.findings-remediation.root',
            owner: 'findings-remediation',
            titleKey: 'plugin.findings-remediation.screen.register.title',
            subtitleKey: 'plugin.findings-remediation.screen.register.subtitle',
            viewPath: $context->path('resources/views/register.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->registerData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->baseQuery($screenContext, false);

                if (is_string($screenContext->query['finding_id'] ?? null) && ($screenContext->query['finding_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to findings',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.findings-remediation.root']),
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Remediation board',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.findings-remediation.board']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'Add finding',
                        url: '#finding-editor',
                        variant: 'primary',
                    ),
                    new ToolbarAction(
                        label: 'Remediation board',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.findings-remediation.board']),
                        variant: 'secondary',
                    ),
                ];
            },
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.findings-remediation.board',
            owner: 'findings-remediation',
            titleKey: 'plugin.findings-remediation.screen.board.title',
            subtitleKey: 'plugin.findings-remediation.screen.board.subtitle',
            viewPath: $context->path('resources/views/board.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->boardData($context, $screenContext),
            toolbarResolver: fn (ScreenRenderContext $screenContext): array => [
                new ToolbarAction(
                    label: 'Findings register',
                    url: route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.findings-remediation.root']),
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
        $repository = $context->app()->make(FindingsRemediationRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManage = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.findings-remediation.findings.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
        ))->allowed();
        $baseQuery = $this->baseQuery($screenContext, false);
        $controlOptions = $this->linkedOptions('controls', 'id', 'name', $organizationId, $screenContext->scopeId);
        $controlLabels = [];
        $riskOptions = $this->linkedOptions('risks', 'id', 'title', $organizationId, $screenContext->scopeId);
        $riskLabels = [];

        foreach ($controlOptions as $option) {
            $controlLabels[$option['id']] = $option['label'];
        }

        foreach ($riskOptions as $option) {
            $riskLabels[$option['id']] = $option['label'];
        }

        $findings = [];

        foreach ($repository->allFindings($organizationId, $screenContext->scopeId) as $finding) {
            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.findings-remediation.finding-lifecycle',
                subjectType: 'finding',
                subjectId: $finding['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $actions = $repository->actionsForFinding($finding['id']);
            $findingActions = [];

            foreach ($actions as $action) {
                $findingActions[] = [
                    ...$action,
                    'owner_assignment' => $this->ownerAssignment($actors, 'remediation-action', $action['id'], $organizationId, $screenContext->scopeId),
                    'update_route' => route('plugin.findings-remediation.actions.update', ['actionId' => $action['id']]),
                ];
            }

            $findings[] = [
                ...$finding,
                'actions' => $findingActions,
                'owner_assignment' => $this->ownerAssignment($actors, 'finding', $finding['id'], $organizationId, $screenContext->scopeId),
                'artifacts' => array_map(
                    static fn ($artifact): array => $artifact->toArray(),
                    $artifacts->forSubject('finding', $finding['id'], $organizationId, $screenContext->scopeId, 5),
                ),
                'state' => $instance->currentState,
                'transitions' => $canManage ? $this->transitionsForState($instance->currentState) : [],
                'transition_route' => route('plugin.findings-remediation.transition', ['findingId' => $finding['id'], 'transitionKey' => '__TRANSITION__']),
                'artifact_upload_route' => route('plugin.findings-remediation.artifacts.store', ['findingId' => $finding['id']]),
                'update_route' => route('plugin.findings-remediation.update', ['findingId' => $finding['id']]),
                'action_store_route' => route('plugin.findings-remediation.actions.store', ['findingId' => $finding['id']]),
                'linked_control_label' => $controlLabels[$finding['linked_control_id']] ?? null,
                'linked_risk_label' => $riskLabels[$finding['linked_risk_id']] ?? null,
                'linked_control_url' => $finding['linked_control_id'] !== ''
                    ? route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.controls-catalog.root'])
                    : null,
                'linked_risk_url' => $finding['linked_risk_id'] !== ''
                    ? route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.risk-management.root', 'risk_id' => $finding['linked_risk_id']])
                    : null,
                'history' => $workflow->history('plugin.findings-remediation.finding-lifecycle', 'finding', $finding['id']),
                'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.findings-remediation.root', 'finding_id' => $finding['id']]),
                'action_count' => count($actions),
                'open_action_count' => count(array_filter($actions, static fn (array $action): bool => $action['status'] !== 'done')),
            ];
        }

        $selectedFindingId = is_string($screenContext->query['finding_id'] ?? null) && $screenContext->query['finding_id'] !== ''
            ? (string) $screenContext->query['finding_id']
            : null;
        $selectedFinding = null;

        if (is_string($selectedFindingId)) {
            foreach ($findings as $finding) {
                if ($finding['id'] === $selectedFindingId) {
                    $selectedFinding = $finding;
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
            'findings' => $findings,
            'selected_finding' => $selectedFinding,
            'can_manage_findings' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $baseQuery,
            'create_route' => route('plugin.findings-remediation.store'),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'control_options' => $controlOptions,
            'risk_options' => $riskOptions,
            'findings_list_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.findings-remediation.root']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boardData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(FindingsRemediationRepository::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManage = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.findings-remediation.findings.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
        ))->allowed();

        $actions = [];

        foreach ($repository->actions($organizationId, $screenContext->scopeId) as $action) {
            $finding = $repository->findFinding($action['finding_id']);

            if ($finding === null) {
                continue;
            }

            $actions[] = [
                ...$action,
                'finding' => $finding,
                'owner_assignment' => $this->ownerAssignment($actors, 'remediation-action', $action['id'], $organizationId, $screenContext->scopeId),
                'update_route' => route('plugin.findings-remediation.actions.update', ['actionId' => $action['id']]),
            ];
        }

        return [
            'actions' => $actions,
            'can_manage_findings' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $context->app()->make(TenancyServiceInterface::class)
                ->resolveContext(
                    principalId: $screenContext->principal?->id,
                    requestedOrganizationId: $organizationId,
                    requestedScopeId: $screenContext->scopeId,
                    requestedMembershipIds: array_map(static fn ($membership): string => $membership->id, $screenContext->memberships),
                )->scopes),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function transitionsForState(string $state): array
    {
        return match ($state) {
            'open' => ['triage'],
            'triaged' => ['start-remediation'],
            'remediating' => ['resolve'],
            'resolved' => ['reopen'],
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
            unset($query['finding_id']);
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
