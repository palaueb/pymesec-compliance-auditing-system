<?php

namespace PymeSec\Plugins\IdentityLocal;

use PymeSec\Core\Contracts\IdentityPluginInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\UI\ScreenDefinition;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\UI\ToolbarAction;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Permissions\DatabaseAuthorizationStore;

class IdentityLocalPlugin implements IdentityPluginInterface
{
    public function identityProviderKey(): string
    {
        return 'identity-local';
    }

    public function register(PluginContext $context): void
    {
        $context->app()->singleton(IdentityLocalRepository::class, fn ($app) => new IdentityLocalRepository(
            audit: $app->make(AuditTrailInterface::class),
            events: $app->make(EventBusInterface::class),
            authorizationStore: $app->make(DatabaseAuthorizationStore::class),
        ));

        $context->app()->singleton(IdentityLocalAuthService::class, fn ($app) => new IdentityLocalAuthService(
            audit: $app->make(AuditTrailInterface::class),
            events: $app->make(EventBusInterface::class),
            users: $app->make(IdentityLocalRepository::class),
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.identity-local.users',
            owner: 'identity-local',
            titleKey: 'plugin.identity-local.screen.users.title',
            subtitleKey: 'plugin.identity-local.screen.users.subtitle',
            viewPath: $context->path('resources/views/users.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->usersData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->baseQuery($screenContext);

                return [
                    new ToolbarAction(
                        label: 'Access',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.identity-local.memberships']),
                        variant: 'secondary',
                    ),
                ];
            },
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.identity-local.memberships',
            owner: 'identity-local',
            titleKey: 'plugin.identity-local.screen.memberships.title',
            subtitleKey: 'plugin.identity-local.screen.memberships.subtitle',
            viewPath: $context->path('resources/views/memberships.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->membershipsData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                return [
                    new ToolbarAction(
                        label: 'People',
                        url: route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.identity-local.users']),
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
    private function usersData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(IdentityLocalRepository::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $query = $this->baseQuery($screenContext);
        $membershipsByPrincipal = [];
        $rows = [];

        foreach ($repository->membershipsForOrganization($organizationId) as $membership) {
            $membershipsByPrincipal[$membership['principal_id']][] = $membership;
        }

        foreach ($repository->usersForOrganization($organizationId) as $user) {
            $linkedActors = array_map(
                static fn ($actor): array => $actor->toArray(),
                $actors->actorsForPrincipal($user['principal_id'], $organizationId),
            );

            $userMemberships = $membershipsByPrincipal[$user['principal_id']] ?? [];
            $workspaceUrl = $userMemberships !== []
                ? route('core.shell.index', array_filter([
                    ...$query,
                    'principal_id' => $user['principal_id'],
                    'organization_id' => $organizationId,
                    'membership_ids' => array_map(static fn (array $membership): string => $membership['id'], $userMemberships),
                ]))
                : null;

            $rows[] = [
                'user' => $user,
                'memberships' => $userMemberships,
                'linked_actors' => $linkedActors,
                'workspace_url' => $workspaceUrl,
            ];
        }

        return [
            'rows' => $rows,
            'query' => $query,
            'organization_id' => $organizationId,
            'create_route' => route('plugin.identity-local.users.store'),
            'owner_actor_options' => $this->actorOptions($actors, $organizationId, $screenContext->scopeId),
            'can_manage_users' => $this->can($authorization, $screenContext, 'plugin.identity-local.users.manage', $organizationId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipsData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(IdentityLocalRepository::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $store = $context->app()->make(DatabaseAuthorizationStore::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $usersByPrincipal = [];
        $rows = [];

        foreach ($repository->usersForOrganization($organizationId) as $user) {
            $usersByPrincipal[$user['principal_id']] = $user;
        }

        foreach ($repository->membershipsForOrganization($organizationId) as $membership) {
            $rows[] = [
                'membership' => $membership,
                'user' => $usersByPrincipal[$membership['principal_id']] ?? null,
            ];
        }

        $scopeContext = $tenancy->resolveContext(
            principalId: $screenContext->principal?->id,
            requestedOrganizationId: $organizationId,
            requestedScopeId: $screenContext->scopeId,
            requestedMembershipIds: array_map(static fn ($membership): string => $membership->id, $screenContext->memberships),
        );

        return [
            'rows' => $rows,
            'query' => $this->baseQuery($screenContext),
            'organization_id' => $organizationId,
            'create_route' => route('plugin.identity-local.memberships.store'),
            'user_options' => array_map(static fn (array $user): array => [
                'principal_id' => $user['principal_id'],
                'label' => sprintf('%s (%s)', $user['display_name'], $user['email']),
            ], array_values($usersByPrincipal)),
            'role_options' => $this->roleOptions($store),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'can_manage_memberships' => $this->can($authorization, $screenContext, 'plugin.identity-local.memberships.manage', $organizationId),
        ];
    }

    /**
     * @return array<string, mixed>
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
     * @return array<int, array<string, string>>
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
     * @return array<int, array<string, string>>
     */
    private function roleOptions(DatabaseAuthorizationStore $store): array
    {
        $options = [];

        foreach ($store->roleDefinitions() as $role) {
            if ($role->permissions === []) {
                continue;
            }

            if (! collect($role->permissions)->every(static fn (string $permission): bool => str_starts_with($permission, 'plugin.'))) {
                continue;
            }

            $options[] = [
                'key' => $role->key,
                'label' => $role->label,
            ];
        }

        usort($options, static fn (array $left, array $right): int => strcmp($left['label'], $right['label']));

        return $options;
    }

    private function can(
        AuthorizationServiceInterface $authorization,
        ScreenRenderContext $screenContext,
        string $permission,
        string $organizationId,
    ): bool {
        return $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: $permission,
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
        ))->allowed();
    }
}
