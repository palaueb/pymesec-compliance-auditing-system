<?php

use Illuminate\Support\Facades\Route;
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
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Permissions\Contracts\AuthorizationStoreInterface;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;
use PymeSec\Core\Plugins\Contracts\PluginManagerInterface;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\Tenancy\TenancyContext;
use PymeSec\Core\UI\Contracts\ScreenRegistryInterface;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\Workflows\Contracts\WorkflowRegistryInterface;

$resolvePrincipalId = static function (?string $default = 'principal-org-a'): ?string {
    $requested = request()->input('principal_id', request()->query('principal_id'));

    if (is_string($requested) && $requested !== '') {
        return $requested;
    }

    $sessionPrincipalId = session('auth.principal_id');

    if (is_string($sessionPrincipalId) && $sessionPrincipalId !== '') {
        return $sessionPrincipalId;
    }

    return $default;
};

Route::get('/', function () {
    return response()->json([
        'service' => 'pymesec-core',
        'status' => 'ok',
    ]);
})->name('core.root');

Route::get('/app', function (
    MenuRegistryInterface $menus,
    MenuLabelResolver $labels,
    ScreenRegistryInterface $screens,
    TenancyServiceInterface $tenancy
) use ($resolvePrincipalId) {
    $availableThemes = config('ui.themes', []);
    $requestedTheme = request()->query('theme');
    $themeKey = is_string($requestedTheme) && isset($availableThemes[$requestedTheme])
        ? $requestedTheme
        : (string) config('ui.default_theme', 'atlas');
    $theme = $availableThemes[$themeKey] ?? reset($availableThemes);

    $locale = request()->query('locale', config('app.locale', 'en'));
    $locale = is_string($locale) && in_array($locale, ['en', 'es', 'fr', 'de'], true) ? $locale : 'en';
    app()->setLocale($locale);

    $principalId = (string) $resolvePrincipalId('principal-org-a');
    $requestedMembershipIds = request()->query('membership_ids', []);

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

    $visibleMenus = $labels->resolveTree($menus->visible(new MenuVisibilityContext(
        principal: new PrincipalReference(
            id: $principalId,
            provider: 'demo',
        ),
        memberships: $memberships,
        organizationId: $organizationId,
        scopeId: $scopeId,
    )), $locale);

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

    $flatMenus = $flatten($visibleMenus);
    $selectedMenuId = request()->query('menu');

    if (! is_string($selectedMenuId) || ! isset($flatMenus[$selectedMenuId])) {
        $defaultMenu = collect($flatMenus)->first(fn (array $menu): bool => ($menu['route'] ?? null) !== null);
        $selectedMenuId = is_array($defaultMenu) ? ($defaultMenu['id'] ?? null) : null;
    }

    $baseQuery = [
        'principal_id' => $principalId,
        'locale' => $locale,
        'theme' => $themeKey,
    ];

    if (is_string($organizationId) && $organizationId !== '') {
        $baseQuery['organization_id'] = $organizationId;
    }

    if (is_string($scopeId) && $scopeId !== '') {
        $baseQuery['scope_id'] = $scopeId;
    }

    foreach ($memberships as $membership) {
        $baseQuery['membership_ids'][] = $membership->id;
    }

    $decorate = function (array $items) use (&$decorate, $baseQuery): array {
        return array_map(function (array $item) use (&$decorate, $baseQuery): array {
            $query = [...$baseQuery, 'menu' => $item['id']];

            return [
                ...$item,
                'shell_url' => route('core.shell.index', $query),
                'children' => $decorate($item['children'] ?? []),
            ];
        }, $items);
    };

    $visibleMenus = $decorate($visibleMenus);
    $selectedMenu = $selectedMenuId !== null ? $flatMenus[$selectedMenuId] ?? null : null;

    $screen = null;

    if (is_string($selectedMenuId) && $screens->has($selectedMenuId)) {
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
            query: $baseQuery,
        ));
    }

    $themeOptions = [];

    foreach ($availableThemes as $key => $definition) {
        if (! is_array($definition)) {
            continue;
        }

        $themeOptions[] = [
            'label' => (string) ($definition['label'] ?? $key),
            'active' => $key === $themeKey,
            'url' => route('core.shell.index', [...$baseQuery, 'theme' => $key, 'menu' => $selectedMenuId]),
        ];
    }

    $debugPayload = [
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
        'menuApiUrl' => route('core.menus.index', $baseQuery),
        'debugPayloadJson' => json_encode($debugPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'principalId' => $principalId,
        'sessionPrincipalId' => is_string(session('auth.principal_id')) ? session('auth.principal_id') : null,
        'organizationId' => $organizationId,
        'scopeId' => $scopeId,
        'organizations' => array_map(static fn ($organization): array => $organization->toArray(), $tenancyContext->organizations),
        'scopes' => array_map(static fn ($scope): array => $scope->toArray(), $tenancyContext->scopes),
        'selectedOrganization' => $tenancyContext->organization?->toArray(),
        'selectedScope' => $tenancyContext->scope?->toArray(),
        'tenancyShellUrl' => isset($flatMenus['core.tenancy'])
            ? route('core.shell.index', [...$baseQuery, 'menu' => 'core.tenancy'])
            : null,
    ]);
})->name('core.shell.index');

Route::get('/core/plugins', function (PluginManagerInterface $plugins) {
    return response()->json([
        'core_version' => config('plugins.core_version'),
        'plugins' => $plugins->status(),
    ]);
})->name('core.plugins.index');

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
) {
    $validated = request()->validate([
        'key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9\\.-]*$/'],
        'label' => ['required', 'string', 'max:160'],
        'permissions' => ['nullable', 'array'],
        'permissions.*' => ['string', 'max:160'],
        'principal_id' => ['nullable', 'string', 'max:80'],
        'locale' => ['nullable', 'string', 'max:10'],
        'menu' => ['nullable', 'string', 'max:80'],
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

    return redirect()->route('core.shell.index', array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.roles',
        'principal_id' => $principalId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
    ]));
})->middleware('core.permission:core.roles.manage')->name('core.roles.store');

Route::post('/core/roles/grants', function (
    AuthorizationStoreInterface $store,
    PermissionRegistryInterface $permissions,
    AuditTrailInterface $audit,
    EventBusInterface $events
) {
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

    return redirect()->route('core.shell.index', array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.roles',
        'principal_id' => $principalId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
    ]));
})->middleware('core.permission:core.roles.manage')->name('core.grants.store');

Route::post('/core/roles/grants/{grantId}', function (
    string $grantId,
    AuthorizationStoreInterface $store,
    PermissionRegistryInterface $permissions,
    AuditTrailInterface $audit,
    EventBusInterface $events
) {
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

    return redirect()->route('core.shell.index', array_filter([
        'menu' => is_string($validated['menu'] ?? null) ? $validated['menu'] : 'core.roles',
        'principal_id' => $principalId,
        'locale' => is_string($validated['locale'] ?? null) ? $validated['locale'] : 'en',
    ]));
})->middleware('core.permission:core.roles.manage')->name('core.grants.update');

Route::get('/core/menus', function (MenuRegistryInterface $menus, TenancyServiceInterface $tenancy) {
    $principalId = request()->query('principal_id', session('auth.principal_id'));
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

Route::get('/core/authorization/check', function (AuthorizationServiceInterface $authorization, TenancyServiceInterface $tenancy) {
    $principalId = request()->query('principal_id', session('auth.principal_id', 'principal-admin'));
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
