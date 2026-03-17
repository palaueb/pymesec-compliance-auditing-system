<?php

namespace PymeSec\Plugins\PolicyExceptions;

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

class PolicyExceptionsPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(PolicyExceptionsRepository::class, fn () => new PolicyExceptionsRepository());

        $context->app()->make(WorkflowRegistryInterface::class)->register(new WorkflowDefinition(
            key: 'plugin.policy-exceptions.policy-lifecycle',
            owner: 'policy-exceptions',
            label: 'Policy lifecycle',
            initialState: 'draft',
            states: ['draft', 'review', 'active', 'retired'],
            transitions: [
                new WorkflowTransitionDefinition(
                    key: 'submit-review',
                    fromStates: ['draft', 'active'],
                    toState: 'review',
                    permission: 'plugin.policy-exceptions.policies.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'activate',
                    fromStates: ['review'],
                    toState: 'active',
                    permission: 'plugin.policy-exceptions.policies.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'send-back',
                    fromStates: ['review'],
                    toState: 'draft',
                    permission: 'plugin.policy-exceptions.policies.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'retire',
                    fromStates: ['active'],
                    toState: 'retired',
                    permission: 'plugin.policy-exceptions.policies.manage',
                ),
            ],
        ));

        $context->app()->make(WorkflowRegistryInterface::class)->register(new WorkflowDefinition(
            key: 'plugin.policy-exceptions.exception-lifecycle',
            owner: 'policy-exceptions',
            label: 'Policy exception lifecycle',
            initialState: 'requested',
            states: ['requested', 'approved', 'expired', 'revoked'],
            transitions: [
                new WorkflowTransitionDefinition(
                    key: 'approve',
                    fromStates: ['requested'],
                    toState: 'approved',
                    permission: 'plugin.policy-exceptions.policies.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'expire',
                    fromStates: ['approved'],
                    toState: 'expired',
                    permission: 'plugin.policy-exceptions.policies.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'revoke',
                    fromStates: ['requested', 'approved'],
                    toState: 'revoked',
                    permission: 'plugin.policy-exceptions.policies.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'resubmit',
                    fromStates: ['expired', 'revoked'],
                    toState: 'requested',
                    permission: 'plugin.policy-exceptions.policies.manage',
                ),
            ],
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.policy-exceptions.root',
            owner: 'policy-exceptions',
            titleKey: 'plugin.policy-exceptions.screen.register.title',
            subtitleKey: 'plugin.policy-exceptions.screen.register.subtitle',
            viewPath: $context->path('resources/views/register.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->registerData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->baseQuery($screenContext, false);

                if (is_string($screenContext->query['policy_id'] ?? null) && ($screenContext->query['policy_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to policies',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.policy-exceptions.root']),
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Exceptions board',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.policy-exceptions.exceptions']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'Add policy',
                        url: '#policy-editor',
                        variant: 'primary',
                    ),
                    new ToolbarAction(
                        label: 'Exceptions board',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.policy-exceptions.exceptions']),
                        variant: 'secondary',
                    ),
                ];
            },
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.policy-exceptions.exceptions',
            owner: 'policy-exceptions',
            titleKey: 'plugin.policy-exceptions.screen.exceptions.title',
            subtitleKey: 'plugin.policy-exceptions.screen.exceptions.subtitle',
            viewPath: $context->path('resources/views/exceptions.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->exceptionsData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->baseQuery($screenContext, false);

                if (is_string($screenContext->query['exception_id'] ?? null) && ($screenContext->query['exception_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to exceptions',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.policy-exceptions.exceptions']),
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Policies register',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.policy-exceptions.root']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'Policies register',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.policy-exceptions.root']),
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
        $repository = $context->app()->make(PolicyExceptionsRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManage = $this->canManage($authorization, $screenContext, $organizationId);
        $baseQuery = $this->baseQuery($screenContext, false);
        $controlOptions = $this->linkedOptions('controls', 'id', 'name', $organizationId, $screenContext->scopeId);
        $controlLabels = [];
        $findingOptions = $this->linkedOptions('findings', 'id', 'title', $organizationId, $screenContext->scopeId);
        $findingLabels = [];

        foreach ($controlOptions as $option) {
            $controlLabels[$option['id']] = $option['label'];
        }

        foreach ($findingOptions as $option) {
            $findingLabels[$option['id']] = $option['label'];
        }

        $policies = [];

        foreach ($repository->allPolicies($organizationId, $screenContext->scopeId) as $policy) {
            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.policy-exceptions.policy-lifecycle',
                subjectType: 'policy',
                subjectId: $policy['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $exceptions = $repository->exceptionsForPolicy($policy['id']);
            $policyExceptions = [];
            $activeExceptionCount = 0;

            foreach ($exceptions as $exception) {
                $exceptionInstance = $workflow->instanceFor(
                    workflowKey: 'plugin.policy-exceptions.exception-lifecycle',
                    subjectType: 'policy-exception',
                    subjectId: $exception['id'],
                    organizationId: $organizationId,
                    scopeId: $screenContext->scopeId,
                );

                if ($exceptionInstance->currentState === 'approved') {
                    $activeExceptionCount++;
                }

                $policyExceptions[] = [
                    ...$exception,
                    'state' => $exceptionInstance->currentState,
                    'owner_assignment' => $this->ownerAssignment($actors, 'policy-exception', $exception['id'], $organizationId, $screenContext->scopeId),
                    'linked_finding_label' => $findingLabels[$exception['linked_finding_id']] ?? null,
                    'linked_finding_url' => $exception['linked_finding_id'] !== ''
                        ? route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.findings-remediation.root', 'finding_id' => $exception['linked_finding_id']])
                        : null,
                    'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.policy-exceptions.exceptions', 'exception_id' => $exception['id']]),
                ];
            }

            $policies[] = [
                ...$policy,
                'exceptions' => $policyExceptions,
                'owner_assignment' => $this->ownerAssignment($actors, 'policy', $policy['id'], $organizationId, $screenContext->scopeId),
                'artifacts' => array_map(
                    static fn ($artifact): array => $artifact->toArray(),
                    $artifacts->forSubject('policy', $policy['id'], $organizationId, $screenContext->scopeId, 5),
                ),
                'state' => $instance->currentState,
                'transitions' => $canManage ? $this->policyTransitionsForState($instance->currentState) : [],
                'transition_route' => route('plugin.policy-exceptions.transition', ['policyId' => $policy['id'], 'transitionKey' => '__TRANSITION__']),
                'artifact_upload_route' => route('plugin.policy-exceptions.artifacts.store', ['policyId' => $policy['id']]),
                'update_route' => route('plugin.policy-exceptions.update', ['policyId' => $policy['id']]),
                'exception_store_route' => route('plugin.policy-exceptions.exceptions.store', ['policyId' => $policy['id']]),
                'linked_control_label' => $controlLabels[$policy['linked_control_id']] ?? null,
                'linked_control_url' => $policy['linked_control_id'] !== ''
                    ? route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.controls-catalog.root'])
                    : null,
                'history' => $workflow->history('plugin.policy-exceptions.policy-lifecycle', 'policy', $policy['id']),
                'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.policy-exceptions.root', 'policy_id' => $policy['id']]),
                'exception_count' => count($exceptions),
                'active_exception_count' => $activeExceptionCount,
            ];
        }

        $selectedPolicyId = is_string($screenContext->query['policy_id'] ?? null) && ($screenContext->query['policy_id'] ?? '') !== ''
            ? (string) $screenContext->query['policy_id']
            : null;
        $selectedPolicy = null;

        if (is_string($selectedPolicyId)) {
            foreach ($policies as $policy) {
                if ($policy['id'] === $selectedPolicyId) {
                    $selectedPolicy = $policy;
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
            'policies' => $policies,
            'selected_policy' => $selectedPolicy,
            'can_manage_policies' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $baseQuery,
            'create_route' => route('plugin.policy-exceptions.store'),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'control_options' => $controlOptions,
            'finding_options' => $findingOptions,
            'policies_list_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.policy-exceptions.root']),
            'exceptions_list_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.policy-exceptions.exceptions']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exceptionsData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(PolicyExceptionsRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManage = $this->canManage($authorization, $screenContext, $organizationId);
        $baseQuery = $this->baseQuery($screenContext, false);
        $findingOptions = $this->linkedOptions('findings', 'id', 'title', $organizationId, $screenContext->scopeId);
        $findingLabels = [];

        foreach ($findingOptions as $option) {
            $findingLabels[$option['id']] = $option['label'];
        }

        $exceptions = [];

        foreach ($repository->exceptions($organizationId, $screenContext->scopeId) as $exception) {
            $policy = $repository->findPolicy($exception['policy_id']);

            if ($policy === null) {
                continue;
            }

            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.policy-exceptions.exception-lifecycle',
                subjectType: 'policy-exception',
                subjectId: $exception['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $exceptions[] = [
                ...$exception,
                'policy' => $policy,
                'owner_assignment' => $this->ownerAssignment($actors, 'policy-exception', $exception['id'], $organizationId, $screenContext->scopeId),
                'artifacts' => array_map(
                    static fn ($artifact): array => $artifact->toArray(),
                    $artifacts->forSubject('policy-exception', $exception['id'], $organizationId, $screenContext->scopeId, 5),
                ),
                'state' => $instance->currentState,
                'transitions' => $canManage ? $this->exceptionTransitionsForState($instance->currentState) : [],
                'transition_route' => route('plugin.policy-exceptions.exceptions.transition', ['exceptionId' => $exception['id'], 'transitionKey' => '__TRANSITION__']),
                'artifact_upload_route' => route('plugin.policy-exceptions.exceptions.artifacts.store', ['exceptionId' => $exception['id']]),
                'update_route' => route('plugin.policy-exceptions.exceptions.update', ['exceptionId' => $exception['id']]),
                'history' => $workflow->history('plugin.policy-exceptions.exception-lifecycle', 'policy-exception', $exception['id']),
                'linked_finding_label' => $findingLabels[$exception['linked_finding_id']] ?? null,
                'linked_finding_url' => $exception['linked_finding_id'] !== ''
                    ? route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.findings-remediation.root', 'finding_id' => $exception['linked_finding_id']])
                    : null,
                'policy_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.policy-exceptions.root', 'policy_id' => $policy['id']]),
                'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.policy-exceptions.exceptions', 'exception_id' => $exception['id']]),
            ];
        }

        $selectedExceptionId = is_string($screenContext->query['exception_id'] ?? null) && ($screenContext->query['exception_id'] ?? '') !== ''
            ? (string) $screenContext->query['exception_id']
            : null;
        $selectedException = null;

        if (is_string($selectedExceptionId)) {
            foreach ($exceptions as $exception) {
                if ($exception['id'] === $selectedExceptionId) {
                    $selectedException = $exception;
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
            'exceptions' => $exceptions,
            'selected_exception' => $selectedException,
            'can_manage_policies' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $baseQuery,
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'finding_options' => $findingOptions,
            'exceptions_list_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.policy-exceptions.exceptions']),
        ];
    }

    private function canManage(
        AuthorizationServiceInterface $authorization,
        ScreenRenderContext $screenContext,
        string $organizationId,
    ): bool {
        return $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.policy-exceptions.policies.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
        ))->allowed();
    }

    /**
     * @return array<int, string>
     */
    private function policyTransitionsForState(string $state): array
    {
        return match ($state) {
            'draft' => ['submit-review'],
            'review' => ['activate', 'send-back'],
            'active' => ['submit-review', 'retire'],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function exceptionTransitionsForState(string $state): array
    {
        return match ($state) {
            'requested' => ['approve', 'revoke'],
            'approved' => ['expire', 'revoke'],
            'expired', 'revoked' => ['resubmit'],
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
            unset($query['policy_id'], $query['exception_id']);
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
