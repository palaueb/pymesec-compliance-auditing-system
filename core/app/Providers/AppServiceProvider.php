<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Artifacts\DatabaseArtifactService;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Audit\DatabaseAuditTrail;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\DatabaseEventBus;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\FunctionalActors\DatabaseFunctionalActorService;
use PymeSec\Core\Menus\Contracts\MenuRegistryInterface;
use PymeSec\Core\Menus\MenuDefinition;
use PymeSec\Core\Menus\MenuLabelResolver;
use PymeSec\Core\Menus\MenuRegistry;
use PymeSec\Core\Notifications\Contracts\NotificationServiceInterface;
use PymeSec\Core\Notifications\DatabaseNotificationService;
use PymeSec\Core\Permissions\AuthorizationService;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;
use PymeSec\Core\Permissions\PermissionGrant;
use PymeSec\Core\Permissions\PermissionDefinition;
use PymeSec\Core\Permissions\PermissionRegistry;
use PymeSec\Core\Permissions\RoleDefinition;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\Tenancy\DatabaseTenancyService;
use PymeSec\Core\UI\Contracts\ScreenRegistryInterface;
use PymeSec\Core\UI\ScreenRegistry;
use PymeSec\Core\Workflows\Contracts\WorkflowRegistryInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\DatabaseWorkflowService;
use PymeSec\Core\Workflows\WorkflowRegistry;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PermissionRegistryInterface::class, function (): PermissionRegistryInterface {
            return new PermissionRegistry();
        });

        $this->app->singleton(AuditTrailInterface::class, function (): AuditTrailInterface {
            return new DatabaseAuditTrail();
        });

        $this->app->singleton(EventBusInterface::class, function (): EventBusInterface {
            return new DatabaseEventBus();
        });

        $this->app->singleton(TenancyServiceInterface::class, function (): TenancyServiceInterface {
            return new DatabaseTenancyService(
                audit: $this->app->make(AuditTrailInterface::class),
                events: $this->app->make(EventBusInterface::class),
            );
        });

        $this->app->singleton(FunctionalActorServiceInterface::class, function (): FunctionalActorServiceInterface {
            return new DatabaseFunctionalActorService(
                audit: $this->app->make(AuditTrailInterface::class),
                events: $this->app->make(EventBusInterface::class),
            );
        });

        $this->app->singleton(NotificationServiceInterface::class, function (): NotificationServiceInterface {
            return new DatabaseNotificationService(
                audit: $this->app->make(AuditTrailInterface::class),
                events: $this->app->make(EventBusInterface::class),
            );
        });

        $this->app->singleton(ArtifactServiceInterface::class, function (): ArtifactServiceInterface {
            return new DatabaseArtifactService(
                audit: $this->app->make(AuditTrailInterface::class),
                events: $this->app->make(EventBusInterface::class),
            );
        });

        $this->app->singleton(MenuRegistryInterface::class, function ($app): MenuRegistryInterface {
            return new MenuRegistry(
                permissions: $app->make(PermissionRegistryInterface::class),
                authorization: $app->make(AuthorizationServiceInterface::class),
                url: $app['url'],
            );
        });

        $this->app->singleton(MenuLabelResolver::class, function ($app): MenuLabelResolver {
            return new MenuLabelResolver(
                plugins: $app->make(\PymeSec\Core\Plugins\Contracts\PluginManagerInterface::class),
            );
        });

        $this->app->singleton(ScreenRegistryInterface::class, function ($app): ScreenRegistryInterface {
            return new ScreenRegistry(
                views: $app['view'],
                labels: $app->make(MenuLabelResolver::class),
            );
        });

        $this->app->singleton(WorkflowRegistryInterface::class, function (): WorkflowRegistryInterface {
            return new WorkflowRegistry();
        });

        $this->app->singleton(WorkflowServiceInterface::class, function ($app): WorkflowServiceInterface {
            return new DatabaseWorkflowService(
                registry: $app->make(WorkflowRegistryInterface::class),
                authorization: $app->make(AuthorizationServiceInterface::class),
                audit: $app->make(AuditTrailInterface::class),
                events: $app->make(EventBusInterface::class),
            );
        });

        $this->app->singleton(AuthorizationServiceInterface::class, function ($app): AuthorizationServiceInterface {
            $roles = [];

            foreach (config('authorization.roles', []) as $key => $role) {
                if (! is_string($key) || ! is_array($role)) {
                    continue;
                }

                $roles[$key] = new RoleDefinition(
                    key: $key,
                    label: (string) ($role['label'] ?? $key),
                    permissions: array_values(array_filter(
                        $role['permissions'] ?? [],
                        static fn (mixed $permission): bool => is_string($permission) && $permission !== '',
                    )),
                );
            }

            $grants = [];

            foreach (config('authorization.grants', []) as $grant) {
                if (! is_array($grant)) {
                    continue;
                }

                $targetType = $grant['target_type'] ?? null;
                $targetId = $grant['target_id'] ?? null;
                $grantType = $grant['grant_type'] ?? null;
                $value = $grant['value'] ?? null;
                $contextType = $grant['context_type'] ?? null;

                if (! is_string($targetType) || ! is_string($targetId) || ! is_string($grantType) || ! is_string($value) || ! is_string($contextType)) {
                    continue;
                }

                $grants[] = new PermissionGrant(
                    targetType: $targetType,
                    targetId: $targetId,
                    grantType: $grantType,
                    value: $value,
                    contextType: $contextType,
                    organizationId: is_string($grant['organization_id'] ?? null) ? $grant['organization_id'] : null,
                    scopeId: is_string($grant['scope_id'] ?? null) ? $grant['scope_id'] : null,
                );
            }

            return new AuthorizationService(
                permissions: $app->make(PermissionRegistryInterface::class),
                roles: $roles,
                grants: $grants,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $permissions = $this->app->make(PermissionRegistryInterface::class);

        foreach ([
            new PermissionDefinition(
                key: 'core.plugins.view',
                label: 'View plugins',
                description: 'View plugin discovery, status, and compatibility information.',
                origin: 'core',
                featureArea: 'plugins',
                operation: 'view',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.plugins.manage',
                label: 'Manage plugins',
                description: 'Enable, disable, and upgrade platform plugins subject to policy.',
                origin: 'core',
                featureArea: 'plugins',
                operation: 'manage',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.permissions.view',
                label: 'View permissions',
                description: 'View the registered permission catalog.',
                origin: 'core',
                featureArea: 'permissions',
                operation: 'view',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.permissions.manage',
                label: 'Manage permissions',
                description: 'Manage permission definitions, grants, and roles.',
                origin: 'core',
                featureArea: 'permissions',
                operation: 'manage',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.menus.view',
                label: 'View menus',
                description: 'View registered shell menus and navigation contributions.',
                origin: 'core',
                featureArea: 'menus',
                operation: 'view',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.workflows.view',
                label: 'View workflows',
                description: 'View registered workflows and workflow state history.',
                origin: 'core',
                featureArea: 'workflows',
                operation: 'view',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.tenancy.view',
                label: 'View tenancy',
                description: 'View organizations, scopes, and resolved tenancy context.',
                origin: 'core',
                featureArea: 'tenancy',
                operation: 'view',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.tenancy.manage',
                label: 'Manage tenancy',
                description: 'Archive and reactivate organizations and scopes.',
                origin: 'core',
                featureArea: 'tenancy',
                operation: 'manage',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.artifacts.view',
                label: 'View artifacts',
                description: 'View stored artifacts and attachment metadata across the platform.',
                origin: 'core',
                featureArea: 'artifacts',
                operation: 'view',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.artifacts.manage',
                label: 'Manage artifacts',
                description: 'Manage shared artifact storage operations and evidence handling.',
                origin: 'core',
                featureArea: 'artifacts',
                operation: 'manage',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.audit-logs.view',
                label: 'View audit logs',
                description: 'View append-only audit records for sensitive platform and plugin operations.',
                origin: 'core',
                featureArea: 'audit',
                operation: 'view',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.audit-logs.export',
                label: 'Export audit logs',
                description: 'Export audit records in bulk for investigation or evidence handling.',
                origin: 'core',
                featureArea: 'audit',
                operation: 'export',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.functional-actors.view',
                label: 'View functional actors',
                description: 'View functional actors, linkages, and accountability assignments.',
                origin: 'core',
                featureArea: 'functional-actors',
                operation: 'view',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.functional-actors.manage',
                label: 'Manage functional actors',
                description: 'Create functional actors and manage linkages or assignments.',
                origin: 'core',
                featureArea: 'functional-actors',
                operation: 'manage',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.events.view',
                label: 'View public events',
                description: 'View published public platform and plugin events.',
                origin: 'core',
                featureArea: 'events',
                operation: 'view',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.notifications.view',
                label: 'View notifications',
                description: 'View in-app notifications and scheduled reminder state.',
                origin: 'core',
                featureArea: 'notifications',
                operation: 'view',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.notifications.manage',
                label: 'Manage notifications',
                description: 'Dispatch scheduled notifications and inspect delivery state.',
                origin: 'core',
                featureArea: 'notifications',
                operation: 'manage',
                contexts: ['platform'],
            ),
        ] as $definition) {
            $permissions->register($definition);
        }

        $menus = $this->app->make(MenuRegistryInterface::class);

        $menus->registerCore(new MenuDefinition(
            id: 'core.platform',
            owner: 'core',
            labelKey: 'core.nav.platform',
            icon: 'layout',
            order: 10,
        ));

        $menus->registerCore(new MenuDefinition(
            id: 'core.plugins',
            owner: 'core',
            labelKey: 'core.nav.plugins',
            routeName: 'core.plugins.index',
            parentId: 'core.platform',
            icon: 'plug',
            order: 10,
            permission: 'core.plugins.view',
        ));

        $menus->registerCore(new MenuDefinition(
            id: 'core.permissions',
            owner: 'core',
            labelKey: 'core.nav.permissions',
            routeName: 'core.permissions.index',
            parentId: 'core.platform',
            icon: 'shield',
            order: 20,
            permission: 'core.permissions.view',
        ));

        $menus->registerCore(new MenuDefinition(
            id: 'core.tenancy',
            owner: 'core',
            labelKey: 'core.nav.tenancy',
            routeName: 'core.tenancy.index',
            parentId: 'core.platform',
            icon: 'building',
            order: 30,
            permission: 'core.tenancy.view',
        ));

        $menus->registerCore(new MenuDefinition(
            id: 'core.audit',
            owner: 'core',
            labelKey: 'core.nav.audit',
            routeName: 'core.audit.index',
            parentId: 'core.platform',
            icon: 'journal',
            order: 40,
            permission: 'core.audit-logs.view',
        ));

        $menus->registerCore(new MenuDefinition(
            id: 'core.functional-actors',
            owner: 'core',
            labelKey: 'core.nav.functional_actors',
            routeName: 'core.functional-actors.index',
            parentId: 'core.platform',
            icon: 'users',
            order: 50,
            permission: 'core.functional-actors.view',
        ));
    }
}
