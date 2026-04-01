<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Menus\Contracts\MenuRegistryInterface;
use PymeSec\Core\Menus\MenuLabelResolver;
use PymeSec\Core\Menus\MenuVisibilityContext;
use PymeSec\Core\Notifications\Contracts\NotificationServiceInterface;
use PymeSec\Core\Notifications\NotificationMailSettingsRepository;
use PymeSec\Core\Notifications\NotificationTemplateRepository;
use PymeSec\Core\Notifications\OutboundNotificationMailer;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Permissions\Contracts\AuthorizationStoreInterface;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;
use PymeSec\Core\Plugins\Contracts\PluginManagerInterface;
use PymeSec\Core\Plugins\PluginLifecycleManager;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\ReferenceData\ReferenceCatalogService;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\Tenancy\TenancyContext;
use PymeSec\Core\UI\Contracts\ScreenRegistryInterface;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\Workflows\Contracts\WorkflowRegistryInterface;
use PymeSec\Plugins\IdentityLocal\IdentityLocalRepository;

$resolvePrincipalId = static function (?string $default = null): ?string {
    $sessionPrincipalId = session('auth.principal_id');

    if (is_string($sessionPrincipalId) && $sessionPrincipalId !== '') {
        return $sessionPrincipalId;
    }

    if (app()->environment('testing')) {
        $requested = request()->input('principal_id', request()->query('principal_id'));

        if (is_string($requested) && $requested !== '') {
            return $requested;
        }
    }

    return $default;
};

$shellRouteNameForMenu = static function (?string $menuId): string {
    if (! is_string($menuId) || $menuId === '' || ! app()->bound(MenuRegistryInterface::class)) {
        return 'core.shell.index';
    }

    $flattenMenus = function (array $items) use (&$flattenMenus): array {
        $map = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                continue;
            }

            $map[$item['id']] = $item;

            foreach ($flattenMenus($item['children'] ?? []) as $id => $child) {
                $map[$id] = $child;
            }
        }

        return $map;
    };

    $definitions = $flattenMenus(app(MenuRegistryInterface::class)->all());
    $menu = $definitions[$menuId] ?? null;

    return is_array($menu) && ($menu['area'] ?? 'app') === 'admin'
        ? 'core.admin.index'
        : 'core.shell.index';
};

