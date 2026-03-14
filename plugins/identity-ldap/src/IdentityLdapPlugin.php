<?php

namespace PymeSec\Plugins\IdentityLdap;

use PymeSec\Core\Contracts\IdentityPluginInterface;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Permissions\DatabaseAuthorizationStore;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\UI\ScreenDefinition;
use PymeSec\Core\UI\ScreenRenderContext;

class IdentityLdapPlugin implements IdentityPluginInterface
{
    public function identityProviderKey(): string
    {
        return 'identity-ldap';
    }

    public function register(PluginContext $context): void
    {
        $context->app()->singleton(LdapDirectoryGatewayInterface::class, fn () => new PhpLdapDirectoryGateway());

        $context->app()->singleton(IdentityLdapRepository::class, fn ($app) => new IdentityLdapRepository(
            audit: $app->make(\PymeSec\Core\Audit\Contracts\AuditTrailInterface::class),
            events: $app->make(\PymeSec\Core\Events\Contracts\EventBusInterface::class),
        ));

        $context->app()->singleton(IdentityLdapService::class, fn ($app) => new IdentityLdapService(
            repository: $app->make(IdentityLdapRepository::class),
            users: $app->make(\PymeSec\Plugins\IdentityLocal\IdentityLocalRepository::class),
            gateway: $app->make(LdapDirectoryGatewayInterface::class),
            audit: $app->make(\PymeSec\Core\Audit\Contracts\AuditTrailInterface::class),
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.identity-ldap.directory',
            owner: 'identity-ldap',
            titleKey: 'plugin.identity-ldap.screen.directory.title',
            subtitleKey: 'plugin.identity-ldap.screen.directory.subtitle',
            viewPath: $context->path('resources/views/directory.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->directoryData($context, $screenContext),
        ));
    }

    public function boot(PluginContext $context): void
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    private function directoryData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(IdentityLdapRepository::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $store = $context->app()->make(DatabaseAuthorizationStore::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $connection = $repository->connectionForOrganization($organizationId);
        $query = $this->baseQuery($screenContext);
        $scopeContext = $tenancy->resolveContext(
            principalId: $screenContext->principal?->id,
            requestedOrganizationId: $organizationId,
            requestedScopeId: $screenContext->scopeId,
            requestedMembershipIds: array_map(static fn ($membership): string => $membership->id, $screenContext->memberships),
        );

        $roleOptions = [];

        foreach ($store->roleDefinitions() as $role) {
            if ($role->permissions === []) {
                continue;
            }

            $roleOptions[] = [
                'key' => $role->key,
                'label' => $role->label,
            ];
        }

        usort($roleOptions, static fn (array $left, array $right): int => strcmp($left['label'], $right['label']));

        return [
            'query' => $query,
            'organization_id' => $organizationId,
            'connection' => $connection,
            'mappings' => $connection !== null ? $repository->mappingsForConnection((string) $connection['id']) : [],
            'cached_users' => $repository->cachedUsersForOrganization($organizationId),
            'role_options' => $roleOptions,
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'save_connection_route' => route('plugin.identity-ldap.connection.store'),
            'save_mapping_route' => route('plugin.identity-ldap.mappings.store'),
            'sync_route' => route('plugin.identity-ldap.sync.store'),
            'can_manage_directory' => $this->can($authorization, $screenContext, 'plugin.identity-ldap.directory.manage', $organizationId),
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
