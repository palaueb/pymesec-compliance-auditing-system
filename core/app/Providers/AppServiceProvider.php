<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
use PymeSec\Core\Menus\MenuVisibilityContext;
use PymeSec\Core\Notifications\Contracts\NotificationServiceInterface;
use PymeSec\Core\Notifications\DatabaseNotificationService;
use PymeSec\Core\Notifications\NotificationMailSettingsRepository;
use PymeSec\Core\Notifications\NotificationTemplateRenderer;
use PymeSec\Core\Notifications\NotificationTemplateRepository;
use PymeSec\Core\Notifications\OutboundNotificationMailer;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\AuthorizationPresentation;
use PymeSec\Core\Permissions\AuthorizationService;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Permissions\Contracts\AuthorizationStoreInterface;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;
use PymeSec\Core\Permissions\DatabaseAuthorizationStore;
use PymeSec\Core\Permissions\PermissionDefinition;
use PymeSec\Core\Permissions\PermissionRegistry;
use PymeSec\Core\Plugins\Contracts\PluginManagerInterface;
use PymeSec\Core\Plugins\PluginLifecycleManager;
use PymeSec\Core\Plugins\PluginStateStore;
use PymeSec\Core\ReferenceData\ReferenceCatalogService;
use PymeSec\Core\Support\Contracts\SupportRegistryInterface;
use PymeSec\Core\Support\JsonSupportRegistry;
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
                mailSettings: $this->app->make(NotificationMailSettingsRepository::class),
                mailer: $this->app->make(OutboundNotificationMailer::class),
                templates: $this->app->make(NotificationTemplateRepository::class),
                templateRenderer: $this->app->make(NotificationTemplateRenderer::class),
            );
        });

        $this->app->singleton(NotificationMailSettingsRepository::class, function (): NotificationMailSettingsRepository {
            return new NotificationMailSettingsRepository;
        });

        $this->app->singleton(OutboundNotificationMailer::class, function (): OutboundNotificationMailer {
            return new OutboundNotificationMailer;
        });

        $this->app->singleton(NotificationTemplateRepository::class, function (): NotificationTemplateRepository {
            return new NotificationTemplateRepository;
        });

        $this->app->singleton(NotificationTemplateRenderer::class, function (): NotificationTemplateRenderer {
            return new NotificationTemplateRenderer;
        });

        $this->app->singleton(ObjectAccessService::class, function ($app): ObjectAccessService {
            return new ObjectAccessService(
                actors: $app->make(FunctionalActorServiceInterface::class),
                authorizationStore: $app->make(AuthorizationStoreInterface::class),
            );
        });

        $this->app->singleton(ArtifactServiceInterface::class, function (): ArtifactServiceInterface {
            return new DatabaseArtifactService(
                audit: $this->app->make(AuditTrailInterface::class),
                events: $this->app->make(EventBusInterface::class),
            );
        });

        $this->app->singleton(DatabaseAuthorizationStore::class, function (): DatabaseAuthorizationStore {
            return new DatabaseAuthorizationStore;
        });

        $this->app->singleton(AuthorizationStoreInterface::class, function ($app): AuthorizationStoreInterface {
            return $app->make(DatabaseAuthorizationStore::class);
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

        $this->app->singleton(SupportRegistryInterface::class, function ($app): SupportRegistryInterface {
            return new JsonSupportRegistry(
                plugins: $app->make(PluginManagerInterface::class),
            );
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

        $this->app->singleton(ReferenceCatalogService::class, function (): ReferenceCatalogService {
            return new ReferenceCatalogService;
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
                key: 'core.reference-data.view',
                label: 'View reference data',
                description: 'View governed business catalogs and their organization-specific overrides.',
                origin: 'core',
                featureArea: 'reference-data',
                operation: 'view',
                contexts: ['platform'],
            ),
            new PermissionDefinition(
                key: 'core.reference-data.manage',
                label: 'Manage reference data',
                description: 'Create and update governed catalog options used across the application.',
                origin: 'core',
                featureArea: 'reference-data',
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
            id: 'core.dashboard',
            owner: 'core',
            labelKey: 'core.nav.dashboard',
            routeName: 'core.shell.index',
            icon: 'pulse',
            order: 5,
            area: 'app',
        ));

        $menus->registerCore(new MenuDefinition(
            id: 'core.support',
            owner: 'core',
            labelKey: 'core.nav.support',
            routeName: 'core.shell.index',
            icon: 'book',
            order: 8,
            area: 'app',
        ));

        $menus->registerCore(new MenuDefinition(
            id: 'core.platform',
            owner: 'core',
            labelKey: 'core.nav.platform',
            icon: 'layout',
            order: 10,
            area: 'admin',
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
            area: 'admin',
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
            area: 'admin',
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
            area: 'admin',
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
            area: 'admin',
        ));

        $menus->registerCore(new MenuDefinition(
            id: 'core.reference-data',
            owner: 'core',
            labelKey: 'core.nav.reference_data',
            routeName: 'core.reference-data.index',
            parentId: 'core.platform',
            icon: 'list',
            order: 35,
            permission: 'core.reference-data.view',
            area: 'admin',
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
            area: 'admin',
        ));

        $menus->registerCore(new MenuDefinition(
            id: 'core.notifications',
            owner: 'core',
            labelKey: 'core.nav.notifications',
            routeName: 'core.notifications.index',
            parentId: 'core.platform',
            icon: 'mail',
            order: 45,
            permission: 'core.notifications.view',
            area: 'admin',
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
            area: 'admin',
        ));

        $menus->registerCore(new MenuDefinition(
            id: 'core.object-access',
            owner: 'core',
            labelKey: 'core.nav.object_access',
            routeName: 'core.admin.index',
            parentId: 'core.platform',
            icon: 'grid',
            order: 55,
            permission: 'core.functional-actors.view',
            area: 'admin',
        ));

        $screens = $this->app->make(ScreenRegistryInterface::class);

        $screens->register(new ScreenDefinition(
            menuId: 'core.dashboard',
            owner: 'core',
            titleKey: 'core.dashboard.screen.title',
            subtitleKey: 'core.dashboard.screen.subtitle',
            viewPath: resource_path('views/dashboard.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->workspaceDashboardData($screenContext),
        ));

        $screens->register(new ScreenDefinition(
            menuId: 'core.support',
            owner: 'core',
            titleKey: 'core.support.screen.title',
            subtitleKey: 'core.support.screen.subtitle',
            viewPath: resource_path('views/support.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->supportScreenData($screenContext),
        ));

        $screens->register(new ScreenDefinition(
            menuId: 'core.platform',
            owner: 'core',
            titleKey: 'core.platform.screen.title',
            subtitleKey: 'core.platform.screen.subtitle',
            viewPath: resource_path('views/platform-overview.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->platformOverviewData($screenContext),
            toolbarResolver: fn (ScreenRenderContext $screenContext): array => [
                new ToolbarAction(
                    label: 'Roles',
                    url: route('core.admin.index', [...$this->coreScreenQuery($screenContext), 'menu' => 'core.roles']),
                    variant: 'primary',
                ),
                new ToolbarAction(
                    label: 'Plugins',
                    url: route('core.admin.index', [...$this->coreScreenQuery($screenContext), 'menu' => 'core.plugins']),
                    variant: 'secondary',
                ),
            ],
        ));

        $screens->register(new ScreenDefinition(
            menuId: 'core.plugins',
            owner: 'core',
            titleKey: 'core.plugins.screen.title',
            subtitleKey: 'core.plugins.screen.subtitle',
            viewPath: resource_path('views/plugins.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->pluginsScreenData($screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->coreScreenQuery($screenContext);
                unset($query['plugin_id']);

                if (is_string($screenContext->query['plugin_id'] ?? null) && ($screenContext->query['plugin_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to modules',
                            url: route('core.admin.index', [...$query, 'menu' => 'core.plugins']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [];
            },
        ));

        $screens->register(new ScreenDefinition(
            menuId: 'core.permissions',
            owner: 'core',
            titleKey: 'core.permissions.screen.title',
            subtitleKey: 'core.permissions.screen.subtitle',
            viewPath: resource_path('views/permissions.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->permissionsScreenData($screenContext),
        ));

        $screens->register(new ScreenDefinition(
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

                $roleRecords = array_map(function (array $role): array {
                    $category = AuthorizationPresentation::roleCategory($role['permissions'] ?? []);

                    return [
                        ...$role,
                        'category' => $category,
                        'category_label' => AuthorizationPresentation::categoryLabel($category),
                        'category_description' => AuthorizationPresentation::categoryDescription($category),
                    ];
                }, $store->roleRecords());

                usort($roleRecords, static function (array $left, array $right): int {
                    return [$left['category'], $left['label']] <=> [$right['category'], $right['label']];
                });

                $permissionOptions = array_map(static function ($permission): array {
                    $category = AuthorizationPresentation::permissionCategory($permission->key);

                    return [
                        'key' => $permission->key,
                        'label' => $permission->label,
                        'category' => $category,
                    ];
                }, $permissions->all());

                $permissionGroups = collect($permissionOptions)
                    ->groupBy('category')
                    ->map(static function ($items, $category): array {
                        return [
                            'key' => (string) $category,
                            'label' => AuthorizationPresentation::categoryLabel((string) $category),
                            'description' => AuthorizationPresentation::categoryDescription((string) $category),
                            'permissions' => collect($items)->sortBy('label')->values()->all(),
                        ];
                    })
                    ->sortBy('label')
                    ->values()
                    ->all();

                $listQuery = $query;
                unset($listQuery['role_key'], $listQuery['grant_id']);

                $grants = array_map(static function (array $grant) use ($listQuery): array {
                    return [
                        ...$grant,
                        'open_url' => route('core.admin.index', [...$listQuery, 'menu' => 'core.roles', 'grant_id' => $grant['id']]),
                    ];
                }, $store->grantRecords());

                $roles = array_map(static function (array $role) use ($listQuery): array {
                    return [
                        ...$role,
                        'open_url' => route('core.admin.index', [...$listQuery, 'menu' => 'core.roles', 'role_key' => $role['key']]),
                    ];
                }, $roleRecords);

                $selectedRoleKey = is_string($screenContext->query['role_key'] ?? null) && $screenContext->query['role_key'] !== ''
                    ? (string) $screenContext->query['role_key']
                    : null;
                $selectedGrantId = is_string($screenContext->query['grant_id'] ?? null) && $screenContext->query['grant_id'] !== ''
                    ? (string) $screenContext->query['grant_id']
                    : null;

                $selectedRole = null;
                $selectedGrant = null;

                if (is_string($selectedRoleKey)) {
                    foreach ($roles as $role) {
                        if ($role['key'] === $selectedRoleKey) {
                            $selectedRole = $role;
                            break;
                        }
                    }
                }

                if (is_string($selectedGrantId)) {
                    foreach ($grants as $grant) {
                        if (($grant['id'] ?? null) === $selectedGrantId) {
                            $selectedGrant = $grant;
                            break;
                        }
                    }
                }

                return [
                    'roles' => $roles,
                    'grants' => $grants,
                    'selected_role' => $selectedRole,
                    'selected_grant' => $selectedGrant,
                    'query' => $query,
                    'list_query' => $listQuery,
                    'role_store_route' => route('core.roles.store'),
                    'grant_store_route' => route('core.grants.store'),
                    'permission_options' => $permissionOptions,
                    'permission_groups' => $permissionGroups,
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
                    'roles_list_url' => route('core.admin.index', [...$listQuery, 'menu' => 'core.roles']),
                ];
            },
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->coreScreenQuery($screenContext);
                unset($query['role_key'], $query['grant_id']);

                if (is_string($screenContext->query['role_key'] ?? null) && ($screenContext->query['role_key'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to roles',
                            url: route('core.admin.index', [...$query, 'menu' => 'core.roles']),
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Assign grant',
                            url: '#grant-editor',
                            variant: 'secondary',
                        ),
                    ];
                }

                if (is_string($screenContext->query['grant_id'] ?? null) && ($screenContext->query['grant_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to grants',
                            url: route('core.admin.index', [...$query, 'menu' => 'core.roles']),
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Add role',
                            url: '#role-editor',
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
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
                ];
            },
        ));

        $screens->register(new ScreenDefinition(
            menuId: 'core.tenancy',
            owner: 'core',
            titleKey: 'core.tenancy.screen.title',
            subtitleKey: 'core.tenancy.screen.subtitle',
            viewPath: resource_path('views/tenancy.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->tenancyScreenData($screenContext),
        ));

        $screens->register(new ScreenDefinition(
            menuId: 'core.reference-data',
            owner: 'core',
            titleKey: 'core.reference-data.screen.title',
            subtitleKey: 'core.reference-data.screen.subtitle',
            viewPath: resource_path('views/reference-catalogs.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->referenceCatalogsScreenData($screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->coreScreenQuery($screenContext);
                $catalogKey = is_string($screenContext->query['catalog_key'] ?? null) && ($screenContext->query['catalog_key'] ?? '') !== ''
                    ? (string) $screenContext->query['catalog_key']
                    : null;

                if ($catalogKey === null) {
                    return [];
                }

                return [
                    new ToolbarAction(
                        label: 'Add option',
                        url: '#reference-catalog-entry-editor',
                        variant: 'primary',
                    ),
                ];
            },
        ));

        $screens->register(new ScreenDefinition(
            menuId: 'core.audit',
            owner: 'core',
            titleKey: 'core.audit.screen.title',
            subtitleKey: 'core.audit.screen.subtitle',
            viewPath: resource_path('views/audit.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->auditScreenData($screenContext),
            toolbarResolver: fn (ScreenRenderContext $screenContext): array => [
                new ToolbarAction(
                    label: 'Export JSONL',
                    url: route('core.audit.export', [...$this->coreScreenQuery($screenContext), 'format' => 'jsonl']),
                    variant: 'secondary',
                    target: '_blank',
                ),
                new ToolbarAction(
                    label: 'Export CSV',
                    url: route('core.audit.export', [...$this->coreScreenQuery($screenContext), 'format' => 'csv']),
                    variant: 'secondary',
                    target: '_blank',
                ),
            ],
        ));

        $screens->register(new ScreenDefinition(
            menuId: 'core.notifications',
            owner: 'core',
            titleKey: 'core.notifications.screen.title',
            subtitleKey: 'core.notifications.screen.subtitle',
            viewPath: resource_path('views/notifications.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->notificationsScreenData($screenContext),
        ));

        $screens->register(new ScreenDefinition(
            menuId: 'core.functional-actors',
            owner: 'core',
            titleKey: 'core.functional-actors.screen.title',
            subtitleKey: 'core.functional-actors.screen.subtitle',
            viewPath: resource_path('views/functional-actors.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->functionalActorsScreenData($screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->coreScreenQuery($screenContext);
                unset($query['actor_id']);

                if (is_string($screenContext->query['actor_id'] ?? null) && ($screenContext->query['actor_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to directory',
                            url: route('core.admin.index', [...$query, 'menu' => 'core.functional-actors']),
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Link person',
                            url: '#functional-actor-link-editor',
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Assign responsibility',
                            url: '#functional-actor-assignment-editor',
                            variant: 'primary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'Add functional profile',
                        url: '#functional-actor-create-editor',
                        variant: 'primary',
                    ),
                    new ToolbarAction(
                        label: 'Link person',
                        url: '#functional-actor-principal-link-editor',
                        variant: 'secondary',
                    ),
                ];
            },
        ));

        $screens->register(new ScreenDefinition(
            menuId: 'core.object-access',
            owner: 'core',
            titleKey: 'core.object-access.screen.title',
            subtitleKey: 'core.object-access.screen.subtitle',
            viewPath: resource_path('views/object-access.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->objectAccessScreenData($screenContext),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function platformOverviewData(ScreenRenderContext $screenContext): array
    {
        $plugins = $this->app->make(PluginManagerInterface::class)->status();
        $permissions = $this->app->make(PermissionRegistryInterface::class)->all();
        $roles = $this->app->make(AuthorizationStoreInterface::class)->roleRecords();
        $audit = $this->app->make(AuditTrailInterface::class)->latest(8);
        $query = $this->coreScreenQuery($screenContext);

        return [
            'query' => $query,
            'metrics' => [
                'plugins' => count($plugins),
                'permissions' => count($permissions),
                'roles' => count($roles),
                'organizations' => DB::table('organizations')->where('is_active', true)->count(),
            ],
            'quick_links' => [
                [
                    'label' => 'Plugins',
                    'copy' => 'Discovery status, compatibility, and activation state.',
                    'url' => route('core.admin.index', [...$query, 'menu' => 'core.plugins']),
                ],
                [
                    'label' => 'Permissions',
                    'copy' => 'Registered capabilities across core and plugins.',
                    'url' => route('core.admin.index', [...$query, 'menu' => 'core.permissions']),
                ],
                [
                    'label' => 'Tenancy',
                    'copy' => 'Organizations, scopes, and access boundaries.',
                    'url' => route('core.admin.index', [...$query, 'menu' => 'core.tenancy']),
                ],
                [
                    'label' => 'Audit',
                    'copy' => 'Recent sensitive operations and evidence trail.',
                    'url' => route('core.admin.index', [...$query, 'menu' => 'core.audit']),
                ],
                [
                    'label' => 'Notifications',
                    'copy' => 'Reminder delivery, SMTP setup, and outbound email checks.',
                    'url' => route('core.admin.index', [...$query, 'menu' => 'core.notifications']),
                ],
            ],
            'recent_audit' => array_map(static fn ($record): array => $record->toArray(), $audit),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function workspaceDashboardData(ScreenRenderContext $screenContext): array
    {
        $query = $this->coreScreenQuery($screenContext);
        $organizationId = $screenContext->organizationId;
        $scopeId = $screenContext->scopeId;
        $metrics = [
            'assets' => $this->filteredScopedCount(
                table: 'assets',
                organizationId: $organizationId,
                scopeId: $scopeId,
                principalId: $screenContext->principal?->id,
                domainObjectType: 'asset',
            ),
            'risks_assessing' => $this->filteredScopedStateCount(
                table: 'risks',
                organizationId: $organizationId,
                scopeId: $scopeId,
                stateColumn: 'state',
                states: ['assessing'],
                principalId: $screenContext->principal?->id,
                domainObjectType: 'risk',
            ),
            'controls_review' => $this->scopedStateCount('controls', $organizationId, $scopeId, 'state', ['review']),
            'findings_open' => $this->filteredScopedStateCount(
                table: 'findings',
                organizationId: $organizationId,
                scopeId: $scopeId,
                stateColumn: 'state',
                states: ['open', 'remediating'],
                principalId: $screenContext->principal?->id,
                domainObjectType: 'finding',
            ),
            'exceptions_requested' => $this->scopedStateCount('policy_exceptions', $organizationId, $scopeId, 'state', ['requested']),
        ];

        $recentAudit = array_map(
            static fn ($record): array => $record->toArray(),
            $this->app->make(AuditTrailInterface::class)->latest(8),
        );

        $visibleMenus = $this->resolvedVisibleMenus($screenContext);
        $appMenus = $this->filterMenusByArea($visibleMenus, 'app');
        $quickLinks = collect($this->flattenMenus($appMenus))
            ->reject(static fn (array $menu, string $id): bool => $id === 'core.dashboard')
            ->filter(static fn (array $menu): bool => is_string($menu['shell_url'] ?? null))
            ->take(6)
            ->map(static fn (array $menu): array => [
                'id' => $menu['id'],
                'label' => $menu['label'] ?? $menu['id'],
                'copy' => $menu['caption'] ?? 'Open this workspace.',
                'url' => $menu['shell_url'],
            ])
            ->values()
            ->all();

        $membershipRoles = collect($screenContext->memberships)
            ->flatMap(static fn ($membership): array => $membership->roles)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'query' => $query,
            'organization' => $organizationId,
            'scope' => $scopeId,
            'metrics' => $metrics,
            'recent_audit' => $recentAudit,
            'quick_links' => $quickLinks,
            'role_sets' => $membershipRoles,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pluginsScreenData(ScreenRenderContext $screenContext): array
    {
        $runtimePlugins = $this->app->make(PluginManagerInterface::class)->status();
        $authorization = $this->app->make(AuthorizationServiceInterface::class);
        $visibleMenus = $this->app->make(MenuRegistryInterface::class)->visible(new MenuVisibilityContext(
            principal: $screenContext->principal,
            memberships: $screenContext->memberships,
            organizationId: $screenContext->organizationId,
            scopeId: $screenContext->scopeId,
        ));
        $visibleMenuMap = [];
        $flattenMenus = function (array $items) use (&$flattenMenus, &$visibleMenuMap): void {
            foreach ($items as $item) {
                if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                    continue;
                }

                $visibleMenuMap[$item['id']] = $item;
                $flattenMenus($item['children'] ?? []);
            }
        };
        $flattenMenus($visibleMenus);
        $query = $this->coreScreenQuery($screenContext);
        $plugins = array_map(function (array $plugin) use ($query, $visibleMenuMap): array {
            $visibleWorkspaceMenus = array_values(array_filter(
                $visibleMenuMap,
                static fn (array $menu): bool => ($menu['owner'] ?? null) === ($plugin['id'] ?? null) && is_string($menu['route'] ?? null),
            ));
            usort($visibleWorkspaceMenus, static function (array $left, array $right): int {
                return ((int) ($left['order'] ?? 100)) <=> ((int) ($right['order'] ?? 100));
            });

            $settingsMenuId = is_string($plugin['settings_menu_id'] ?? null) ? $plugin['settings_menu_id'] : null;
            $workspaceMenu = $visibleWorkspaceMenus[0] ?? null;
            $settingsMenu = $settingsMenuId !== null ? ($visibleMenuMap[$settingsMenuId] ?? null) : null;

            return [
                ...$plugin,
                'open_url' => route('core.admin.index', [...$query, 'menu' => 'core.plugins', 'plugin_id' => $plugin['id']]),
                'workspace_url' => is_array($workspaceMenu)
                    ? route('core.shell.index', [...$query, 'menu' => $workspaceMenu['id']])
                    : null,
                'settings_url' => is_array($settingsMenu)
                    ? route('core.shell.index', [...$query, 'menu' => $settingsMenu['id']])
                    : null,
                'settings_requires_context' => $settingsMenuId !== null && ! is_array($settingsMenu),
            ];
        }, $this->app->make(PluginLifecycleManager::class)->enrichStatus($runtimePlugins));

        $listQuery = $query;
        unset($listQuery['plugin_id']);

        $selectedPluginId = is_string($screenContext->query['plugin_id'] ?? null) && $screenContext->query['plugin_id'] !== ''
            ? (string) $screenContext->query['plugin_id']
            : null;
        $selectedPlugin = null;

        if (is_string($selectedPluginId)) {
            foreach ($plugins as $plugin) {
                if (($plugin['id'] ?? null) === $selectedPluginId) {
                    $selectedPlugin = $plugin;
                    break;
                }
            }
        }

        return [
            'query' => $query,
            'list_query' => $listQuery,
            'plugins' => $plugins,
            'selected_plugin' => $selectedPlugin,
            'metrics' => [
                'enabled' => collect($plugins)->where('effective_enabled', true)->count(),
                'booted' => collect($plugins)->where('booted', true)->count(),
                'attention' => collect($plugins)->whereNotNull('reason')->count(),
                'overrides' => collect($plugins)->whereNotNull('override_state')->count(),
            ],
            'can_manage_plugins' => $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
                principal: $screenContext->principal,
                permission: 'core.plugins.manage',
                memberships: $screenContext->memberships,
                organizationId: $screenContext->organizationId,
                scopeId: $screenContext->scopeId,
            ))->allowed(),
            'enable_plugin_route' => static fn (string $pluginId): string => route('core.plugins.enable', ['pluginId' => $pluginId]),
            'disable_plugin_route' => static fn (string $pluginId): string => route('core.plugins.disable', ['pluginId' => $pluginId]),
            'state_path' => $this->app->make(PluginStateStore::class)->path(),
            'plugins_list_url' => route('core.admin.index', [...$listQuery, 'menu' => 'core.plugins']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function permissionsScreenData(ScreenRenderContext $screenContext): array
    {
        $permissions = array_map(
            static fn ($definition): array => $definition->toArray(),
            $this->app->make(PermissionRegistryInterface::class)->all(),
        );

        $origins = collect($permissions)
            ->groupBy('origin')
            ->map(static fn ($items, $origin): array => [
                'origin' => (string) $origin,
                'count' => count($items),
            ])
            ->sortBy('origin')
            ->values()
            ->all();

        return [
            'query' => $this->coreScreenQuery($screenContext),
            'permissions' => $permissions,
            'origins' => $origins,
            'metrics' => [
                'total' => count($permissions),
                'platform' => collect($permissions)->filter(static fn (array $permission): bool => in_array('platform', $permission['contexts'] ?? [], true))->count(),
                'organization' => collect($permissions)->filter(static fn (array $permission): bool => in_array('organization', $permission['contexts'] ?? [], true))->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function supportScreenData(ScreenRenderContext $screenContext): array
    {
        $catalogue = $this->app->make(SupportRegistryInterface::class)->catalogue($screenContext->locale);

        return [
            'query' => $this->coreScreenQuery($screenContext),
            'guide' => $catalogue['guide'] ?? [],
            'concepts' => $catalogue['concepts'] ?? [],
            'concept_index' => $catalogue['concept_index'] ?? [],
            'relationships' => $catalogue['relationships'] ?? [],
            'issues' => $catalogue['issues'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationsScreenData(ScreenRenderContext $screenContext): array
    {
        $query = $this->coreScreenQuery($screenContext);
        $organizationId = $screenContext->organizationId;
        $scopeId = $screenContext->scopeId;
        $authorization = $this->app->make(AuthorizationServiceInterface::class);
        $canManageNotifications = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'core.notifications.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $scopeId,
        ))->allowed();

        $notifications = [];
        $settings = [
            'organization_id' => $organizationId,
            'email_enabled' => false,
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'has_password' => false,
            'from_address' => '',
            'from_name' => '',
            'reply_to_address' => '',
            'last_tested_at' => '',
            'updated_by_principal_id' => '',
        ];
        $selectedTemplateType = is_string($screenContext->query['template_type'] ?? null) && $screenContext->query['template_type'] !== ''
            ? (string) $screenContext->query['template_type']
            : null;
        $templates = [];
        $selectedTemplate = null;

        if (is_string($organizationId) && $organizationId !== '') {
            $settings = [
                ...$settings,
                ...($this->app->make(NotificationMailSettingsRepository::class)->findForOrganization($organizationId) ?? []),
            ];

            $notifications = array_map(function (array $notification): array {
                $emailDelivery = is_array($notification['metadata']['channels']['email'] ?? null)
                    ? $notification['metadata']['channels']['email']
                    : [];

                return [
                    ...$notification,
                    'email_delivery_status' => is_string($emailDelivery['status'] ?? null) && $emailDelivery['status'] !== ''
                        ? $emailDelivery['status']
                        : 'not-attempted',
                    'email_delivery_reason' => is_string($emailDelivery['reason'] ?? null) && $emailDelivery['reason'] !== ''
                        ? $emailDelivery['reason']
                        : null,
                ];
            }, array_map(
                static fn ($notification): array => $notification->toArray(),
                $this->app->make(NotificationServiceInterface::class)->latest(25, array_filter([
                    'organization_id' => $organizationId,
                    'scope_id' => $scopeId,
                ])),
            ));

            $templates = $this->notificationTemplateCatalogue(
                $organizationId,
                $notifications,
                $query,
            );

            if ($selectedTemplateType === null && $templates !== []) {
                $selectedTemplateType = (string) ($templates[0]['notification_type'] ?? '');
            }

            if (is_string($selectedTemplateType) && $selectedTemplateType !== '') {
                $selectedTemplate = $this->notificationTemplateSelection(
                    $organizationId,
                    $selectedTemplateType,
                    $templates,
                );
            }
        }

        return [
            'query' => $query,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'has_organization_context' => is_string($organizationId) && $organizationId !== '',
            'settings' => $settings,
            'notifications' => $notifications,
            'templates' => $templates,
            'selected_template' => $selectedTemplate,
            'principal_options' => is_string($organizationId) && $organizationId !== ''
                ? $this->functionalActorPrincipalOptions($organizationId)
                : [],
            'can_manage_notifications' => $canManageNotifications,
            'metrics' => [
                'notifications' => count($notifications),
                'pending' => collect($notifications)->where('status', 'pending')->count(),
                'dispatched' => collect($notifications)->where('status', 'dispatched')->count(),
                'email_sent' => collect($notifications)->where('email_delivery_status', 'sent')->count(),
                'email_failed' => collect($notifications)->where('email_delivery_status', 'failed')->count(),
            ],
            'save_settings_route' => route('core.notifications.settings.update'),
            'send_test_route' => route('core.notifications.test.send'),
            'save_template_route' => route('core.notifications.templates.update'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $notifications
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function notificationTemplateCatalogue(string $organizationId, array $notifications, array $query): array
    {
        $repository = $this->app->make(NotificationTemplateRepository::class);
        $templateMap = collect($repository->listForOrganization($organizationId))
            ->keyBy('notification_type')
            ->all();
        $known = $this->knownNotificationTemplateDefinitions();
        $types = collect(array_merge(array_keys($known), collect($notifications)->pluck('type')->filter()->unique()->all()))
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return array_map(function (string $type) use ($known, $notifications, $templateMap, $query): array {
            $metadataKeys = collect($notifications)
                ->filter(static fn (array $notification): bool => ($notification['type'] ?? null) === $type)
                ->flatMap(function (array $notification): array {
                    $metadata = is_array($notification['metadata'] ?? null) ? $notification['metadata'] : [];

                    return array_values(array_filter(array_keys($metadata), static fn (mixed $key): bool => is_string($key) && $key !== 'channels' && $key !== 'template'));
                })
                ->unique()
                ->sort()
                ->values()
                ->all();
            $variables = array_values(array_unique(array_merge([
                'notification_title',
                'notification_body',
                'notification_type',
                'organization_id',
                'scope_id',
                'principal_id',
                'deliver_at',
            ], $metadataKeys, $known[$type]['variables'] ?? [])));
            $stored = is_array($templateMap[$type] ?? null) ? $templateMap[$type] : null;

            return [
                'notification_type' => $type,
                'label' => $known[$type]['label'] ?? $type,
                'description' => $known[$type]['description'] ?? 'Custom notification type discovered from runtime activity.',
                'variables' => $variables,
                'is_active' => (bool) ($stored['is_active'] ?? false),
                'has_override' => $stored !== null,
                'open_url' => route('core.admin.index', [...$query, 'menu' => 'core.notifications', 'template_type' => $type]),
            ];
        }, $types);
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalogue
     * @return array<string, mixed>|null
     */
    private function notificationTemplateSelection(string $organizationId, string $notificationType, array $catalogue): ?array
    {
        $catalogEntry = collect($catalogue)->first(
            static fn (array $entry): bool => ($entry['notification_type'] ?? null) === $notificationType
        );

        if (! is_array($catalogEntry)) {
            return null;
        }

        $stored = $this->app->make(NotificationTemplateRepository::class)
            ->findForOrganizationAndType($organizationId, $notificationType);

        return [
            'notification_type' => $notificationType,
            'label' => $catalogEntry['label'],
            'description' => $catalogEntry['description'],
            'variables' => $catalogEntry['variables'],
            'is_active' => (bool) ($stored['is_active'] ?? false),
            'title_template' => is_string($stored['title_template'] ?? null) ? $stored['title_template'] : '',
            'body_template' => is_string($stored['body_template'] ?? null) ? $stored['body_template'] : '',
            'updated_by_principal_id' => is_string($stored['updated_by_principal_id'] ?? null) ? $stored['updated_by_principal_id'] : '',
            'updated_at' => is_string($stored['updated_at'] ?? null) ? $stored['updated_at'] : '',
        ];
    }

    /**
     * @return array<string, array{label: string, description: string, variables: array<int, string>}>
     */
    private function knownNotificationTemplateDefinitions(): array
    {
        return [
            'plugin.controls-catalog.review-requested' => [
                'label' => 'Control review requested',
                'description' => 'Operational follow-up for control owners when a control enters review.',
                'variables' => ['control_id', 'transition_key'],
            ],
            'plugin.evidence-management.review-due' => [
                'label' => 'Evidence review due',
                'description' => 'Reminder for evidence that must be reviewed before the due date.',
                'variables' => ['evidence_id', 'reminder_type', 'due_on'],
            ],
            'plugin.evidence-management.expiry-soon' => [
                'label' => 'Evidence expiry soon',
                'description' => 'Reminder for evidence that is about to expire and needs renewal or replacement.',
                'variables' => ['evidence_id', 'reminder_type', 'due_on'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tenancyScreenData(ScreenRenderContext $screenContext): array
    {
        $authorization = $this->app->make(AuthorizationServiceInterface::class);
        $organizations = DB::table('organizations')
            ->leftJoin('scopes', 'scopes.organization_id', '=', 'organizations.id')
            ->leftJoin('memberships', 'memberships.organization_id', '=', 'organizations.id')
            ->select(
                'organizations.id',
                'organizations.name',
                'organizations.slug',
                'organizations.default_locale',
                'organizations.default_timezone',
                'organizations.is_active',
                DB::raw('COUNT(DISTINCT scopes.id) as scope_count'),
                DB::raw('COUNT(DISTINCT memberships.id) as membership_count'),
            )
            ->groupBy('organizations.id', 'organizations.name', 'organizations.slug', 'organizations.default_locale', 'organizations.default_timezone', 'organizations.is_active')
            ->orderBy('organizations.name')
            ->get()
            ->map(static fn ($organization): array => [
                'id' => (string) $organization->id,
                'name' => (string) $organization->name,
                'slug' => (string) $organization->slug,
                'default_locale' => (string) $organization->default_locale,
                'default_timezone' => (string) $organization->default_timezone,
                'is_active' => (bool) $organization->is_active,
                'scope_count' => (int) $organization->scope_count,
                'membership_count' => (int) $organization->membership_count,
            ])
            ->all();

        $scopes = DB::table('scopes')
            ->orderBy('organization_id')
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name', 'slug', 'description', 'is_active'])
            ->map(static fn ($scope): array => [
                'id' => (string) $scope->id,
                'organization_id' => (string) $scope->organization_id,
                'name' => (string) $scope->name,
                'slug' => (string) $scope->slug,
                'description' => is_string($scope->description ?? null) ? $scope->description : '',
                'is_active' => (bool) $scope->is_active,
            ])
            ->all();

        $memberships = DB::table('memberships')
            ->orderBy('organization_id')
            ->orderBy('principal_id')
            ->limit(20)
            ->get(['id', 'principal_id', 'organization_id', 'is_active'])
            ->map(static fn ($membership): array => [
                'id' => (string) $membership->id,
                'principal_id' => (string) $membership->principal_id,
                'organization_id' => (string) $membership->organization_id,
                'is_active' => (bool) $membership->is_active,
            ])
            ->all();

        return [
            'query' => $this->coreScreenQuery($screenContext),
            'organizations' => $organizations,
            'scopes' => $scopes,
            'memberships' => $memberships,
            'locale_options' => ['en', 'es', 'fr', 'de'],
            'create_organization_route' => route('core.tenancy.organizations.store'),
            'create_scope_route' => route('core.tenancy.scopes.store'),
            'update_organization_route' => static fn (string $organizationId): string => route('core.tenancy.organizations.update', ['organizationId' => $organizationId]),
            'archive_organization_route' => static fn (string $organizationId): string => route('core.tenancy.organizations.archive', ['organizationId' => $organizationId]),
            'activate_organization_route' => static fn (string $organizationId): string => route('core.tenancy.organizations.activate', ['organizationId' => $organizationId]),
            'update_scope_route' => static fn (string $scopeId): string => route('core.tenancy.scopes.update', ['scopeId' => $scopeId]),
            'archive_scope_route' => static fn (string $scopeId): string => route('core.tenancy.scopes.archive', ['scopeId' => $scopeId]),
            'activate_scope_route' => static fn (string $scopeId): string => route('core.tenancy.scopes.activate', ['scopeId' => $scopeId]),
            'can_manage_tenancy' => $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
                principal: $screenContext->principal,
                permission: 'core.tenancy.manage',
                memberships: $screenContext->memberships,
                organizationId: $screenContext->organizationId,
                scopeId: $screenContext->scopeId,
            ))->allowed(),
            'metrics' => [
                'organizations' => count($organizations),
                'active_scopes' => collect($scopes)->where('is_active', true)->count(),
                'memberships' => DB::table('memberships')->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditScreenData(ScreenRenderContext $screenContext): array
    {
        $query = $this->coreScreenQuery($screenContext);
        $records = array_map(
            static fn ($record): array => $record->toArray(),
            $this->app->make(AuditTrailInterface::class)->latest(40),
        );

        return [
            'query' => $query,
            'records' => $records,
            'metrics' => [
                'events' => count($records),
                'failures' => collect($records)->where('outcome', 'failure')->count(),
                'components' => collect($records)->pluck('origin_component')->filter()->unique()->count(),
            ],
            'export_jsonl_url' => route('core.audit.export', [...$query, 'format' => 'jsonl']),
            'export_csv_url' => route('core.audit.export', [...$query, 'format' => 'csv']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function referenceCatalogsScreenData(ScreenRenderContext $screenContext): array
    {
        $catalogs = $this->app->make(ReferenceCatalogService::class);
        $authorization = $this->app->make(AuthorizationServiceInterface::class);
        $query = $this->coreScreenQuery($screenContext);
        $organizationOptions = DB::table('organizations')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn ($organization): array => [
                'id' => (string) $organization->id,
                'label' => (string) $organization->name,
            ])->all();

        $selectedOrganizationId = is_string($query['organization_id'] ?? null) && $query['organization_id'] !== ''
            ? (string) $query['organization_id']
            : ($organizationOptions[0]['id'] ?? null);

        if (is_string($selectedOrganizationId) && $selectedOrganizationId !== '') {
            $query['organization_id'] = $selectedOrganizationId;
        }

        $catalogRows = array_map(function (array $catalog) use ($catalogs, $query, $selectedOrganizationId): array {
            $effectiveRows = $catalogs->effectiveRows($catalog['key'], $selectedOrganizationId);
            $managedRows = $catalogs->managedEntries($catalog['key'], $selectedOrganizationId);

            return [
                ...$catalog,
                'effective_count' => count($effectiveRows),
                'managed_count' => count($managedRows),
                'uses_default' => count($managedRows) === 0,
                'open_url' => route('core.admin.index', [...$query, 'menu' => 'core.reference-data', 'catalog_key' => $catalog['key']]),
            ];
        }, $catalogs->manageableCatalogs());

        $selectedCatalogKey = is_string($screenContext->query['catalog_key'] ?? null) && ($screenContext->query['catalog_key'] ?? '') !== ''
            ? (string) $screenContext->query['catalog_key']
            : ($catalogRows[0]['key'] ?? null);
        $selectedCatalog = null;

        if (is_string($selectedCatalogKey)) {
            foreach ($catalogRows as $catalog) {
                if (($catalog['key'] ?? null) === $selectedCatalogKey) {
                    $selectedCatalog = $catalog;
                    break;
                }
            }
        }

        $managedEntries = is_array($selectedCatalog)
            ? $catalogs->managedEntries((string) $selectedCatalog['key'], $selectedOrganizationId)
            : [];
        $effectiveEntries = is_array($selectedCatalog)
            ? $catalogs->effectiveRows((string) $selectedCatalog['key'], $selectedOrganizationId)
            : [];

        $listQuery = $query;
        unset($listQuery['catalog_key'], $listQuery['entry_id']);

        $selectedEntryId = is_string($screenContext->query['entry_id'] ?? null) && ($screenContext->query['entry_id'] ?? '') !== ''
            ? (string) $screenContext->query['entry_id']
            : null;
        $selectedEntry = null;

        if (is_string($selectedEntryId)) {
            foreach ($managedEntries as $entry) {
                if (($entry['id'] ?? null) === $selectedEntryId) {
                    $selectedEntry = $entry;
                    break;
                }
            }
        }

        return [
            'query' => $query,
            'list_query' => $listQuery,
            'catalogs' => $catalogRows,
            'selected_catalog' => $selectedCatalog,
            'selected_entry' => $selectedEntry,
            'effective_entries' => $effectiveEntries,
            'managed_entries' => $managedEntries,
            'organization_options' => $organizationOptions,
            'selected_organization_id' => $selectedOrganizationId,
            'create_entry_route' => route('core.reference-data.entries.store'),
            'update_entry_route' => static fn (string $entryId): string => route('core.reference-data.entries.update', ['entryId' => $entryId]),
            'archive_entry_route' => static fn (string $entryId): string => route('core.reference-data.entries.archive', ['entryId' => $entryId]),
            'activate_entry_route' => static fn (string $entryId): string => route('core.reference-data.entries.activate', ['entryId' => $entryId]),
            'can_manage_reference_data' => $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
                principal: $screenContext->principal,
                permission: 'core.reference-data.manage',
                memberships: $screenContext->memberships,
                organizationId: $selectedOrganizationId,
                scopeId: $screenContext->scopeId,
            ))->allowed(),
            'metrics' => [
                'catalogs' => count($catalogRows),
                'effective_options' => array_sum(array_map(static fn (array $catalog): int => (int) ($catalog['effective_count'] ?? 0), $catalogRows)),
                'managed_entries' => array_sum(array_map(static fn (array $catalog): int => (int) ($catalog['managed_count'] ?? 0), $catalogRows)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function functionalActorsScreenData(ScreenRenderContext $screenContext): array
    {
        $service = $this->app->make(FunctionalActorServiceInterface::class);
        $authorization = $this->app->make(AuthorizationServiceInterface::class);
        $actors = $service->actors();
        $assignments = $service->assignments();
        $query = $this->coreScreenQuery($screenContext);
        $listQuery = $query;
        unset($listQuery['actor_id']);
        $organizationId = is_string($query['organization_id'] ?? null) && $query['organization_id'] !== ''
            ? (string) $query['organization_id']
            : null;
        $selectedPrincipalId = is_string($screenContext->query['subject_principal_id'] ?? null) && ($screenContext->query['subject_principal_id'] ?? '') !== ''
            ? (string) $screenContext->query['subject_principal_id']
            : null;

        $actorRows = array_map(function ($actor) use ($listQuery): array {
            $row = $actor->toArray();
            $row['open_url'] = route('core.admin.index', [...$listQuery, 'menu' => 'core.functional-actors', 'actor_id' => $row['id']]);

            return $row;
        }, $actors);

        $links = DB::table('principal_functional_actor_links')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['principal_id', 'functional_actor_id', 'organization_id', 'created_at'])
            ->map(static fn ($link): array => [
                'principal_id' => (string) $link->principal_id,
                'functional_actor_id' => (string) $link->functional_actor_id,
                'organization_id' => (string) $link->organization_id,
                'created_at' => (string) $link->created_at,
            ])
            ->all();

        $assignmentRows = array_map(function ($assignment) use ($screenContext): array {
            $row = $assignment->toArray();
            $row['subject_url'] = $this->domainObjectShellUrl(
                screenContext: $screenContext,
                domainType: (string) $row['domain_object_type'],
                domainId: (string) $row['domain_object_id'],
                organizationId: (string) $row['organization_id'],
                scopeId: is_string($row['scope_id'] ?? null) && $row['scope_id'] !== '' ? (string) $row['scope_id'] : null,
            );

            return $row;
        }, $assignments);

        $selectedActorId = is_string($screenContext->query['actor_id'] ?? null) && $screenContext->query['actor_id'] !== ''
            ? (string) $screenContext->query['actor_id']
            : null;
        $selectedActor = null;

        if (is_string($selectedActorId)) {
            foreach ($actorRows as $actor) {
                if (($actor['id'] ?? null) === $selectedActorId) {
                    $selectedActor = $actor;
                    break;
                }
            }
        }

        $selectedLinks = $selectedActorId !== null
            ? array_values(array_filter($links, static fn (array $link): bool => $link['functional_actor_id'] === $selectedActorId))
            : [];
        $selectedAssignments = $selectedActorId !== null
            ? array_values(array_filter($assignmentRows, static fn (array $assignment): bool => $assignment['functional_actor_id'] === $selectedActorId))
            : [];
        $selectedPrincipalActors = $selectedPrincipalId !== null
            ? array_map(static fn ($actor): array => $actor->toArray(), $service->actorsForPrincipal($selectedPrincipalId, $organizationId))
            : [];

        return [
            'query' => $query,
            'list_query' => $listQuery,
            'actors' => $actorRows,
            'selected_actor' => $selectedActor,
            'assignments' => $assignmentRows,
            'selected_links' => $selectedLinks,
            'selected_assignments' => $selectedAssignments,
            'links' => $links,
            'selected_principal_id' => $selectedPrincipalId,
            'selected_principal_actors' => $selectedPrincipalActors,
            'principal_options' => $this->functionalActorPrincipalOptions($organizationId),
            'actor_kind_options' => $this->functionalActorKindOptions(),
            'assignment_type_options' => $this->functionalAssignmentTypeOptions(),
            'assignable_object_options' => $this->functionalAssignableObjectOptions($organizationId),
            'can_manage_functional_actors' => $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
                principal: $screenContext->principal,
                permission: 'core.functional-actors.manage',
                memberships: $screenContext->memberships,
                organizationId: $organizationId,
                scopeId: is_string($query['scope_id'] ?? null) && $query['scope_id'] !== '' ? (string) $query['scope_id'] : null,
            ))->allowed(),
            'metrics' => [
                'actors' => count($actors),
                'links' => count($links),
                'assignments' => count($assignments),
                'organizations' => collect($actors)->pluck('organization_id')->filter()->unique()->count(),
            ],
            'actors_list_url' => route('core.admin.index', [...$listQuery, 'menu' => 'core.functional-actors']),
            'create_actor_route' => route('core.functional-actors.store'),
            'link_principal_route' => route('core.functional-actors.links.store'),
            'assign_actor_route' => route('core.functional-actors.assignments.store'),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function functionalActorPrincipalOptions(?string $organizationId): array
    {
        if (! Schema::hasTable('identity_local_users')) {
            return [];
        }

        $query = DB::table('identity_local_users')
            ->orderBy('display_name')
            ->orderBy('email');

        if (is_string($organizationId) && $organizationId !== '') {
            $query->where('organization_id', $organizationId);
        }

        return $query->get(['principal_id', 'display_name', 'email'])
            ->map(static fn ($row): array => [
                'id' => (string) $row->principal_id,
                'label' => sprintf(
                    '%s%s',
                    (string) ($row->display_name !== '' ? $row->display_name : $row->principal_id),
                    is_string($row->email ?? null) && $row->email !== '' ? ' ('.$row->email.')' : ''
                ),
            ])->all();
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function functionalActorKindOptions(): array
    {
        return [
            ['id' => 'person', 'label' => 'Person'],
            ['id' => 'team', 'label' => 'Team'],
            ['id' => 'office', 'label' => 'Office'],
            ['id' => 'role', 'label' => 'Role'],
        ];
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function functionalAssignmentTypeOptions(): array
    {
        return [
            ['id' => 'owner', 'label' => 'Owner'],
            ['id' => 'reviewer', 'label' => 'Reviewer'],
            ['id' => 'approver', 'label' => 'Approver'],
            ['id' => 'contributor', 'label' => 'Contributor'],
        ];
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function functionalAssignableObjectOptions(?string $organizationId): array
    {
        if (! is_string($organizationId) || $organizationId === '') {
            return [];
        }

        $definitions = [
            ['table' => 'assets', 'type' => 'asset', 'label_column' => 'name', 'prefix' => 'Asset'],
            ['table' => 'risks', 'type' => 'risk', 'label_column' => 'title', 'prefix' => 'Risk'],
            ['table' => 'controls', 'type' => 'control', 'label_column' => 'name', 'prefix' => 'Control'],
            ['table' => 'findings', 'type' => 'finding', 'label_column' => 'title', 'prefix' => 'Finding'],
            ['table' => 'policies', 'type' => 'policy', 'label_column' => 'title', 'prefix' => 'Policy'],
            ['table' => 'policy_exceptions', 'type' => 'policy-exception', 'label_column' => 'title', 'prefix' => 'Policy exception'],
            ['table' => 'privacy_data_flows', 'type' => 'data-flow', 'label_column' => 'title', 'prefix' => 'Data flow'],
            ['table' => 'privacy_processing_activities', 'type' => 'processing-activity', 'label_column' => 'title', 'prefix' => 'Processing activity'],
            ['table' => 'continuity_services', 'type' => 'continuity-service', 'label_column' => 'title', 'prefix' => 'Continuity service'],
            ['table' => 'continuity_plans', 'type' => 'recovery-plan', 'label_column' => 'title', 'prefix' => 'Recovery plan'],
            ['table' => 'assessment_campaigns', 'type' => 'assessment', 'label_column' => 'title', 'prefix' => 'Assessment'],
        ];

        $options = [];

        foreach ($definitions as $definition) {
            if (! Schema::hasTable($definition['table'])) {
                continue;
            }

            $rows = DB::table($definition['table'])
                ->where('organization_id', $organizationId)
                ->orderBy($definition['label_column'])
                ->get(['id', $definition['label_column']]);

            foreach ($rows as $row) {
                $options[] = [
                    'id' => $definition['type'].'::'.(string) $row->id,
                    'label' => sprintf('%s · %s [%s]', $definition['prefix'], (string) $row->{$definition['label_column']}, (string) $row->id),
                ];
            }
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectAccessScreenData(ScreenRenderContext $screenContext): array
    {
        $query = $this->coreScreenQuery($screenContext);
        $organizationId = $screenContext->organizationId;
        $scopeId = $screenContext->scopeId;
        $authorization = $this->app->make(AuthorizationServiceInterface::class);
        $service = $this->app->make(FunctionalActorServiceInterface::class);
        $objectAccess = $this->app->make(ObjectAccessService::class);
        $canManageObjectAccess = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'core.functional-actors.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $scopeId,
        ))->allowed();

        $principalOptions = $this->functionalActorPrincipalOptions($organizationId);
        $actorRows = array_map(static fn ($actor): array => $actor->toArray(), $service->actors($organizationId, $scopeId));
        $assignmentRows = array_map(static fn ($assignment): array => $assignment->toArray(), $service->assignments($organizationId, $scopeId));
        $actorMap = collect($actorRows)->keyBy('id')->all();
        $actorOptions = array_map(static fn (array $actor): array => [
            'id' => (string) $actor['id'],
            'label' => sprintf('%s (%s)', (string) $actor['display_name'], (string) $actor['kind']),
        ], $actorRows);
        $assignableObjectOptions = $this->functionalAssignableObjectOptions($organizationId);
        $assignableObjectMap = collect($assignableObjectOptions)->mapWithKeys(
            static fn (array $option): array => [(string) $option['id'] => (string) $option['label']]
        )->all();
        $domainDefinitions = $this->objectAccessDomainDefinitions();
        $selectedPrincipalId = is_string($screenContext->query['subject_principal_id'] ?? null) && $screenContext->query['subject_principal_id'] !== ''
            ? (string) $screenContext->query['subject_principal_id']
            : null;
        $selectedSubjectKey = is_string($screenContext->query['subject_key'] ?? null) && $screenContext->query['subject_key'] !== ''
            ? (string) $screenContext->query['subject_key']
            : null;
        $principalLinks = [];

        if (Schema::hasTable('principal_functional_actor_links') && is_string($organizationId) && $organizationId !== '') {
            $principalLinks = DB::table('principal_functional_actor_links')
                ->where('organization_id', $organizationId)
                ->orderBy('principal_id')
                ->orderBy('functional_actor_id')
                ->get(['functional_actor_id', 'principal_id'])
                ->map(static fn ($link): array => [
                    'functional_actor_id' => (string) $link->functional_actor_id,
                    'principal_id' => (string) $link->principal_id,
                ])->all();
        }

        $linksByActor = collect($principalLinks)->groupBy('functional_actor_id')->all();
        $linkedActors = $selectedPrincipalId !== null
            ? array_map(static fn ($actor): array => $actor->toArray(), $service->actorsForPrincipal($selectedPrincipalId, $organizationId))
            : [];
        $linkedActorIds = collect($linkedActors)->pluck('id')->filter()->values()->all();
        $principalAssignmentRows = $selectedPrincipalId !== null
            ? array_values(array_filter(
                $assignmentRows,
                static fn (array $assignment): bool => in_array($assignment['functional_actor_id'] ?? null, $linkedActorIds, true)
            ))
            : [];

        $selectedPrincipalMatrix = array_values(collect($principalAssignmentRows)
            ->groupBy(static fn (array $assignment): string => (string) $assignment['domain_object_type'].'::'.(string) $assignment['domain_object_id'])
            ->map(function ($group, string $subjectKey) use ($actorMap, $linksByActor, $assignableObjectMap, $screenContext, $organizationId, $scopeId, $domainDefinitions): array {
                $first = $group->first();
                $domainType = (string) ($first['domain_object_type'] ?? '');
                $domainId = (string) ($first['domain_object_id'] ?? '');
                $actorLabels = $group
                    ->map(static fn (array $assignment): ?string => $actorMap[$assignment['functional_actor_id'] ?? '']['display_name'] ?? null)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $principalLabels = $group
                    ->flatMap(static fn (array $assignment): array => $linksByActor[$assignment['functional_actor_id'] ?? ''] ?? [])
                    ->pluck('principal_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'subject_key' => $subjectKey,
                    'domain_object_type' => $domainType,
                    'domain_label' => $domainDefinitions[$domainType]['label'] ?? ucfirst(str_replace('-', ' ', $domainType)),
                    'label' => $assignableObjectMap[$subjectKey] ?? sprintf('%s [%s]', $domainType, $domainId),
                    'assignment_types' => $group->pluck('assignment_type')->filter()->unique()->values()->all(),
                    'actors' => $actorLabels,
                    'principals' => $principalLabels,
                    'open_url' => is_string($organizationId) && $organizationId !== ''
                        ? $this->domainObjectShellUrl($screenContext, $domainType, $domainId, $organizationId, $scopeId)
                        : null,
                ];
            })
            ->values()
            ->all());

        $domainVisibility = $selectedPrincipalId !== null && is_string($organizationId) && $organizationId !== ''
            ? array_values(array_map(function (array $definition) use ($objectAccess, $selectedPrincipalId, $organizationId, $scopeId, $selectedPrincipalMatrix): array {
                $visibleIds = $objectAccess->visibleObjectIds($selectedPrincipalId, $organizationId, $scopeId, $definition['type']);
                $directAssignments = array_values(array_filter(
                    $selectedPrincipalMatrix,
                    static fn (array $row): bool => ($row['domain_object_type'] ?? null) === $definition['type']
                ));

                return [
                    'domain_type' => $definition['type'],
                    'label' => $definition['label'],
                    'mode' => $visibleIds === null ? 'broad fallback' : 'scoped',
                    'visible_count' => $visibleIds === null
                        ? $this->countObjectAccessDomainRecords($definition['table'], $organizationId, $scopeId)
                        : count($visibleIds),
                    'direct_assignment_count' => count($directAssignments),
                ];
            }, array_values($domainDefinitions)))
            : [];

        $selectedObjectAssignments = [];

        if (is_string($selectedSubjectKey) && $selectedSubjectKey !== '' && is_string($organizationId) && $organizationId !== '') {
            [$domainType, $domainId] = array_pad(explode('::', $selectedSubjectKey, 2), 2, null);

            if (is_string($domainType) && $domainType !== '' && is_string($domainId) && $domainId !== '') {
                $selectedObjectAssignments = array_map(function ($assignment) use ($actorMap, $linksByActor): array {
                    $actor = is_array($actorMap[$assignment->functionalActorId] ?? null) ? $actorMap[$assignment->functionalActorId] : null;
                    $principalIds = collect($linksByActor[$assignment->functionalActorId] ?? [])
                        ->pluck('principal_id')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    return [
                        ...$assignment->toArray(),
                        'actor_label' => is_array($actor) ? (string) $actor['display_name'] : $assignment->functionalActorId,
                        'principal_ids' => $principalIds,
                    ];
                }, $service->assignmentsFor($domainType, $domainId, $organizationId, $scopeId));
            }
        }

        return [
            'query' => $query,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'has_organization_context' => is_string($organizationId) && $organizationId !== '',
            'principal_options' => $principalOptions,
            'actor_options' => $actorOptions,
            'assignment_type_options' => $this->functionalAssignmentTypeOptions(),
            'assignable_object_options' => $assignableObjectOptions,
            'selected_principal_id' => $selectedPrincipalId,
            'selected_subject_key' => $selectedSubjectKey,
            'selected_principal_actors' => $linkedActors,
            'selected_principal_matrix' => $selectedPrincipalMatrix,
            'domain_visibility' => $domainVisibility,
            'selected_object_assignments' => $selectedObjectAssignments,
            'can_manage_object_access' => $canManageObjectAccess,
            'metrics' => [
                'actors' => count($actorRows),
                'assignments' => count($assignmentRows),
                'governed_objects' => collect($assignmentRows)
                    ->map(static fn (array $assignment): string => (string) $assignment['domain_object_type'].'::'.(string) $assignment['domain_object_id'])
                    ->unique()
                    ->count(),
                'principals_with_links' => collect($principalLinks)->pluck('principal_id')->unique()->count(),
            ],
            'assign_object_access_route' => route('core.object-access.assignments.store'),
            'deactivate_object_access_route' => static fn (string $assignmentId): string => route('core.object-access.assignments.deactivate', ['assignmentId' => $assignmentId]),
        ];
    }

    /**
     * @return array<string, array{type: string, label: string, table: string}>
     */
    private function objectAccessDomainDefinitions(): array
    {
        $definitions = [
            ['type' => 'asset', 'label' => 'Assets', 'table' => 'assets'],
            ['type' => 'risk', 'label' => 'Risks', 'table' => 'risks'],
            ['type' => 'control', 'label' => 'Controls', 'table' => 'controls'],
            ['type' => 'finding', 'label' => 'Findings', 'table' => 'findings'],
            ['type' => 'policy', 'label' => 'Policies', 'table' => 'policies'],
            ['type' => 'policy-exception', 'label' => 'Policy exceptions', 'table' => 'policy_exceptions'],
            ['type' => 'data-flow', 'label' => 'Data flows', 'table' => 'privacy_data_flows'],
            ['type' => 'processing-activity', 'label' => 'Processing activities', 'table' => 'privacy_processing_activities'],
            ['type' => 'continuity-service', 'label' => 'Continuity services', 'table' => 'continuity_services'],
            ['type' => 'recovery-plan', 'label' => 'Recovery plans', 'table' => 'continuity_plans'],
            ['type' => 'assessment', 'label' => 'Assessments', 'table' => 'assessment_campaigns'],
        ];

        return collect($definitions)->mapWithKeys(static fn (array $definition): array => [
            $definition['type'] => $definition,
        ])->all();
    }

    private function countObjectAccessDomainRecords(string $table, string $organizationId, ?string $scopeId): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table)->where('organization_id', $organizationId);

        if (is_string($scopeId) && $scopeId !== '' && Schema::hasColumn($table, 'scope_id')) {
            $query->where(function ($inner) use ($scopeId): void {
                $inner->whereNull('scope_id')->orWhere('scope_id', $scopeId);
            });
        }

        return (int) $query->count();
    }

    private function domainObjectShellUrl(
        ScreenRenderContext $screenContext,
        string $domainType,
        string $domainId,
        string $organizationId,
        ?string $scopeId,
    ): ?string {
        $query = $this->coreScreenQuery($screenContext);
        $query['organization_id'] = $organizationId;

        if ($scopeId !== null && $scopeId !== '') {
            $query['scope_id'] = $scopeId;
        } else {
            unset($query['scope_id']);
        }

        return match ($domainType) {
            'asset' => route('core.shell.index', [...$query, 'menu' => 'plugin.asset-catalog.root', 'asset_id' => $domainId]),
            'risk' => route('core.shell.index', [...$query, 'menu' => 'plugin.risk-management.root', 'risk_id' => $domainId]),
            'control' => route('core.shell.index', [...$query, 'menu' => 'plugin.controls-catalog.root', 'control_id' => $domainId]),
            'finding' => route('core.shell.index', [...$query, 'menu' => 'plugin.findings-remediation.root', 'finding_id' => $domainId]),
            'policy' => route('core.shell.index', [...$query, 'menu' => 'plugin.policy-exceptions.root', 'policy_id' => $domainId]),
            'policy-exception' => route('core.shell.index', [...$query, 'menu' => 'plugin.policy-exceptions.exceptions', 'exception_id' => $domainId]),
            'data-flow' => route('core.shell.index', [...$query, 'menu' => 'plugin.data-flows-privacy.root', 'flow_id' => $domainId]),
            'processing-activity' => route('core.shell.index', [...$query, 'menu' => 'plugin.data-flows-privacy.activities', 'activity_id' => $domainId]),
            'continuity-service' => route('core.shell.index', [...$query, 'menu' => 'plugin.continuity-bcm.root', 'service_id' => $domainId]),
            'recovery-plan' => route('core.shell.index', [...$query, 'menu' => 'plugin.continuity-bcm.plans', 'plan_id' => $domainId]),
            'assessment' => route('core.shell.index', [...$query, 'menu' => 'plugin.assessments-audits.root', 'assessment_id' => $domainId]),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function coreScreenQuery(ScreenRenderContext $screenContext): array
    {
        $query = $screenContext->query;
        $query['principal_id'] = $screenContext->principal?->id ?? ($query['principal_id'] ?? 'principal-admin');
        $query['locale'] = $screenContext->locale;

        if ($screenContext->organizationId !== null) {
            $query['organization_id'] = $screenContext->organizationId;
        }

        if ($screenContext->scopeId !== null) {
            $query['scope_id'] = $screenContext->scopeId;
        }

        foreach ($screenContext->memberships as $membership) {
            $query['membership_ids'][] = $membership->id;
        }

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolvedVisibleMenus(ScreenRenderContext $screenContext): array
    {
        $menus = $this->app->make(MenuRegistryInterface::class);
        $labels = $this->app->make(MenuLabelResolver::class);
        $resolved = $labels->resolveTree($menus->visible(new MenuVisibilityContext(
            principal: $screenContext->principal,
            memberships: $screenContext->memberships,
            organizationId: $screenContext->organizationId,
            scopeId: $screenContext->scopeId,
        )), $screenContext->locale);
        $query = $this->coreScreenQuery($screenContext);

        return $this->decorateMenusWithShellUrls($resolved, $query);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function filterMenusByArea(array $items, string $targetArea): array
    {
        $filtered = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                continue;
            }

            $include = ($item['area'] ?? 'app') === $targetArea;

            if (! $include) {
                continue;
            }

            $filtered[] = [
                ...$item,
                'children' => $this->filterMenusByArea($item['children'] ?? [], $targetArea),
            ];
        }

        return $filtered;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private function flattenMenus(array $items): array
    {
        $map = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                continue;
            }

            $map[$item['id']] = $item;

            foreach ($this->flattenMenus($item['children'] ?? []) as $id => $child) {
                $map[$id] = $child;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function decorateMenusWithShellUrls(array $items, array $query): array
    {
        return array_map(function (array $item) use ($query): array {
            return [
                ...$item,
                'shell_url' => route('core.shell.index', [...$query, 'menu' => $item['id']]),
                'children' => $this->decorateMenusWithShellUrls($item['children'] ?? [], $query),
            ];
        }, $items);
    }

    private function scopedCount(string $table, ?string $organizationId, ?string $scopeId = null): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        if (is_string($organizationId) && $organizationId !== '' && Schema::hasColumn($table, 'organization_id')) {
            $query->where('organization_id', $organizationId);
        }

        if (is_string($scopeId) && $scopeId !== '' && Schema::hasColumn($table, 'scope_id')) {
            $query->where('scope_id', $scopeId);
        }

        return $query->count();
    }

    private function filteredScopedCount(
        string $table,
        ?string $organizationId,
        ?string $scopeId,
        ?string $principalId,
        string $domainObjectType,
    ): int {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        if (is_string($organizationId) && $organizationId !== '' && Schema::hasColumn($table, 'organization_id')) {
            $query->where('organization_id', $organizationId);
        }

        if (is_string($scopeId) && $scopeId !== '' && Schema::hasColumn($table, 'scope_id')) {
            $query->where('scope_id', $scopeId);
        }

        $visibleIds = $this->app->make(ObjectAccessService::class)->visibleObjectIds(
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            domainObjectType: $domainObjectType,
        );

        if (is_array($visibleIds)) {
            if ($visibleIds === []) {
                return 0;
            }

            $query->whereIn('id', $visibleIds);
        }

        return $query->count();
    }

    /**
     * @param  array<int, string>  $states
     */
    private function scopedStateCount(
        string $table,
        ?string $organizationId,
        ?string $scopeId,
        string $stateColumn,
        array $states,
    ): int {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $stateColumn)) {
            return 0;
        }

        $query = DB::table($table);

        if (is_string($organizationId) && $organizationId !== '' && Schema::hasColumn($table, 'organization_id')) {
            $query->where('organization_id', $organizationId);
        }

        if (is_string($scopeId) && $scopeId !== '' && Schema::hasColumn($table, 'scope_id')) {
            $query->where('scope_id', $scopeId);
        }

        return $query->whereIn($stateColumn, $states)->count();
    }

    /**
     * @param  array<int, string>  $states
     */
    private function filteredScopedStateCount(
        string $table,
        ?string $organizationId,
        ?string $scopeId,
        string $stateColumn,
        array $states,
        ?string $principalId,
        string $domainObjectType,
    ): int {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $stateColumn)) {
            return 0;
        }

        $query = DB::table($table);

        if (is_string($organizationId) && $organizationId !== '' && Schema::hasColumn($table, 'organization_id')) {
            $query->where('organization_id', $organizationId);
        }

        if (is_string($scopeId) && $scopeId !== '' && Schema::hasColumn($table, 'scope_id')) {
            $query->where('scope_id', $scopeId);
        }

        $visibleIds = $this->app->make(ObjectAccessService::class)->visibleObjectIds(
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            domainObjectType: $domainObjectType,
        );

        if (is_array($visibleIds)) {
            if ($visibleIds === []) {
                return 0;
            }

            $query->whereIn('id', $visibleIds);
        }

        return $query->whereIn($stateColumn, $states)->count();
    }
}