$renderShell = function (
    string $area,
    MenuRegistryInterface $menus,
    MenuLabelResolver $labels,
    ScreenRegistryInterface $screens,
    TenancyServiceInterface $tenancy
) use ($resolvePrincipalId) {
    if (
        Schema::hasTable('identity_local_users')
        && DB::table('identity_local_users')->count() === 0
        && Route::has('plugin.identity-local.setup')
    ) {
        return redirect()->route('plugin.identity-local.setup');
    }

    $principalId = $resolvePrincipalId();

    if ((! is_string($principalId) || $principalId === '') && Route::has('plugin.identity-local.auth.login')) {
        return redirect()->route('plugin.identity-local.auth.login');
    }

    if (! is_string($principalId) || $principalId === '') {
        $principalId = 'principal-org-a';
    }

    $availableThemes = config('ui.themes', []);
    $requestedTheme = request()->query('theme');
    $themeKey = is_string($requestedTheme) && isset($availableThemes[$requestedTheme])
        ? $requestedTheme
        : (string) config('ui.default_theme', 'atlas');
    $theme = $availableThemes[$themeKey] ?? reset($availableThemes);

    $locale = request()->query('locale', config('app.locale', 'en'));
    $locale = is_string($locale) && in_array($locale, ['en', 'es', 'fr', 'de'], true) ? $locale : 'en';
    app()->setLocale($locale);
    $requestedMembershipIds = request()->query('membership_ids', []);
    $requestedOrganizationId = request()->query('organization_id');

    if (! is_array($requestedMembershipIds)) {
        $requestedMembershipIds = [];
    }

    $tenancyContext = $tenancy->resolveContext(
        principalId: $principalId,
        requestedOrganizationId: request()->query('organization_id'),
        requestedScopeId: request()->query('scope_id'),
        requestedMembershipIds: $requestedMembershipIds,
    );
    $organizationId = $tenancyContext->organization?->id;
    $scopeId = $tenancyContext->scope?->id;
    $memberships = $tenancyContext->memberships;
    $bootstrapOrganizationId = is_string($organizationId) && $organizationId !== ''
        ? $organizationId
        : (is_string($requestedOrganizationId) && $requestedOrganizationId !== '' ? $requestedOrganizationId : null);

    if ($bootstrapOrganizationId === null && class_exists(IdentityLocalRepository::class)) {
        $bootstrapOrganizationId = app(IdentityLocalRepository::class)->firstOrganizationId();
    }

    if (
        $area === 'app'
        && is_string($bootstrapOrganizationId)
        && $bootstrapOrganizationId !== ''
        && app()->bound(AuthorizationServiceInterface::class)
        && class_exists(IdentityLocalRepository::class)
    ) {
        $isPlatformAdmin = app(AuthorizationServiceInterface::class)->authorize(new AuthorizationContext(
            principal: new PrincipalReference(
                id: $principalId,
                provider: 'demo',
            ),
            permission: 'core.roles.view',
            memberships: $memberships,
            organizationId: null,
            scopeId: null,
        ))->allowed();

        if ($isPlatformAdmin) {
            app(IdentityLocalRepository::class)
                ->ensureBootstrapOrganizationAccess($principalId, $bootstrapOrganizationId);

            $tenancyContext = $tenancy->resolveContext(
                principalId: $principalId,
                requestedOrganizationId: request()->query('organization_id'),
                requestedScopeId: request()->query('scope_id'),
                requestedMembershipIds: $requestedMembershipIds,
            );
            $organizationId = $tenancyContext->organization?->id;
            $scopeId = $tenancyContext->scope?->id;
            $memberships = $tenancyContext->memberships;
        }
    }

    $visibleMenus = $labels->resolveTree($menus->visible(new MenuVisibilityContext(
        principal: new PrincipalReference(
            id: $principalId,
            provider: 'demo',
        ),
        memberships: $memberships,
        organizationId: $organizationId,
        scopeId: $scopeId,
    )), $locale);

    $filterMenusByArea = function (array $items, string $targetArea) use (&$filterMenusByArea): array {
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
                'children' => $filterMenusByArea($item['children'] ?? [], $targetArea),
            ];
        }

        return $filtered;
    };

    $flatten = function (array $items) use (&$flatten): array {
        $map = [];

        foreach ($items as $item) {
            $map[$item['id']] = $item;

            foreach ($flatten($item['children'] ?? []) as $id => $child) {
                $map[$id] = $child;
            }
        }

        return $map;
    };

    $appMenus = $filterMenusByArea($visibleMenus, 'app');
    $adminMenus = $filterMenusByArea($visibleMenus, 'admin');
    $areaMenus = $area === 'admin' ? $adminMenus : $appMenus;
    $flatMenus = $flatten($areaMenus);
    $requestedMenuId = request()->query('menu');
    $selectedMenuId = $requestedMenuId;
    $shellError = null;

    if (is_string($requestedMenuId) && $requestedMenuId !== '') {
        if (! isset($flatMenus[$requestedMenuId])) {
            $selectedMenuId = null;
            $shellError = [
                'title' => __('core.shell.error.title'),
                'subtitle' => __('core.shell.error.unavailable_subtitle'),
                'message' => __('core.shell.error.unavailable_message', ['menu' => $requestedMenuId]),
            ];
        } elseif (! $screens->has($requestedMenuId)) {
            $fallbackChild = collect($flatMenus[$requestedMenuId]['children'] ?? [])
                ->first(fn (array $child): bool => $screens->has((string) ($child['id'] ?? '')));

            if (is_array($fallbackChild) && is_string($fallbackChild['id'] ?? null) && $fallbackChild['id'] !== '') {
                $selectedMenuId = $fallbackChild['id'];
            } else {
                $shellError = [
                    'title' => __('core.shell.error.title'),
                    'subtitle' => __('core.shell.error.unimplemented_subtitle'),
                    'message' => __('core.shell.error.unimplemented_message', ['menu' => $requestedMenuId]),
                ];
            }
        }
    }

    if ($shellError === null && (! is_string($selectedMenuId) || ! isset($flatMenus[$selectedMenuId]))) {
        $defaultMenu = $area === 'app' && isset($flatMenus['core.dashboard'])
            ? $flatMenus['core.dashboard']
            : null;

        if (! is_array($defaultMenu)) {
            $defaultMenu = collect($flatMenus)->first(
                fn (array $menu): bool => ($menu['route'] ?? null) !== null && (($menu['owner'] ?? 'core') !== 'core' || ($menu['id'] ?? null) === 'core.dashboard'),
            );
        }

        if (! is_array($defaultMenu)) {
            $defaultMenu = collect($flatMenus)->first(fn (array $menu): bool => ($menu['route'] ?? null) !== null);
        }

        $selectedMenuId = is_array($defaultMenu) ? ($defaultMenu['id'] ?? null) : null;
    }

    $baseQuery = [
        'locale' => $locale,
        'theme' => $themeKey,
    ];

    if (is_string($organizationId) && $organizationId !== '') {
        $baseQuery['organization_id'] = $organizationId;
    } elseif ($area === 'admin' && is_string($requestedOrganizationId) && $requestedOrganizationId !== '') {
        $baseQuery['organization_id'] = $requestedOrganizationId;
    }

    if (is_string($scopeId) && $scopeId !== '') {
        $baseQuery['scope_id'] = $scopeId;
    } elseif ($area === 'admin') {
        $requestedScopeId = request()->query('scope_id');

        if (is_string($requestedScopeId) && $requestedScopeId !== '') {
            $baseQuery['scope_id'] = $requestedScopeId;
        }
    }

    foreach ($memberships as $membership) {
        $baseQuery['membership_ids'][] = $membership->id;
    }

    $screenQuery = $baseQuery;

    foreach (request()->query() as $key => $value) {
        if (! is_string($key) || in_array($key, ['menu', 'theme', 'principal_id', 'organization_id', 'scope_id', 'locale', 'membership_ids'], true)) {
            continue;
        }

        $screenQuery[$key] = $value;
    }

    $currentShellRoute = $area === 'admin' ? 'core.admin.index' : 'core.shell.index';

    $decorate = function (array $items) use (&$decorate, $baseQuery, $currentShellRoute): array {
        return array_map(function (array $item) use (&$decorate, $baseQuery, $currentShellRoute): array {
            return [
                ...$item,
                'shell_url' => ($item['route'] ?? null) !== null
                    ? route($currentShellRoute, [...$baseQuery, 'menu' => $item['id']])
                    : null,
                'children' => $decorate($item['children'] ?? []),
            ];
        }, $items);
    };

    $visibleMenus = $decorate($areaMenus);
    $selectedMenu = $selectedMenuId !== null ? $flatMenus[$selectedMenuId] ?? null : null;

    $screen = null;

    if ($shellError === null && is_string($selectedMenuId) && $screens->has($selectedMenuId)) {
        $screen = $screens->render($selectedMenuId, new ScreenRenderContext(
            app: app(),
            principal: new PrincipalReference(
                id: $principalId,
                provider: 'demo',
            ),
            memberships: $memberships,
            organizationId: $organizationId,
            scopeId: $scopeId,
            locale: $locale,
            query: $screenQuery,
        ));
    }

    $contextBackUrl = request()->query('context_back_url');
    $contextLabel = request()->query('context_label');
    $sanitizeShellContextBackUrl = static function (mixed $candidate): ?string {
        if (! is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);

        if ($candidate === '' || str_starts_with($candidate, '//') || preg_match('/[\x00-\x1F\x7F]/', $candidate) === 1) {
            return null;
        }

        if (str_starts_with($candidate, '/')) {
            return preg_match('~^/(app|admin)(?:[/?#]|$)~', $candidate) === 1
                ? $candidate
                : null;
        }

        $parts = parse_url($candidate);

        if ($parts === false) {
            return null;
        }

        $path = $parts['path'] ?? null;

        if (! is_string($path) || preg_match('~^/(app|admin)(?:[/?#]|$)~', $path) !== 1) {
            return null;
        }

        $host = $parts['host'] ?? null;
        $scheme = $parts['scheme'] ?? null;

        if (is_string($host) && strcasecmp($host, request()->getHost()) !== 0) {
            return null;
        }

        if (is_string($scheme) && strcasecmp($scheme, request()->getScheme()) !== 0) {
            return null;
        }

        $normalized = $path;

        if (is_string($parts['query'] ?? null) && $parts['query'] !== '') {
            $normalized .= '?'.$parts['query'];
        }

        if (is_string($parts['fragment'] ?? null) && $parts['fragment'] !== '') {
            $normalized .= '#'.$parts['fragment'];
        }

        return $normalized;
    };
    $contextBackUrl = $sanitizeShellContextBackUrl($contextBackUrl);
    $contextLabel = is_string($contextLabel) && trim($contextLabel) !== ''
        ? trim($contextLabel)
        : null;

    if ($contextBackUrl === null) {
        $contextLabel = null;
    }

    $shellUtilityQuery = $screenQuery;

    if (is_string($selectedMenuId) && $selectedMenuId !== '') {
        $shellUtilityQuery['menu'] = $selectedMenuId;
    } else {
        unset($shellUtilityQuery['menu']);
    }

    $shellUtilityQuery['theme'] = $themeKey;
    $shellUtilityQuery['locale'] = $locale;

    if (is_string($organizationId) && $organizationId !== '') {
        $shellUtilityQuery['organization_id'] = $organizationId;
    } else {
        unset($shellUtilityQuery['organization_id']);
    }

    if (is_string($scopeId) && $scopeId !== '') {
        $shellUtilityQuery['scope_id'] = $scopeId;
    } else {
        unset($shellUtilityQuery['scope_id']);
    }

    unset($shellUtilityQuery['membership_ids']);

    foreach ($memberships as $membership) {
        $shellUtilityQuery['membership_ids'][] = $membership->id;
    }

    if ($contextBackUrl !== null) {
        $shellUtilityQuery['context_back_url'] = $contextBackUrl;
    } else {
        unset($shellUtilityQuery['context_back_url']);
    }

    if ($contextLabel !== null) {
        $shellUtilityQuery['context_label'] = $contextLabel;
    } else {
        unset($shellUtilityQuery['context_label']);
    }

    $organizationSelectorQuery = $shellUtilityQuery;
    unset($organizationSelectorQuery['organization_id']);
    $scopeSelectorQuery = $shellUtilityQuery;
    unset($scopeSelectorQuery['scope_id']);
    $localeSelectorQuery = $shellUtilityQuery;
    unset($localeSelectorQuery['locale']);

    $themeOptions = [];

    foreach ($availableThemes as $key => $definition) {
        if (! is_array($definition)) {
            continue;
        }

        $themeOptions[] = [
            'label' => (string) ($definition['label'] ?? $key),
            'active' => $key === $themeKey,
            'url' => route($currentShellRoute, [...$shellUtilityQuery, 'theme' => $key]),
        ];
    }

    $localeOptions = [
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
    ];

    $userProfile = null;

    if (Schema::hasTable('identity_local_users')) {
        $profile = DB::table('identity_local_users')
            ->where('principal_id', $principalId)
            ->first([
                'principal_id',
                'display_name',
                'username',
                'email',
                'job_title',
                'auth_provider',
            ]);

        if ($profile !== null) {
            $userProfile = (array) $profile;
        }
    }

    $appMenuMap = $flatten($appMenus);
    $adminMenuMap = $flatten($adminMenus);
    $defaultAreaMenu = static function (array $flatMenus): ?string {
        $menu = collect($flatMenus)->first(fn (array $item): bool => ($item['route'] ?? null) !== null);

        return is_array($menu) ? ($menu['id'] ?? null) : null;
    };
    $appHomeMenuId = $defaultAreaMenu($appMenuMap);
    $adminHomeMenuId = $defaultAreaMenu($adminMenuMap);
    $dashboardUrl = route('core.shell.index', [...$baseQuery, 'menu' => 'core.dashboard']);
    $supportUrl = route('core.shell.index', [...$baseQuery, 'menu' => 'core.support']);

    $debugPayload = [
        'area' => $area,
        'principal_id' => $principalId,
        'locale' => $locale,
        'theme' => $themeKey,
        'selected_menu_id' => $selectedMenuId,
        'selected_menu' => $selectedMenu,
        'organization_id' => $organizationId,
        'scope_id' => $scopeId,
        'memberships' => array_map(static fn ($membership): array => $membership->toArray(), $memberships),
        'screen' => $screen !== null
            ? [
                'title' => $screen->title,
                'subtitle' => $screen->subtitle,
                'toolbar_actions' => array_map(
                    static fn ($action): array => [
                        'label' => $action->label,
                        'url' => $action->url,
                        'variant' => $action->variant,
                        'target' => $action->target,
                    ],
                    $screen->toolbarActions,
                ),
            ]
            : null,
        'shell_error' => $shellError,
        'menus' => $visibleMenus,
        'query' => $baseQuery,
    ];

    return response()->view('shell', [
        'locale' => $locale,
        'theme' => $theme,
        'themeKey' => $themeKey,
        'themeOptions' => $themeOptions,
        'menus' => $visibleMenus,
        'selectedMenuId' => $selectedMenuId,
        'selectedMenu' => $selectedMenu,
        'screen' => $screen,
        'shellArea' => $area,
        'shellError' => $shellError,
        'menuApiUrl' => route('core.menus.index', $baseQuery),
        'debugPayloadJson' => json_encode($debugPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'currentShellRoute' => $currentShellRoute,
        'principalId' => $principalId,
        'sessionPrincipalId' => is_string(session('auth.principal_id')) ? session('auth.principal_id') : null,
        'memberships' => $memberships,
        'organizationId' => $organizationId,
        'scopeId' => $scopeId,
        'localeOptions' => $localeOptions,
        'organizationSelectorQuery' => $organizationSelectorQuery,
        'scopeSelectorQuery' => $scopeSelectorQuery,
        'localeSelectorQuery' => $localeSelectorQuery,
        'organizations' => array_map(static fn ($organization): array => $organization->toArray(), $tenancyContext->organizations),
        'scopes' => array_map(static fn ($scope): array => $scope->toArray(), $tenancyContext->scopes),
        'selectedOrganization' => $tenancyContext->organization?->toArray(),
        'selectedScope' => $tenancyContext->scope?->toArray(),
        'dashboardUrl' => $dashboardUrl,
        'supportUrl' => $supportUrl,
        'userProfile' => $userProfile,
        'adminAreaUrl' => $adminHomeMenuId !== null
            ? route('core.admin.index', [...$baseQuery, 'menu' => $adminHomeMenuId])
            : null,
        'appAreaUrl' => route('core.shell.index', $appHomeMenuId !== null
            ? [...$baseQuery, 'menu' => $appHomeMenuId]
            : $baseQuery),
        'tenancyShellUrl' => isset($adminMenuMap['core.tenancy'])
            ? route('core.admin.index', [...$baseQuery, 'menu' => 'core.tenancy'])
            : null,
        'contextBackUrl' => $contextBackUrl,
        'contextLabel' => $contextLabel,
        'coreVersion' => (string) config('plugins.core_version', '0.1.0'),
        'repositoryUrl' => (string) config('plugins.repository_url', 'https://github.com/palaueb/pymesec-compliance-auditing-system'),
        'currentYear' => now()->format('Y'),
    ]);
};

