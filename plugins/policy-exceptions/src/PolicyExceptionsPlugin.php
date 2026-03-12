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
                $query = $this->baseQuery($screenContext);

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
            toolbarResolver: fn (ScreenRenderContext $screenContext): array => [
                new ToolbarAction(
                    label: 'Policies register',
                    url: route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.policy-exceptions.root']),
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
        $repository = $context->app()->make(PolicyExceptionsRepository::class);
        $artifacts = $context->app()->make(ArtifactServiceInterface::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManage = $this->canManage($authorization, $screenContext, $organizationId);
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

            $policies[] = [
                ...$policy,
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
                'exception_count' => count($exceptions),
                'active_exception_count' => count(array_filter($exceptions, function (array $exception) use ($workflow, $organizationId, $screenContext): bool {
                    $instance = $workflow->instanceFor(
                        workflowKey: 'plugin.policy-exceptions.exception-lifecycle',
                        subjectType: 'policy-exception',
                        subjectId: $exception['id'],
                        organizationId: $organizationId,
                        scopeId: $screenContext->scopeId,
                    );

                    return $instance->currentState === 'approved';
                })),
            ];
        }

        $scopeContext = $tenancy->resolveContext(
            principalId: $screenContext->principal?->id,
            requestedOrganizationId: $organizationId,
            requestedScopeId: $screenContext->scopeId,
            requestedMembershipIds: array_map(static fn ($membership): string => $membership->id, $screenContext->memberships),
        );

        return [
            'policies' => $policies,
            'can_manage_policies' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'create_route' => route('plugin.policy-exceptions.store'),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'control_options' => $this->linkedOptions('controls', 'id', 'name', $organizationId, $screenContext->scopeId),
            'finding_options' => $this->linkedOptions('findings', 'id', 'title', $organizationId, $screenContext->scopeId),
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
            ];
        }

        $scopeContext = $tenancy->resolveContext(
            principalId: $screenContext->principal?->id,
            requestedOrganizationId: $organizationId,
            requestedScopeId: $screenContext->scopeId,
            requestedMembershipIds: array_map(static fn ($membership): string => $membership->id, $screenContext->memberships),
        );

        return [
            'exceptions' => $exceptions,
            'can_manage_policies' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'finding_options' => $this->linkedOptions('findings', 'id', 'title', $organizationId, $screenContext->scopeId),
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
