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
            id: 'core.dashboard',
            owner: 'core',
            labelKey: 'core.nav.dashboard',
            routeName: 'core.shell.index',
            icon: 'pulse',
            order: 5,
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

                return [
                    'roles' => $roleRecords,
                    'grants' => $store->grantRecords(),
                    'query' => $query,
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

        $screens->register(new ScreenDefinition(
            menuId: 'core.tenancy',
            owner: 'core',
            titleKey: 'core.tenancy.screen.title',
            subtitleKey: 'core.tenancy.screen.subtitle',
            viewPath: resource_path('views/tenancy.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->tenancyScreenData($screenContext),
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
            menuId: 'core.functional-actors',
            owner: 'core',
            titleKey: 'core.functional-actors.screen.title',
            subtitleKey: 'core.functional-actors.screen.subtitle',
            viewPath: resource_path('views/functional-actors.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->functionalActorsScreenData($screenContext),
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
            'assets' => $this->scopedCount('assets', $organizationId, $scopeId),
            'risks_assessing' => $this->scopedStateCount('risks', $organizationId, $scopeId, 'state', ['assessing']),
            'controls_review' => $this->scopedStateCount('controls', $organizationId, $scopeId, 'state', ['review']),
            'findings_open' => $this->scopedStateCount('findings', $organizationId, $scopeId, 'state', ['open', 'remediating']),
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
                'workspace_url' => is_array($workspaceMenu)
                    ? route('core.shell.index', [...$query, 'menu' => $workspaceMenu['id']])
                    : null,
                'settings_url' => is_array($settingsMenu)
                    ? route('core.shell.index', [...$query, 'menu' => $settingsMenu['id']])
                    : null,
                'settings_requires_context' => $settingsMenuId !== null && ! is_array($settingsMenu),
            ];
        }, $this->app->make(PluginLifecycleManager::class)->enrichStatus($runtimePlugins));

        return [
            'query' => $query,
            'plugins' => $plugins,
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
    private function functionalActorsScreenData(ScreenRenderContext $screenContext): array
    {
        $actors = $this->app->make(FunctionalActorServiceInterface::class)->actors();
        $assignments = $this->app->make(FunctionalActorServiceInterface::class)->assignments();

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

        return [
            'query' => $this->coreScreenQuery($screenContext),
            'actors' => array_map(static fn ($actor): array => $actor->toArray(), $actors),
            'assignments' => array_map(static fn ($assignment): array => $assignment->toArray(), $assignments),
            'links' => $links,
            'metrics' => [
                'actors' => count($actors),
                'links' => count($links),
                'assignments' => count($assignments),
                'organizations' => collect($actors)->pluck('organization_id')->filter()->unique()->count(),
            ],
        ];
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
}