Route::get('/', function () {
    if (
        Schema::hasTable('identity_local_users')
        && DB::table('identity_local_users')->count() === 0
        && Route::has('plugin.identity-local.setup')
    ) {
        return redirect()->route('plugin.identity-local.setup');
    }

    if (is_string(session('auth.principal_id')) && session('auth.principal_id') !== '') {
        return redirect()->route('core.shell.index');
    }

    if (Route::has('plugin.identity-local.auth.login')) {
        return redirect()->route('plugin.identity-local.auth.login');
    }

    return redirect()->route('core.shell.index');
})->name('core.root');

Route::get('/app', fn (
    MenuRegistryInterface $menus,
    MenuLabelResolver $labels,
    ScreenRegistryInterface $screens,
    TenancyServiceInterface $tenancy
) => $renderShell('app', $menus, $labels, $screens, $tenancy))->name('core.shell.index');

Route::get('/admin', fn (
    MenuRegistryInterface $menus,
    MenuLabelResolver $labels,
    ScreenRegistryInterface $screens,
    TenancyServiceInterface $tenancy
) => $renderShell('admin', $menus, $labels, $screens, $tenancy))->name('core.admin.index');

Route::get('/core/plugins', function (PluginManagerInterface $plugins) {
    return response()->json([
        'core_version' => config('plugins.core_version'),
        'plugins' => $plugins->status(),
    ]);
})->name('core.plugins.index');

Route::post('/core/plugins/{pluginId}/enable', function (
    string $pluginId,
    PluginLifecycleManager $lifecycle,
    AuditTrailInterface $audit
) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'principal_id' => ['nullable', 'string', 'max:80'],
        'locale' => ['nullable', 'string', 'max:10'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
        'plugin_id' => ['nullable', 'string', 'max:80'],
        'organization_id' => ['nullable', 'string', 'max:80'],
        'scope_id' => ['nullable', 'string', 'max:80'],
        'membership_ids' => ['nullable', 'array'],
        'membership_ids.*' => ['string', 'max:80'],
    ]);

    $result = $lifecycle->enable($pluginId);
    $principalId = is_string($validated['principal_id'] ?? null) ? $validated['principal_id'] : null;

    $audit->record(new AuditRecordData(
        eventType: 'core.plugins.enable',
        outcome: $result->ok ? 'success' : 'failure',
        originComponent: 'core',
        principalId: $principalId,
        targetType: 'plugin',
        targetId: $pluginId,
        summary: [
            'reason' => $result->reason,
            'effective_before' => $result->effectiveBefore,
            'effective_after' => $result->effectiveAfter,
            ...$result->details,
        ],
        executionOrigin: 'http',
    ));

    $query = array_filter([
        'principal_id' => $principalId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : null,
        'theme' => is_string($validated['theme'] ?? null) ? $validated['theme'] : null,
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.plugins',
        'plugin_id' => is_string($validated['plugin_id'] ?? null) ? $validated['plugin_id'] : null,
        'organization_id' => is_string($validated['organization_id'] ?? null) ? $validated['organization_id'] : null,
        'scope_id' => is_string($validated['scope_id'] ?? null) ? $validated['scope_id'] : null,
    ], static fn (mixed $value): bool => is_string($value) && $value !== '');

    foreach ($validated['membership_ids'] ?? [] as $membershipId) {
        $query['membership_ids'][] = $membershipId;
    }

    return redirect()
        ->route($shellRouteNameForMenu($query['menu'] ?? null), $query)
        ->with($result->ok ? 'status' : 'error', $result->message);
})->middleware('core.permission:core.plugins.manage')->name('core.plugins.enable');

Route::post('/core/plugins/{pluginId}/disable', function (
    string $pluginId,
    PluginLifecycleManager $lifecycle,
    AuditTrailInterface $audit
) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'principal_id' => ['nullable', 'string', 'max:80'],
        'locale' => ['nullable', 'string', 'max:10'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
        'plugin_id' => ['nullable', 'string', 'max:80'],
        'organization_id' => ['nullable', 'string', 'max:80'],
        'scope_id' => ['nullable', 'string', 'max:80'],
        'membership_ids' => ['nullable', 'array'],
        'membership_ids.*' => ['string', 'max:80'],
    ]);

    $result = $lifecycle->disable($pluginId);
    $principalId = is_string($validated['principal_id'] ?? null) ? $validated['principal_id'] : null;

    $audit->record(new AuditRecordData(
        eventType: 'core.plugins.disable',
        outcome: $result->ok ? 'success' : 'failure',
        originComponent: 'core',
        principalId: $principalId,
        targetType: 'plugin',
        targetId: $pluginId,
        summary: [
            'reason' => $result->reason,
            'effective_before' => $result->effectiveBefore,
            'effective_after' => $result->effectiveAfter,
            ...$result->details,
        ],
        executionOrigin: 'http',
    ));

    $query = array_filter([
        'principal_id' => $principalId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : null,
        'theme' => is_string($validated['theme'] ?? null) ? $validated['theme'] : null,
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.plugins',
        'plugin_id' => is_string($validated['plugin_id'] ?? null) ? $validated['plugin_id'] : null,
        'organization_id' => is_string($validated['organization_id'] ?? null) ? $validated['organization_id'] : null,
        'scope_id' => is_string($validated['scope_id'] ?? null) ? $validated['scope_id'] : null,
    ], static fn (mixed $value): bool => is_string($value) && $value !== '');

    foreach ($validated['membership_ids'] ?? [] as $membershipId) {
        $query['membership_ids'][] = $membershipId;
    }

    return redirect()
        ->route($shellRouteNameForMenu($query['menu'] ?? null), $query)
        ->with($result->ok ? 'status' : 'error', $result->message);
})->middleware('core.permission:core.plugins.manage')->name('core.plugins.disable');

Route::get('/core/artifacts', function (ArtifactServiceInterface $artifacts) {
    $limit = (int) request()->query('limit', 50);
    $filters = array_filter([
        'owner_component' => request()->query('owner_component'),
        'subject_type' => request()->query('subject_type'),
        'subject_id' => request()->query('subject_id'),
        'artifact_type' => request()->query('artifact_type'),
        'principal_id' => request()->query('principal_id_filter'),
        'membership_id' => request()->query('membership_id'),
        'organization_id' => request()->query('organization_id'),
        'scope_id' => request()->query('scope_id'),
    ], static fn (mixed $value): bool => is_string($value) && $value !== '');

    return response()->json([
        'artifacts' => array_map(
            static fn ($artifact): array => $artifact->toArray(),
            $artifacts->latest($limit, $filters),
        ),
    ]);
})->middleware('core.permission:core.artifacts.view')->name('core.artifacts.index');

Route::get('/core/permissions', function (PermissionRegistryInterface $permissions) {
    return response()->json([
        'permissions' => array_map(
            static fn ($definition) => $definition->toArray(),
            $permissions->all(),
        ),
    ]);
})->name('core.permissions.index');

Route::get('/core/roles', function (AuthorizationStoreInterface $store) {
    return response()->json([
        'roles' => $store->roleRecords(),
        'grants' => $store->grantRecords(),
    ]);
})->middleware('core.permission:core.roles.view')->name('core.roles.index');

Route::post('/core/roles', function (
    AuthorizationStoreInterface $store,
    PermissionRegistryInterface $permissions,
    AuditTrailInterface $audit,
    EventBusInterface $events
) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9\\.-]*$/'],
        'label' => ['required', 'string', 'max:160'],
        'permissions' => ['nullable', 'array'],
        'permissions.*' => ['string', 'max:160'],
        'principal_id' => ['nullable', 'string', 'max:80'],
        'locale' => ['nullable', 'string', 'max:10'],
        'menu' => ['nullable', 'string', 'max:80'],
        'role_key' => ['nullable', 'string', 'max:80'],
        'grant_id' => ['nullable', 'string', 'max:120'],
    ]);

    $selectedPermissions = array_values(array_filter(
        $validated['permissions'] ?? [],
        static fn (mixed $permission): bool => is_string($permission) && $permission !== '' && $permissions->has($permission),
    ));

    $role = $store->upsertRole(
        key: (string) $validated['key'],
        label: (string) $validated['label'],
        permissions: $selectedPermissions,
    );

    $principalId = is_string($validated['principal_id'] ?? null) ? $validated['principal_id'] : null;

    $audit->record(new AuditRecordData(
        eventType: 'core.roles.upserted',
        outcome: 'success',
        originComponent: 'core',
        principalId: $principalId,
        targetType: 'role',
        targetId: $role->key,
        summary: [
            'label' => $role->label,
            'permission_count' => count($role->permissions),
        ],
        executionOrigin: 'http',
    ));

    $events->publish(new PublicEvent(
        name: 'core.roles.upserted',
        originComponent: 'core',
        payload: [
            'role_key' => $role->key,
            'label' => $role->label,
            'permission_count' => count($role->permissions),
        ],
    ));

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.roles',
        'role_key' => $role->key,
        'principal_id' => $principalId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query);
})->middleware('core.permission:core.roles.manage')->name('core.roles.store');

