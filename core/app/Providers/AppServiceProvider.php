<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
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
use PymeSec\Core\Permissions\Contracts\AuthorizationStoreInterface;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;
use PymeSec\Core\Permissions\DatabaseAuthorizationStore;
use PymeSec\Core\Permissions\PermissionDefinition;
use PymeSec\Core\Permissions\PermissionRegistry;
use PymeSec\Core\Plugins\Contracts\PluginManagerInterface;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\Tenancy\DatabaseTenancyService;
use PymeSec\Core\UI\Contracts\ScreenRegistryInterface;
use PymeSec\Core\UI\ScreenDefinition;
use PymeSec\Core\UI\ScreenRegistry;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\UI\ToolbarAction;
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
            return new PermissionRegistry;
        });

        $this->app->singleton(AuditTrailInterface::class, function (): AuditTrailInterface {
            return new DatabaseAuditTrail;
        });

        $this->app->singleton(EventBusInterface::class, function (): EventBusInterface {
            return new DatabaseEventBus;
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

        $this->app->singleton(AuthorizationStoreInterface::class, function (): AuthorizationStoreInterface {
            return new DatabaseAuthorizationStore;
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
                plugins: $app->make(PluginManagerInterface::class),
            );
        });

        $this->app->singleton(ScreenRegistryInterface::class, function ($app): ScreenRegistryInterface {
            return new ScreenRegistry(
                views: $app['view'],
                labels: $app->make(MenuLabelResolver::class),
            );
        });

        $this->app->singleton(WorkflowRegistryInterface::class, function (): WorkflowRegistryInterface {
            return new WorkflowRegistry;
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
            return new AuthorizationService(
                permissions: $app->make(PermissionRegistryInterface::class),
                store: $app->make(AuthorizationStoreInterface::class),
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
                key: 'core.roles.view',
                label: 'View roles',
                description: 'View authorization roles and grants.',
                origin: 'core',
                featureArea: 'roles',
                operation: 'view',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.roles.manage',
                label: 'Manage roles',
                description: 'Create roles, update grants, and manage authorization assignments.',
                origin: 'core',
                featureArea: 'roles',
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
            id: 'core.roles',
            owner: 'core',
            labelKey: 'core.nav.roles',
            routeName: 'core.roles.index',
            parentId: 'core.platform',
            icon: 'key',
            order: 25,
            permission: 'core.roles.view',
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

        $this->app->make(ScreenRegistryInterface::class)->register(new ScreenDefinition(
            menuId: 'core.roles',
            owner: 'core',
            titleKey: 'core.roles.screen.title',
            subtitleKey: 'core.roles.screen.subtitle',
            viewPath: resource_path('views/roles.blade.php'),
            dataResolver: function (ScreenRenderContext $screenContext): array {
                $store = $this->app->make(AuthorizationStoreInterface::class);
                $permissions = $this->app->make(PermissionRegistryInterface::class);

                $query = $screenContext->query;
                $query['principal_id'] = $screenContext->principal?->id ?? ($query['principal_id'] ?? 'principal-admin');
                $query['locale'] = $screenContext->locale;

                $principalOptions = ['principal-admin'];

                foreach (DB::table('memberships')->select('principal_id')->distinct()->pluck('principal_id') as $principalId) {
                    if (is_string($principalId) && $principalId !== '' && ! in_array($principalId, $principalOptions, true)) {
                        $principalOptions[] = $principalId;
                    }
                }

                return [
                    'roles' => $store->roleRecords(),
                    'grants' => $store->grantRecords(),
                    'query' => $query,
                    'role_store_route' => route('core.roles.store'),
                    'grant_store_route' => route('core.grants.store'),
                    'permission_options' => array_map(static fn ($permission): array => [
                        'key' => $permission->key,
                        'label' => $permission->label,
                    ], $permissions->all()),
                    'principal_options' => $principalOptions,
                    'membership_options' => DB::table('memberships')
                        ->orderBy('organization_id')
                        ->orderBy('id')
                        ->get(['id', 'principal_id', 'organization_id'])
                        ->map(static fn ($membership): array => [
                            'id' => (string) $membership->id,
                            'label' => sprintf('%s [%s / %s]', (string) $membership->id, (string) $membership->principal_id, (string) $membership->organization_id),
                        ])->all(),
                    'organization_options' => DB::table('organizations')
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(static fn ($organization): array => [
                            'id' => (string) $organization->id,
                            'label' => (string) $organization->name,
                        ])->all(),
                    'scope_options' => DB::table('scopes')
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get(['id', 'name', 'organization_id'])
                        ->map(static fn ($scope): array => [
                            'id' => (string) $scope->id,
                            'label' => sprintf('%s [%s]', (string) $scope->name, (string) $scope->organization_id),
                        ])->all(),
                ];
            },
            toolbarResolver: fn (ScreenRenderContext $screenContext): array => [
                new ToolbarAction(
                    label: 'Add role',
                    url: '#role-editor',
                    variant: 'primary',
                ),
                new ToolbarAction(
                    label: 'Assign grant',
                    url: '#grant-editor',
                    variant: 'secondary',
                ),
            ],
        ));
    }
}