Route::post('/core/roles/grants', function (
    AuthorizationStoreInterface $store,
    PermissionRegistryInterface $permissions,
    AuditTrailInterface $audit,
    EventBusInterface $events
) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'target_type' => ['required', 'string', 'in:principal,membership'],
        'target_id' => ['required', 'string', 'max:120'],
        'grant_type' => ['required', 'string', 'in:role,permission'],
        'value' => ['required', 'string', 'max:160'],
        'context_type' => ['required', 'string', 'in:platform,organization,scope'],
        'organization_id' => ['nullable', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'principal_id' => ['nullable', 'string', 'max:80'],
        'locale' => ['nullable', 'string', 'max:10'],
        'menu' => ['nullable', 'string', 'max:80'],
        'role_key' => ['nullable', 'string', 'max:80'],
        'grant_id' => ['nullable', 'string', 'max:120'],
    ]);

    if ($validated['grant_type'] === 'role' && ! isset($store->roleDefinitions()[$validated['value']])) {
        abort(422, 'Unknown role value.');
    }

    if ($validated['grant_type'] === 'permission' && ! $permissions->has($validated['value'])) {
        abort(422, 'Unknown permission value.');
    }

    $organizationId = $validated['context_type'] !== 'platform'
        ? (is_string($validated['organization_id'] ?? null) && $validated['organization_id'] !== '' ? $validated['organization_id'] : null)
        : null;
    $scopeId = $validated['context_type'] === 'scope'
        ? (is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null)
        : null;

    $grant = $store->upsertGrant(
        id: null,
        targetType: $validated['target_type'],
        targetId: $validated['target_id'],
        grantType: $validated['grant_type'],
        value: $validated['value'],
        contextType: $validated['context_type'],
        organizationId: $organizationId,
        scopeId: $scopeId,
    );

    $principalId = is_string($validated['principal_id'] ?? null) ? $validated['principal_id'] : null;

    $audit->record(new AuditRecordData(
        eventType: 'core.role-grants.upserted',
        outcome: 'success',
        originComponent: 'core',
        principalId: $principalId,
        targetType: 'authorization_grant',
        targetId: (string) ($grant['id'] ?? ''),
        summary: $grant,
        executionOrigin: 'http',
    ));

    $events->publish(new PublicEvent(
        name: 'core.role-grants.upserted',
        originComponent: 'core',
        payload: $grant,
        organizationId: $organizationId,
        scopeId: $scopeId,
    ));

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.roles',
        'grant_id' => is_string($grant['id'] ?? null) ? $grant['id'] : null,
        'principal_id' => $principalId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query);
})->middleware('core.permission:core.roles.manage')->name('core.grants.store');

Route::post('/core/roles/grants/{grantId}', function (
    string $grantId,
    AuthorizationStoreInterface $store,
    PermissionRegistryInterface $permissions,
    AuditTrailInterface $audit,
    EventBusInterface $events
) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'target_type' => ['required', 'string', 'in:principal,membership'],
        'target_id' => ['required', 'string', 'max:120'],
        'grant_type' => ['required', 'string', 'in:role,permission'],
        'value' => ['required', 'string', 'max:160'],
        'context_type' => ['required', 'string', 'in:platform,organization,scope'],
        'organization_id' => ['nullable', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'principal_id' => ['nullable', 'string', 'max:80'],
        'locale' => ['nullable', 'string', 'max:10'],
        'menu' => ['nullable', 'string', 'max:80'],
        'role_key' => ['nullable', 'string', 'max:80'],
        'grant_id' => ['nullable', 'string', 'max:120'],
    ]);

    if ($validated['grant_type'] === 'role' && ! isset($store->roleDefinitions()[$validated['value']])) {
        abort(422, 'Unknown role value.');
    }

    if ($validated['grant_type'] === 'permission' && ! $permissions->has($validated['value'])) {
        abort(422, 'Unknown permission value.');
    }

    $organizationId = $validated['context_type'] !== 'platform'
        ? (is_string($validated['organization_id'] ?? null) && $validated['organization_id'] !== '' ? $validated['organization_id'] : null)
        : null;
    $scopeId = $validated['context_type'] === 'scope'
        ? (is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null)
        : null;

    $grant = $store->upsertGrant(
        id: $grantId,
        targetType: $validated['target_type'],
        targetId: $validated['target_id'],
        grantType: $validated['grant_type'],
        value: $validated['value'],
        contextType: $validated['context_type'],
        organizationId: $organizationId,
        scopeId: $scopeId,
    );

    $principalId = is_string($validated['principal_id'] ?? null) ? $validated['principal_id'] : null;

    $audit->record(new AuditRecordData(
        eventType: 'core.role-grants.upserted',
        outcome: 'success',
        originComponent: 'core',
        principalId: $principalId,
        targetType: 'authorization_grant',
        targetId: (string) ($grant['id'] ?? ''),
        summary: $grant,
        executionOrigin: 'http',
    ));

    $events->publish(new PublicEvent(
        name: 'core.role-grants.upserted',
        originComponent: 'core',
        payload: $grant,
        organizationId: $organizationId,
        scopeId: $scopeId,
    ));

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.roles',
        'grant_id' => is_string($grant['id'] ?? null) ? $grant['id'] : null,
        'principal_id' => $principalId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query);
})->middleware('core.permission:core.roles.manage')->name('core.grants.update');

Route::get('/core/menus', function (MenuRegistryInterface $menus, TenancyServiceInterface $tenancy) use ($resolvePrincipalId) {
    $principalId = $resolvePrincipalId();
    abort_unless(is_string($principalId) && $principalId !== '', 403);
    $requestedMembershipIds = request()->query('membership_ids', []);

    if (! is_array($requestedMembershipIds)) {
        $requestedMembershipIds = [];
    }

    $tenancyContext = $tenancy->resolveContext(
        principalId: is_string($principalId) ? $principalId : null,
        requestedOrganizationId: request()->query('organization_id'),
        requestedScopeId: request()->query('scope_id'),
        requestedMembershipIds: $requestedMembershipIds,
    );

    $principal = is_string($principalId) && $principalId !== ''
        ? new PrincipalReference(
            id: $principalId,
            provider: 'demo',
        )
        : null;

    return response()->json([
        'menus' => $menus->all(),
        'visible_menus' => $menus->visible(new MenuVisibilityContext(
            principal: $principal,
            memberships: $tenancyContext->memberships,
            organizationId: $tenancyContext->organization?->id,
            scopeId: $tenancyContext->scope?->id,
        )),
        'issues' => $menus->issues(),
    ]);
})->name('core.menus.index');

Route::get('/core/tenancy', function (TenancyServiceInterface $tenancy) {
    $principalId = request()->query('subject_principal_id', request()->query('principal_id', 'principal-org-a'));
    $requestedMembershipIds = request()->query('membership_ids', []);

    if (! is_array($requestedMembershipIds)) {
        $requestedMembershipIds = [];
    }

    $context = $tenancy->resolveContext(
        principalId: is_string($principalId) ? $principalId : null,
        requestedOrganizationId: request()->query('organization_id'),
        requestedScopeId: request()->query('scope_id'),
        requestedMembershipIds: $requestedMembershipIds,
    );

    return response()->json([
        'principal_id' => $context->principalId,
        'organizations' => array_map(static fn ($organization): array => $organization->toArray(), $context->organizations),
        'selected_organization' => $context->organization?->toArray(),
        'scopes' => array_map(static fn ($scope): array => $scope->toArray(), $context->scopes),
        'selected_scope' => $context->scope?->toArray(),
        'memberships' => array_map(static fn ($membership): array => $membership->toArray(), $context->memberships),
    ]);
})->middleware('core.permission:core.tenancy.view')->name('core.tenancy.index');

Route::get('/core/reference-data', function (ReferenceCatalogService $catalogs) {
    $organizationId = request()->query('organization_id');

    return response()->json([
        'organization_id' => $organizationId,
        'catalogs' => array_map(function (array $catalog) use ($catalogs, $organizationId): array {
            return [
                ...$catalog,
                'options' => $catalogs->optionRows($catalog['key'], is_string($organizationId) ? $organizationId : null),
            ];
        }, $catalogs->manageableCatalogs()),
    ]);
})->middleware('core.permission:core.reference-data.view')->name('core.reference-data.index');

Route::post('/core/reference-data/entries', function (ReferenceCatalogService $catalogs) use ($shellRouteNameForMenu) {
    $catalogKeys = array_map(static fn (array $catalog): string => $catalog['key'], $catalogs->manageableCatalogs());

    $validated = request()->validate([
        'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
        'catalog_key' => ['required', 'string', Rule::in($catalogKeys)],
        'option_key' => [
            'required',
            'string',
            'max:120',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::unique('reference_catalog_entries', 'option_key')
                ->where(fn ($query) => $query
                    ->where('organization_id', request()->input('organization_id'))
                    ->where('catalog_key', request()->input('catalog_key'))),
        ],
        'label' => ['required', 'string', 'max:160'],
        'description' => ['nullable', 'string', 'max:1000'],
        'sort_order' => ['required', 'integer', 'min:1', 'max:10000'],
        'principal_id' => ['nullable', 'string', 'max:120'],
        'locale' => ['nullable', 'string', 'max:12'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
    ]);

    $entry = $catalogs->createManagedEntry(
        $validated,
        is_string($validated['principal_id'] ?? null) && $validated['principal_id'] !== '' ? $validated['principal_id'] : null,
    );

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.reference-data',
        'principal_id' => is_string($validated['principal_id'] ?? null) ? $validated['principal_id'] : null,
        'organization_id' => $validated['organization_id'],
        'catalog_key' => $validated['catalog_key'],
        'entry_id' => $entry['id'] ?? null,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
        'theme' => is_string($validated['theme'] ?? null) ? $validated['theme'] : null,
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Catalog option saved.');
})->middleware('core.permission:core.reference-data.manage')->name('core.reference-data.entries.store');

Route::post('/core/reference-data/entries/{entryId}', function (ReferenceCatalogService $catalogs, string $entryId) use ($shellRouteNameForMenu) {
    $existing = $catalogs->findManagedEntry($entryId);
    abort_if($existing === null, 404);

    $catalogKeys = array_map(static fn (array $catalog): string => $catalog['key'], $catalogs->manageableCatalogs());

    $validated = request()->validate([
        'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
        'catalog_key' => ['required', 'string', Rule::in($catalogKeys)],
        'option_key' => [
            'required',
            'string',
            'max:120',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::unique('reference_catalog_entries', 'option_key')
                ->ignore($entryId, 'id')
                ->where(fn ($query) => $query
                    ->where('organization_id', request()->input('organization_id'))
                    ->where('catalog_key', request()->input('catalog_key'))),
        ],
        'label' => ['required', 'string', 'max:160'],
        'description' => ['nullable', 'string', 'max:1000'],
        'sort_order' => ['required', 'integer', 'min:1', 'max:10000'],
        'principal_id' => ['nullable', 'string', 'max:120'],
        'locale' => ['nullable', 'string', 'max:12'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
    ]);

    $catalogs->updateManagedEntry(
        $entryId,
        $validated,
        is_string($validated['principal_id'] ?? null) && $validated['principal_id'] !== '' ? $validated['principal_id'] : null,
    );

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.reference-data',
        'principal_id' => is_string($validated['principal_id'] ?? null) ? $validated['principal_id'] : null,
        'organization_id' => $validated['organization_id'],
        'catalog_key' => $validated['catalog_key'],
        'entry_id' => $entryId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
        'theme' => is_string($validated['theme'] ?? null) ? $validated['theme'] : null,
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Catalog option updated.');
})->middleware('core.permission:core.reference-data.manage')->name('core.reference-data.entries.update');

Route::post('/core/reference-data/entries/{entryId}/archive', function (ReferenceCatalogService $catalogs, string $entryId) use ($shellRouteNameForMenu) {
    $entry = $catalogs->findManagedEntry($entryId);
    abort_if($entry === null, 404);
    abort_unless($catalogs->archiveManagedEntry($entryId, request()->input('principal_id')), 404);

    $query = array_filter([
        'menu' => request()->input('menu', 'core.reference-data'),
        'principal_id' => request()->input('principal_id'),
        'organization_id' => request()->input('organization_id', $entry['organization_id']),
        'catalog_key' => request()->input('catalog_key', $entry['catalog_key']),
        'locale' => request()->input('locale', 'en'),
        'theme' => request()->input('theme'),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Catalog option archived.');
})->middleware('core.permission:core.reference-data.manage')->name('core.reference-data.entries.archive');

Route::post('/core/reference-data/entries/{entryId}/activate', function (ReferenceCatalogService $catalogs, string $entryId) use ($shellRouteNameForMenu) {
    $entry = $catalogs->findManagedEntry($entryId);
    abort_if($entry === null, 404);
    abort_unless($catalogs->activateManagedEntry($entryId, request()->input('principal_id')), 404);

    $query = array_filter([
        'menu' => request()->input('menu', 'core.reference-data'),
        'principal_id' => request()->input('principal_id'),
        'organization_id' => request()->input('organization_id', $entry['organization_id']),
        'catalog_key' => request()->input('catalog_key', $entry['catalog_key']),
        'entry_id' => $entryId,
        'locale' => request()->input('locale', 'en'),
        'theme' => request()->input('theme'),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Catalog option reactivated.');
})->middleware('core.permission:core.reference-data.manage')->name('core.reference-data.entries.activate');

Route::post('/core/tenancy/organizations', function (TenancyServiceInterface $tenancy) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'name' => ['required', 'string', 'max:160'],
        'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('organizations', 'slug')],
        'default_locale' => ['required', 'string', 'in:en,es,fr,de'],
        'default_timezone' => ['required', 'string', 'max:64'],
        'principal_id' => ['nullable', 'string', 'max:80'],
        'locale' => ['nullable', 'string', 'max:10'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
    ]);

    $organization = $tenancy->createOrganization($validated);

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.tenancy',
        'principal_id' => is_string($validated['principal_id'] ?? null) ? $validated['principal_id'] : null,
        'organization_id' => $organization->id,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
        'theme' => is_string($validated['theme'] ?? null) ? $validated['theme'] : null,
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Organization created.');
})->middleware('core.permission:core.tenancy.manage')->name('core.tenancy.organizations.store');

Route::post('/core/tenancy/organizations/{organizationId}', function (TenancyServiceInterface $tenancy, string $organizationId) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'name' => ['required', 'string', 'max:160'],
        'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('organizations', 'slug')->ignore($organizationId, 'id')],
        'default_locale' => ['required', 'string', 'in:en,es,fr,de'],
        'default_timezone' => ['required', 'string', 'max:64'],
        'principal_id' => ['nullable', 'string', 'max:80'],
        'locale' => ['nullable', 'string', 'max:10'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
    ]);

    abort_if($tenancy->updateOrganization($organizationId, $validated) === null, 404);

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.tenancy',
        'principal_id' => is_string($validated['principal_id'] ?? null) ? $validated['principal_id'] : null,
        'organization_id' => $organizationId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
        'theme' => is_string($validated['theme'] ?? null) ? $validated['theme'] : null,
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Organization updated.');
})->middleware('core.permission:core.tenancy.manage')->name('core.tenancy.organizations.update');

Route::post('/core/tenancy/organizations/{organizationId}/archive', function (TenancyServiceInterface $tenancy, string $organizationId) use ($shellRouteNameForMenu) {
    abort_unless($tenancy->archiveOrganization($organizationId), 404);

    $query = array_filter([
        'menu' => request()->input('menu', 'core.tenancy'),
        'principal_id' => request()->input('principal_id'),
        'locale' => request()->input('locale', 'en'),
        'theme' => request()->input('theme'),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Organization archived.');
})->middleware('core.permission:core.tenancy.manage')->name('core.tenancy.organizations.archive');

Route::post('/core/tenancy/organizations/{organizationId}/activate', function (TenancyServiceInterface $tenancy, string $organizationId) use ($shellRouteNameForMenu) {
    abort_unless($tenancy->activateOrganization($organizationId), 404);

    $query = array_filter([
        'menu' => request()->input('menu', 'core.tenancy'),
        'principal_id' => request()->input('principal_id'),
        'organization_id' => $organizationId,
        'locale' => request()->input('locale', 'en'),
        'theme' => request()->input('theme'),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Organization activated.');
})->middleware('core.permission:core.tenancy.manage')->name('core.tenancy.organizations.activate');

Route::post('/core/tenancy/scopes', function (TenancyServiceInterface $tenancy) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
        'name' => ['required', 'string', 'max:160'],
        'slug' => [
            'nullable',
            'string',
            'max:160',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::unique('scopes', 'slug')->where(fn ($query) => $query->where('organization_id', request()->input('organization_id'))),
        ],
        'description' => ['nullable', 'string', 'max:1000'],
        'principal_id' => ['nullable', 'string', 'max:80'],
        'locale' => ['nullable', 'string', 'max:10'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
    ]);

    $scope = $tenancy->createScope($validated);

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.tenancy',
        'principal_id' => is_string($validated['principal_id'] ?? null) ? $validated['principal_id'] : null,
        'organization_id' => $scope->organizationId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
        'theme' => is_string($validated['theme'] ?? null) ? $validated['theme'] : null,
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Scope created.');
})->middleware('core.permission:core.tenancy.manage')->name('core.tenancy.scopes.store');

Route::post('/core/tenancy/scopes/{scopeId}', function (TenancyServiceInterface $tenancy, string $scopeId) use ($shellRouteNameForMenu) {
    $existingScope = DB::table('scopes')->where('id', $scopeId)->first(['organization_id']);
    abort_if($existingScope === null, 404);

    $validated = request()->validate([
        'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
        'name' => ['required', 'string', 'max:160'],
        'slug' => [
            'required',
            'string',
            'max:160',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::unique('scopes', 'slug')
                ->ignore($scopeId, 'id')
                ->where(fn ($query) => $query->where('organization_id', request()->input('organization_id'))),
        ],
        'description' => ['nullable', 'string', 'max:1000'],
        'principal_id' => ['nullable', 'string', 'max:80'],
        'locale' => ['nullable', 'string', 'max:10'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
    ]);

    abort_if($tenancy->updateScope($scopeId, $validated) === null, 404);

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.tenancy',
        'principal_id' => is_string($validated['principal_id'] ?? null) ? $validated['principal_id'] : null,
        'organization_id' => is_string($validated['organization_id'] ?? null) ? $validated['organization_id'] : (string) $existingScope->organization_id,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
        'theme' => is_string($validated['theme'] ?? null) ? $validated['theme'] : null,
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Scope updated.');
})->middleware('core.permission:core.tenancy.manage')->name('core.tenancy.scopes.update');

Route::post('/core/tenancy/scopes/{scopeId}/archive', function (TenancyServiceInterface $tenancy, string $scopeId) use ($shellRouteNameForMenu) {
    abort_unless($tenancy->archiveScope($scopeId), 404);

    $query = array_filter([
        'menu' => request()->input('menu', 'core.tenancy'),
        'principal_id' => request()->input('principal_id'),
        'organization_id' => request()->input('organization_id'),
        'locale' => request()->input('locale', 'en'),
        'theme' => request()->input('theme'),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Scope archived.');
})->middleware('core.permission:core.tenancy.manage')->name('core.tenancy.scopes.archive');

Route::post('/core/tenancy/scopes/{scopeId}/activate', function (TenancyServiceInterface $tenancy, string $scopeId) use ($shellRouteNameForMenu) {
    abort_unless($tenancy->activateScope($scopeId), 404);

    $query = array_filter([
        'menu' => request()->input('menu', 'core.tenancy'),
        'principal_id' => request()->input('principal_id'),
        'organization_id' => request()->input('organization_id'),
        'locale' => request()->input('locale', 'en'),
        'theme' => request()->input('theme'),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Scope activated.');
})->middleware('core.permission:core.tenancy.manage')->name('core.tenancy.scopes.activate');

Route::get('/core/functional-actors', function (FunctionalActorServiceInterface $actors, TenancyServiceInterface $tenancy) {
    $principalId = request()->query('subject_principal_id', request()->query('principal_id'));
    $organizationId = request()->query('organization_id');
    $scopeId = request()->query('scope_id');

    if (! is_string($organizationId) || $organizationId === '') {
        $resolved = $tenancy->resolveContext(
            principalId: is_string($principalId) ? $principalId : null,
            requestedOrganizationId: null,
            requestedScopeId: is_string($scopeId) ? $scopeId : null,
        );

        $organizationId = $resolved->organization?->id;
        $scopeId = $resolved->scope?->id ?? (is_string($scopeId) ? $scopeId : null);
    }

    $domainObjectType = request()->query('domain_object_type');
    $domainObjectId = request()->query('domain_object_id');
    $assignments = [];

    if (
        is_string($domainObjectType) && $domainObjectType !== ''
        && is_string($domainObjectId) && $domainObjectId !== ''
        && is_string($organizationId) && $organizationId !== ''
    ) {
        $assignments = array_map(
            static fn ($assignment): array => $assignment->toArray(),
            $actors->assignmentsFor($domainObjectType, $domainObjectId, $organizationId, is_string($scopeId) ? $scopeId : null),
        );
    }

    return response()->json([
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'actors' => array_map(
            static fn ($actor): array => $actor->toArray(),
            $actors->actors(is_string($organizationId) ? $organizationId : null, is_string($scopeId) ? $scopeId : null),
        ),
        'principal_links' => is_string($principalId) && $principalId !== ''
            ? array_map(static fn ($link): array => $link->toArray(), $actors->linksForPrincipal($principalId, is_string($organizationId) ? $organizationId : null))
            : [],
        'principal_actors' => is_string($principalId) && $principalId !== ''
            ? array_map(static fn ($actor): array => $actor->toArray(), $actors->actorsForPrincipal($principalId, is_string($organizationId) ? $organizationId : null))
            : [],
        'assignments' => $assignments,
    ]);
})->middleware('core.permission:core.functional-actors.view')->name('core.functional-actors.index');

Route::post('/core/functional-actors', function (FunctionalActorServiceInterface $actors) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'display_name' => ['required', 'string', 'max:160'],
        'kind' => ['required', 'string', 'max:40'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'principal_id' => ['nullable', 'string', 'max:120'],
        'locale' => ['nullable', 'string', 'max:12'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
        'subject_principal_id' => ['nullable', 'string', 'max:120'],
    ]);

    $actor = $actors->createActor(
        provider: 'manual',
        kind: $validated['kind'],
        displayName: $validated['display_name'],
        organizationId: $validated['organization_id'],
        scopeId: is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
        metadata: [],
        createdByPrincipalId: is_string($validated['principal_id'] ?? null) && $validated['principal_id'] !== '' ? $validated['principal_id'] : null,
    );

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.functional-actors',
        'actor_id' => $actor->id,
        'subject_principal_id' => $validated['subject_principal_id'] ?? null,
        'principal_id' => $validated['principal_id'] ?? null,
        'organization_id' => $validated['organization_id'],
        'scope_id' => $validated['scope_id'] ?? null,
        'locale' => $validated['locale'] ?? 'en',
        'theme' => $validated['theme'] ?? null,
        'membership_ids' => request()->input('membership_ids', []),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Functional profile created.');
})->middleware('core.permission:core.functional-actors.manage')->name('core.functional-actors.store');

Route::post('/core/functional-actors/links', function (FunctionalActorServiceInterface $actors) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'actor_id' => ['required', 'string', 'max:120'],
        'subject_principal_id' => ['required', 'string', 'max:120'],
        'organization_id' => ['required', 'string', 'max:64'],
        'principal_id' => ['nullable', 'string', 'max:120'],
        'locale' => ['nullable', 'string', 'max:12'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
    ]);

    abort_if($actors->findActor($validated['actor_id']) === null, 404);

    $actors->linkPrincipal(
        principalId: $validated['subject_principal_id'],
        actorId: $validated['actor_id'],
        organizationId: $validated['organization_id'],
        linkedByPrincipalId: is_string($validated['principal_id'] ?? null) && $validated['principal_id'] !== '' ? $validated['principal_id'] : null,
    );

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.functional-actors',
        'actor_id' => $validated['actor_id'],
        'subject_principal_id' => $validated['subject_principal_id'],
        'principal_id' => $validated['principal_id'] ?? null,
        'organization_id' => $validated['organization_id'],
        'locale' => $validated['locale'] ?? 'en',
        'theme' => $validated['theme'] ?? null,
        'membership_ids' => request()->input('membership_ids', []),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Person linked to functional profile.');
})->middleware('core.permission:core.functional-actors.manage')->name('core.functional-actors.links.store');

Route::post('/core/functional-actors/assignments', function (FunctionalActorServiceInterface $actors) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'actor_id' => ['required', 'string', 'max:120'],
        'subject_key' => ['required', 'string', 'max:255'],
        'assignment_type' => ['required', 'string', 'max:40'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'principal_id' => ['nullable', 'string', 'max:120'],
        'locale' => ['nullable', 'string', 'max:12'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
    ]);

    abort_if($actors->findActor($validated['actor_id']) === null, 404);

    [$domainObjectType, $domainObjectId] = array_pad(explode('::', $validated['subject_key'], 2), 2, null);

    if (! is_string($domainObjectType) || $domainObjectType === '' || ! is_string($domainObjectId) || $domainObjectId === '') {
        return back()->withErrors(['subject_key' => 'Choose a valid workspace item.'])->withInput();
    }

    if ($validated['assignment_type'] === 'owner') {
        $actors->syncSingleAssignment(
            actorId: $validated['actor_id'],
            domainObjectType: $domainObjectType,
            domainObjectId: $domainObjectId,
            assignmentType: $validated['assignment_type'],
            organizationId: $validated['organization_id'],
            scopeId: is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
            metadata: ['source' => 'functional-actors-admin'],
            assignedByPrincipalId: is_string($validated['principal_id'] ?? null) && $validated['principal_id'] !== '' ? $validated['principal_id'] : null,
        );
    } else {
        $actors->assignActor(
            actorId: $validated['actor_id'],
            domainObjectType: $domainObjectType,
            domainObjectId: $domainObjectId,
            assignmentType: $validated['assignment_type'],
            organizationId: $validated['organization_id'],
            scopeId: is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
            metadata: ['source' => 'functional-actors-admin'],
            assignedByPrincipalId: is_string($validated['principal_id'] ?? null) && $validated['principal_id'] !== '' ? $validated['principal_id'] : null,
        );
    }

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.functional-actors',
        'actor_id' => $validated['actor_id'],
        'principal_id' => $validated['principal_id'] ?? null,
        'organization_id' => $validated['organization_id'],
        'scope_id' => $validated['scope_id'] ?? null,
        'locale' => $validated['locale'] ?? 'en',
        'theme' => $validated['theme'] ?? null,
        'membership_ids' => request()->input('membership_ids', []),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)->with('status', 'Responsibility assigned.');
})->middleware('core.permission:core.functional-actors.manage')->name('core.functional-actors.assignments.store');

Route::post('/core/object-access/assignments', function (FunctionalActorServiceInterface $actors) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'actor_id' => ['required', 'string', 'max:120'],
        'subject_key' => ['required', 'string', 'max:255'],
        'assignment_type' => ['required', 'string', 'max:40'],
        'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'subject_principal_id' => ['nullable', 'string', 'max:120'],
        'principal_id' => ['nullable', 'string', 'max:120'],
        'locale' => ['nullable', 'string', 'max:12'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
    ]);

    abort_if($actors->findActor($validated['actor_id']) === null, 404);

    [$domainObjectType, $domainObjectId] = array_pad(explode('::', $validated['subject_key'], 2), 2, null);

    if (! is_string($domainObjectType) || $domainObjectType === '' || ! is_string($domainObjectId) || $domainObjectId === '') {
        return back()->withErrors(['subject_key' => 'Choose a valid workspace item.'])->withInput();
    }

    if ($validated['assignment_type'] === 'owner') {
        $actors->syncSingleAssignment(
            actorId: $validated['actor_id'],
            domainObjectType: $domainObjectType,
            domainObjectId: $domainObjectId,
            assignmentType: $validated['assignment_type'],
            organizationId: $validated['organization_id'],
            scopeId: is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
            metadata: ['source' => 'object-access-admin'],
            assignedByPrincipalId: is_string($validated['principal_id'] ?? null) && $validated['principal_id'] !== '' ? $validated['principal_id'] : null,
        );
    } else {
        $actors->assignActor(
            actorId: $validated['actor_id'],
            domainObjectType: $domainObjectType,
            domainObjectId: $domainObjectId,
            assignmentType: $validated['assignment_type'],
            organizationId: $validated['organization_id'],
            scopeId: is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
            metadata: ['source' => 'object-access-admin'],
            assignedByPrincipalId: is_string($validated['principal_id'] ?? null) && $validated['principal_id'] !== '' ? $validated['principal_id'] : null,
        );
    }

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.object-access',
        'subject_principal_id' => is_string($validated['subject_principal_id'] ?? null) && $validated['subject_principal_id'] !== ''
            ? $validated['subject_principal_id']
            : null,
        'subject_key' => $validated['subject_key'],
        'principal_id' => $validated['principal_id'] ?? null,
        'organization_id' => $validated['organization_id'],
        'scope_id' => $validated['scope_id'] ?? null,
        'locale' => $validated['locale'] ?? 'en',
        'theme' => $validated['theme'] ?? null,
        'membership_ids' => request()->input('membership_ids', []),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)
        ->with('status', 'Object access assignment updated.');
})->middleware('core.permission:core.functional-actors.manage')->name('core.object-access.assignments.store');

Route::post('/core/object-access/assignments/{assignmentId}/deactivate', function (
    FunctionalActorServiceInterface $actors,
    string $assignmentId
) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'subject_key' => ['nullable', 'string', 'max:255'],
        'subject_principal_id' => ['nullable', 'string', 'max:120'],
        'principal_id' => ['nullable', 'string', 'max:120'],
        'locale' => ['nullable', 'string', 'max:12'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
    ]);

    $record = DB::table('functional_assignments')->where('id', $assignmentId)->first();
    abort_if($record === null, 404);
    abort_if((string) ($record->organization_id ?? '') !== $validated['organization_id'], 404);

    $actors->deactivateAssignment(
        assignmentId: $assignmentId,
        deactivatedByPrincipalId: is_string($validated['principal_id'] ?? null) && $validated['principal_id'] !== '' ? $validated['principal_id'] : null,
    );

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.object-access',
        'subject_principal_id' => is_string($validated['subject_principal_id'] ?? null) && $validated['subject_principal_id'] !== ''
            ? $validated['subject_principal_id']
            : null,
        'subject_key' => is_string($validated['subject_key'] ?? null) && $validated['subject_key'] !== ''
            ? $validated['subject_key']
            : null,
        'principal_id' => $validated['principal_id'] ?? null,
        'organization_id' => $validated['organization_id'],
        'scope_id' => $validated['scope_id'] ?? null,
        'locale' => $validated['locale'] ?? 'en',
        'theme' => $validated['theme'] ?? null,
        'membership_ids' => request()->input('membership_ids', []),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)
        ->with('status', 'Object access assignment removed.');
})->middleware('core.permission:core.functional-actors.manage')->name('core.object-access.assignments.deactivate');

Route::get('/core/events', function (EventBusInterface $events) {
    $limit = request()->integer('limit', 50);
    $filters = array_filter([
        'name' => request()->query('name'),
        'origin_component' => request()->query('origin_component'),
        'organization_id' => request()->query('organization_id'),
        'scope_id' => request()->query('scope_id'),
    ], static fn (mixed $value): bool => is_string($value) && $value !== '');

    return response()->json([
        'events' => array_map(
            static fn ($event): array => $event->toArray(),
            $events->latest($limit, $filters),
        ),
    ]);
})->middleware('core.permission:core.events.view')->name('core.events.index');

Route::get('/core/notifications', function (NotificationServiceInterface $notifications) {
    $limit = request()->integer('limit', 50);
    $filters = array_filter([
        'status' => request()->query('status'),
        'type' => request()->query('type'),
        'principal_id' => request()->query('recipient_principal_id'),
        'functional_actor_id' => request()->query('functional_actor_id'),
        'organization_id' => request()->query('organization_id'),
        'scope_id' => request()->query('scope_id'),
        'source_event_name' => request()->query('source_event_name'),
    ], static fn (mixed $value): bool => is_string($value) && $value !== '');

    return response()->json([
        'notifications' => array_map(
            static fn ($notification): array => $notification->toArray(),
            $notifications->latest($limit, $filters),
        ),
    ]);
})->middleware('core.permission:core.notifications.view')->name('core.notifications.index');

Route::post('/core/notifications/settings', function (
    NotificationMailSettingsRepository $settings,
    AuditTrailInterface $audit,
    EventBusInterface $events
) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
        'email_enabled' => ['nullable', 'boolean'],
        'smtp_host' => ['nullable', 'string', 'max:190', 'required_if:email_enabled,1'],
        'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:email_enabled,1'],
        'smtp_encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl', 'none'])],
        'smtp_username' => ['nullable', 'string', 'max:190'],
        'smtp_password' => ['nullable', 'string', 'max:500'],
        'from_address' => ['nullable', 'email:rfc', 'max:190', 'required_if:email_enabled,1'],
        'from_name' => ['nullable', 'string', 'max:190'],
        'reply_to_address' => ['nullable', 'email:rfc', 'max:190'],
        'principal_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'locale' => ['nullable', 'string', 'max:12'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
    ]);

    $principalId = is_string($validated['principal_id'] ?? null) && $validated['principal_id'] !== ''
        ? $validated['principal_id']
        : null;
    $organizationId = $validated['organization_id'];
    $scopeId = is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== ''
        ? $validated['scope_id']
        : null;

    $saved = $settings->upsert($organizationId, $validated, $principalId);

    $audit->record(new AuditRecordData(
        eventType: 'core.notifications.mail-settings.updated',
        outcome: 'success',
        originComponent: 'core',
        principalId: $principalId,
        organizationId: $organizationId,
        scopeId: $scopeId,
        targetType: 'notification-mail-settings',
        targetId: (string) ($saved['id'] ?? $organizationId),
        summary: [
            'email_enabled' => (bool) ($saved['email_enabled'] ?? false),
            'smtp_host' => is_string($saved['smtp_host'] ?? null) && $saved['smtp_host'] !== '' ? $saved['smtp_host'] : null,
            'smtp_port' => $saved['smtp_port'] ?? null,
            'smtp_encryption' => is_string($saved['smtp_encryption'] ?? null) && $saved['smtp_encryption'] !== '' ? $saved['smtp_encryption'] : 'none',
            'has_password' => (bool) ($saved['has_password'] ?? false),
        ],
        executionOrigin: 'web',
    ));

    $events->publish(new PublicEvent(
        name: 'core.notifications.mail-settings.updated',
        originComponent: 'core',
        organizationId: $organizationId,
        scopeId: $scopeId,
        payload: [
            'email_enabled' => (bool) ($saved['email_enabled'] ?? false),
            'has_password' => (bool) ($saved['has_password'] ?? false),
        ],
    ));

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.notifications',
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => $scopeId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
        'theme' => is_string($validated['theme'] ?? null) ? $validated['theme'] : null,
        'membership_ids' => request()->input('membership_ids', []),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)
        ->with('status', 'Notification delivery settings saved.');
})->middleware('core.permission:core.notifications.manage')->name('core.notifications.settings.update');

Route::post('/core/notifications/test-email', function (
    NotificationMailSettingsRepository $settings,
    OutboundNotificationMailer $mailer,
    AuditTrailInterface $audit,
    EventBusInterface $events
) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
        'recipient_principal_id' => [
            'required',
            'string',
            'max:120',
            Rule::exists('identity_local_users', 'principal_id')->where(
                fn ($query) => $query->where('organization_id', request()->input('organization_id'))->where('is_active', true)
            ),
        ],
        'principal_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'locale' => ['nullable', 'string', 'max:12'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
    ]);

    $organizationId = $validated['organization_id'];
    $principalId = is_string($validated['principal_id'] ?? null) && $validated['principal_id'] !== ''
        ? $validated['principal_id']
        : null;
    $scopeId = is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== ''
        ? $validated['scope_id']
        : null;

    $deliveryConfig = $settings->deliveryConfigForOrganization($organizationId);

    if ($deliveryConfig === null) {
        return back()->withErrors([
            'recipient_principal_id' => 'Enable and save outbound email before sending a test message.',
        ])->withInput();
    }

    $recipientEmail = DB::table('identity_local_users')
        ->where('principal_id', $validated['recipient_principal_id'])
        ->where('organization_id', $organizationId)
        ->where('is_active', true)
        ->value('email');

    if (! is_string($recipientEmail) || trim($recipientEmail) === '') {
        return back()->withErrors([
            'recipient_principal_id' => 'The selected person does not have an email address.',
        ])->withInput();
    }

    $mailer->sendTestMessage($deliveryConfig, trim($recipientEmail), $organizationId);
    $settings->markTested($organizationId);

    $audit->record(new AuditRecordData(
        eventType: 'core.notifications.test-email.sent',
        outcome: 'success',
        originComponent: 'core',
        principalId: $principalId,
        organizationId: $organizationId,
        scopeId: $scopeId,
        targetType: 'notification-mail-settings',
        targetId: $organizationId,
        summary: [
            'recipient_principal_id' => $validated['recipient_principal_id'],
        ],
        executionOrigin: 'web',
    ));

    $events->publish(new PublicEvent(
        name: 'core.notifications.test-email.sent',
        originComponent: 'core',
        organizationId: $organizationId,
        scopeId: $scopeId,
        payload: [
            'recipient_principal_id' => $validated['recipient_principal_id'],
        ],
    ));

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.notifications',
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => $scopeId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
        'theme' => is_string($validated['theme'] ?? null) ? $validated['theme'] : null,
        'membership_ids' => request()->input('membership_ids', []),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)
        ->with('status', 'Test email sent.');
})->middleware('core.permission:core.notifications.manage')->name('core.notifications.test.send');

Route::post('/core/notifications/templates', function (
    NotificationTemplateRepository $templates,
    AuditTrailInterface $audit,
    EventBusInterface $events
) use ($shellRouteNameForMenu) {
    $validated = request()->validate([
        'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
        'notification_type' => ['required', 'string', 'max:190'],
        'is_active' => ['nullable', 'boolean'],
        'title_template' => ['nullable', 'string', 'max:1000'],
        'body_template' => ['nullable', 'string', 'max:10000'],
        'principal_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'locale' => ['nullable', 'string', 'max:12'],
        'theme' => ['nullable', 'string', 'max:40'],
        'menu' => ['nullable', 'string', 'max:80'],
    ]);

    $organizationId = $validated['organization_id'];
    $notificationType = $validated['notification_type'];
    $principalId = is_string($validated['principal_id'] ?? null) && $validated['principal_id'] !== ''
        ? $validated['principal_id']
        : null;
    $scopeId = is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== ''
        ? $validated['scope_id']
        : null;

    $saved = $templates->upsert(
        organizationId: $organizationId,
        notificationType: $notificationType,
        data: $validated,
        updatedByPrincipalId: $principalId,
    );

    $audit->record(new AuditRecordData(
        eventType: 'core.notifications.templates.updated',
        outcome: 'success',
        originComponent: 'core',
        principalId: $principalId,
        organizationId: $organizationId,
        scopeId: $scopeId,
        targetType: 'notification-template',
        targetId: (string) ($saved['id'] ?? $notificationType),
        summary: [
            'notification_type' => $notificationType,
            'is_active' => (bool) ($saved['is_active'] ?? false),
        ],
        executionOrigin: 'web',
    ));

    $events->publish(new PublicEvent(
        name: 'core.notifications.templates.updated',
        originComponent: 'core',
        organizationId: $organizationId,
        scopeId: $scopeId,
        payload: [
            'notification_type' => $notificationType,
            'is_active' => (bool) ($saved['is_active'] ?? false),
        ],
    ));

    $query = array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.notifications',
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => $scopeId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
        'theme' => is_string($validated['theme'] ?? null) ? $validated['theme'] : null,
        'template_type' => $notificationType,
        'membership_ids' => request()->input('membership_ids', []),
    ]);

    return redirect()->route($shellRouteNameForMenu($query['menu'] ?? null), $query)
        ->with('status', 'Notification template saved.');
})->middleware('core.permission:core.notifications.manage')->name('core.notifications.templates.update');

Route::get('/core/workflows', function (WorkflowRegistryInterface $workflows) {
    return response()->json([
        'workflows' => array_map(static fn ($workflow): array => [
            'key' => $workflow->key,
            'owner' => $workflow->owner,
            'label' => $workflow->label,
            'initial_state' => $workflow->initialState,
            'states' => $workflow->states,
            'transitions' => array_map(static fn ($transition): array => [
                'key' => $transition->key,
                'from_states' => $transition->fromStates,
                'to_state' => $transition->toState,
                'permission' => $transition->permission,
            ], $workflow->transitions),
        ], $workflows->all()),
    ]);
})->name('core.workflows.index');

Route::get('/core/audit-logs', function (AuditTrailInterface $audit) {
    $limit = request()->integer('limit', 50);
    $filters = array_filter([
        'event_type' => request()->query('event_type'),
        'outcome' => request()->query('outcome'),
        'origin_component' => request()->query('origin_component'),
        'principal_id' => request()->query('actor_principal_id'),
        'membership_id' => request()->query('membership_id'),
        'organization_id' => request()->query('organization_id'),
        'scope_id' => request()->query('scope_id'),
        'target_type' => request()->query('target_type'),
        'target_id' => request()->query('target_id'),
        'execution_origin' => request()->query('execution_origin'),
        'created_from' => request()->query('created_from'),
        'created_to' => request()->query('created_to'),
    ], static fn (mixed $value): bool => is_string($value) && $value !== '');

    return response()->json([
        'audit_logs' => array_map(
            static fn ($record): array => $record->toArray(),
            $audit->latest($limit, $filters),
        ),
    ]);
})->middleware('core.permission:core.audit-logs.view')->name('core.audit.index');

Route::get('/core/audit-logs/export', function (AuditTrailInterface $audit) {
    $limit = request()->integer('limit', 200);
    $format = request()->query('format', 'jsonl');
    $format = is_string($format) && in_array($format, ['jsonl', 'csv'], true) ? $format : 'jsonl';

    $filters = array_filter([
        'event_type' => request()->query('event_type'),
        'outcome' => request()->query('outcome'),
        'origin_component' => request()->query('origin_component'),
        'principal_id' => request()->query('actor_principal_id'),
        'membership_id' => request()->query('membership_id'),
        'organization_id' => request()->query('organization_id'),
        'scope_id' => request()->query('scope_id'),
        'target_type' => request()->query('target_type'),
        'target_id' => request()->query('target_id'),
        'execution_origin' => request()->query('execution_origin'),
        'created_from' => request()->query('created_from'),
        'created_to' => request()->query('created_to'),
    ], static fn (mixed $value): bool => is_string($value) && $value !== '');

    $records = $audit->latest($limit, $filters);
    $filename = sprintf('audit-logs-%s.%s', now()->format('Ymd-His'), $format);

    if ($format === 'csv') {
        $rows = [[
            'id',
            'created_at',
            'event_type',
            'outcome',
            'origin_component',
            'principal_id',
            'membership_id',
            'organization_id',
            'scope_id',
            'target_type',
            'target_id',
            'execution_origin',
            'summary',
            'correlation',
        ]];

        foreach ($records as $record) {
            $rows[] = [
                $record->id,
                $record->createdAt,
                $record->eventType,
                $record->outcome,
                $record->originComponent,
                $record->principalId ?? '',
                $record->membershipId ?? '',
                $record->organizationId ?? '',
                $record->scopeId ?? '',
                $record->targetType ?? '',
                $record->targetId ?? '',
                $record->executionOrigin ?? '',
                json_encode($record->summary, JSON_UNESCAPED_SLASHES),
                json_encode($record->correlation, JSON_UNESCAPED_SLASHES),
            ];
        }

        $stream = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $content = stream_get_contents($stream) ?: '';
        fclose($stream);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    $content = implode("\n", array_map(
        static fn ($record): string => json_encode($record->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        $records,
    ));

    if ($content !== '') {
        $content .= "\n";
    }

    return response($content, 200, [
        'Content-Type' => 'application/x-ndjson; charset=UTF-8',
        'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
    ]);
})->middleware('core.permission:core.audit-logs.export')->name('core.audit.export');

Route::get('/core/authorization/check', function (AuthorizationServiceInterface $authorization, TenancyServiceInterface $tenancy) use ($resolvePrincipalId) {
    $principalId = $resolvePrincipalId();
    abort_unless(is_string($principalId) && $principalId !== '', 403);
    $permission = request()->query('permission', 'core.plugins.view');
    $requestedMembershipIds = request()->query('membership_ids', []);
    $requestedOrganizationId = request()->query('organization_id');
    $requestedScopeId = request()->query('scope_id');

    if (! is_array($requestedMembershipIds)) {
        $requestedMembershipIds = [];
    }

    $tenancyContext = (is_string($requestedOrganizationId) && $requestedOrganizationId !== '')
        ? $tenancy->resolveContext(
            principalId: is_string($principalId) ? $principalId : null,
            requestedOrganizationId: $requestedOrganizationId,
            requestedScopeId: is_string($requestedScopeId) ? $requestedScopeId : null,
            requestedMembershipIds: $requestedMembershipIds,
        )
        : new TenancyContext(
            principalId: is_string($principalId) ? $principalId : null,
        );

    $result = $authorization->authorize(new AuthorizationContext(
        principal: new PrincipalReference(
            id: $principalId,
            provider: 'demo',
        ),
        permission: $permission,
        memberships: $tenancyContext->memberships,
        organizationId: $tenancyContext->organization?->id,
        scopeId: $tenancyContext->scope?->id,
    ));

    return response()->json([
        'principal_id' => $principalId,
        'permission' => $permission,
        'organization_id' => $tenancyContext->organization?->id,
        'scope_id' => $tenancyContext->scope?->id,
        'membership_ids' => $tenancyContext->membershipIds(),
        'result' => $result->toArray(),
    ]);
})->name('core.authorization.check');
