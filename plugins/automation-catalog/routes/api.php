<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PymeSec\Plugins\AutomationCatalog\AutomationCatalogRepository;
use PymeSec\Plugins\AutomationCatalog\AutomationOutputMappingDeliveryService;
use PymeSec\Plugins\AutomationCatalog\AutomationPackageRepositorySyncService;
use PymeSec\Plugins\AutomationCatalog\AutomationPackRuntimeService;

$apiSuccess = static fn (mixed $data, array $meta = []) => response()->json([
    'data' => $data,
    'meta' => array_merge([
        'request_id' => request()->attributes->get('core.request_id'),
    ], $meta),
]);

$apiPrincipalId = static function (Request $request): ?string {
    $principalId = $request->attributes->get('core.authenticated_principal_id');

    if (is_string($principalId) && $principalId !== '') {
        return $principalId;
    }

    $fallback = $request->input('principal_id', $request->query('principal_id'));

    return is_string($fallback) && $fallback !== '' ? $fallback : null;
};

$apiOrganizationId = static function (Request $request): string {
    $organizationId = $request->input('organization_id', $request->query('organization_id'));

    if (! is_string($organizationId) || trim($organizationId) === '') {
        throw ValidationException::withMessages([
            'organization_id' => __('Organization context is required.'),
        ]);
    }

    return trim($organizationId);
};

$apiMembershipId = static function (Request $request): ?string {
    $membershipId = $request->input('membership_id', $request->query('membership_id'));

    return is_string($membershipId) && trim($membershipId) !== ''
        ? trim($membershipId)
        : null;
};

$apiScopeId = static function (Request $request): ?string {
    $scopeId = $request->input('scope_id', $request->query('scope_id'));

    return is_string($scopeId) && trim($scopeId) !== ''
        ? trim($scopeId)
        : null;
};

$officialRepositoryPreset = static function (): array {
    if (function_exists('automationCatalogOfficialRepositoryPreset')) {
        return automationCatalogOfficialRepositoryPreset();
    }

    $config = config('plugins.automation_catalog.official_repository');
    $label = trim((string) (is_array($config) ? ($config['label'] ?? '') : ''));
    $repositoryUrl = trim((string) (is_array($config) ? ($config['url'] ?? '') : ''));
    $repositorySignUrl = trim((string) (is_array($config) ? ($config['sign_url'] ?? '') : ''));
    $trustTier = trim((string) (is_array($config) ? ($config['trust_tier'] ?? '') : ''));
    $publicKeyPem = trim((string) (is_array($config) ? ($config['public_key_pem'] ?? '') : ''));
    $publicKeyPath = trim((string) (is_array($config) ? ($config['public_key_path'] ?? '') : ''));

    if ($publicKeyPem === '' && $publicKeyPath !== '' && is_file($publicKeyPath)) {
        $publicKeyPem = trim((string) file_get_contents($publicKeyPath));
    }

    if ($publicKeyPem === '' && function_exists('automationCatalogOfficialRepositoryDefaultPublicKey')) {
        $publicKeyPem = automationCatalogOfficialRepositoryDefaultPublicKey();
    }

    if (str_contains($publicKeyPem, '\n')) {
        $publicKeyPem = str_replace('\n', "\n", $publicKeyPem);
    }

    if ($repositorySignUrl === '' && $repositoryUrl !== '') {
        $repositorySignUrl = rtrim($repositoryUrl, '/').'.sign';
    }

    return [
        'label' => $label !== '' ? $label : 'PymeSec Official Repository',
        'repository_url' => $repositoryUrl,
        'repository_sign_url' => $repositorySignUrl,
        'trust_tier' => in_array($trustTier, ['trusted-first-party', 'trusted-partner', 'community-reviewed', 'untrusted'], true)
            ? $trustTier
            : 'trusted-first-party',
        'public_key_pem' => $publicKeyPem,
    ];
};

Route::get('/packs', function (Request $request, AutomationCatalogRepository $repository) use (
    $apiSuccess,
    $apiOrganizationId,
    $apiScopeId,
) {
    $organizationId = $apiOrganizationId($request);
    $scopeId = $apiScopeId($request);

    return $apiSuccess($repository->all($organizationId, $scopeId));
})->middleware('core.permission:plugin.automation-catalog.packs.view')->defaults('_openapi', [
    'operation_id' => 'automationCatalogListPacks',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'List automation packs in organization and optional scope context',
    'responses' => [
        '200' => [
            'description' => 'Automation packs list',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
]);

Route::get('/external-catalog', function (Request $request, AutomationCatalogRepository $repository) use (
    $apiSuccess,
    $apiOrganizationId,
    $apiScopeId,
) {
    $organizationId = $apiOrganizationId($request);
    $scopeId = $apiScopeId($request);

    return $apiSuccess($repository->externalCatalogRows($organizationId, $scopeId));
})->middleware('core.permission:plugin.automation-catalog.packs.view')->defaults('_openapi', [
    'operation_id' => 'automationCatalogListExternalCatalogRows',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'List latest external catalog pack releases from enabled repositories',
    'responses' => [
        '200' => [
            'description' => 'External catalog latest rows',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
]);

Route::get('/repositories', function (Request $request, AutomationCatalogRepository $repository) use (
    $apiSuccess,
    $apiOrganizationId,
    $apiScopeId,
) {
    $organizationId = $apiOrganizationId($request);
    $scopeId = $apiScopeId($request);

    return $apiSuccess($repository->repositories($organizationId, $scopeId));
})->middleware('core.permission:plugin.automation-catalog.packs.view')->defaults('_openapi', [
    'operation_id' => 'automationCatalogListRepositories',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'List configured package repositories for automation packs',
    'responses' => [
        '200' => [
            'description' => 'Automation repositories list',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
]);

Route::get('/lookups/scopes/options', function (Request $request) use ($apiSuccess, $apiOrganizationId) {
    $organizationId = $apiOrganizationId($request);
    $limit = max(1, min(200, (int) $request->integer('limit', 100)));

    if (! Schema::hasTable('scopes')) {
        return $apiSuccess([]);
    }

    $rows = DB::table('scopes')
        ->where('organization_id', $organizationId)
        ->orderBy('name')
        ->orderBy('id')
        ->limit($limit)
        ->get(['id', 'name'])
        ->map(static fn (object $scope): array => [
            'id' => (string) $scope->id,
            'label' => trim((string) $scope->name) !== '' ? (string) $scope->name : (string) $scope->id,
        ])
        ->values()
        ->all();

    return $apiSuccess($rows);
})->middleware('core.permission:plugin.automation-catalog.packs.view')->defaults('_openapi', [
    'operation_id' => 'automationCatalogLookupScopes',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'List scope options for automation mapping and repository forms',
    'responses' => [
        '200' => [
            'description' => 'Scope options',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
]);

Route::get('/lookups/artifacts/options', function (Request $request) use (
    $apiSuccess,
    $apiOrganizationId,
    $apiScopeId,
) {
    $organizationId = $apiOrganizationId($request);
    $scopeId = $apiScopeId($request);
    $limit = max(1, min(200, (int) $request->integer('limit', 100)));

    if (! Schema::hasTable('artifacts')) {
        return $apiSuccess([]);
    }

    $query = DB::table('artifacts')
        ->where('organization_id', $organizationId)
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->limit($limit);

    if ($scopeId !== null) {
        $query->where(function ($nested) use ($scopeId): void {
            $nested->where('scope_id', $scopeId)->orWhereNull('scope_id');
        });
    }

    $rows = $query->get(['id', 'label'])
        ->map(static fn (object $artifact): array => [
            'id' => (string) $artifact->id,
            'label' => trim((string) ($artifact->label ?? '')) !== '' ? (string) $artifact->label : (string) $artifact->id,
        ])
        ->values()
        ->all();

    return $apiSuccess($rows);
})->middleware('core.permission:plugin.automation-catalog.packs.view')->defaults('_openapi', [
    'operation_id' => 'automationCatalogLookupArtifacts',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'List artifact options for manual automation output mapping apply',
    'responses' => [
        '200' => [
            'description' => 'Artifact options',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
]);

Route::get('/lookups/target-subjects/options', function (Request $request) use (
    $apiSuccess,
    $apiOrganizationId,
    $apiScopeId,
) {
    $organizationId = $apiOrganizationId($request);
    $scopeId = $apiScopeId($request);
    $targetType = trim((string) $request->query('target_subject_type', ''));
    $limit = max(1, min(200, (int) $request->integer('limit', 100)));

    if ($targetType === '') {
        return $apiSuccess([]);
    }

    $tableByType = [
        'asset' => ['assets', 'name'],
        'control' => ['controls', 'name'],
        'risk' => ['risks', 'title'],
        'finding' => ['findings', 'title'],
        'policy' => ['policies', 'title'],
        'policy-exception' => ['policy_exceptions', 'title'],
        'privacy-data-flow' => ['privacy_data_flows', 'name'],
        'privacy-processing-activity' => ['privacy_processing_activities', 'name'],
        'continuity-service' => ['continuity_services', 'name'],
        'continuity-plan' => ['continuity_recovery_plans', 'name'],
        'recovery-plan' => ['continuity_recovery_plans', 'name'],
        'assessment' => ['assessment_campaigns', 'title'],
        'assessment-review' => ['assessment_control_reviews', 'id'],
        'vendor-review' => ['vendor_reviews', 'title'],
    ];

    $tableDefinition = $tableByType[$targetType] ?? null;
    if (! is_array($tableDefinition) || ! is_string($tableDefinition[0]) || ! is_string($tableDefinition[1])) {
        return $apiSuccess([]);
    }

    [$table, $labelColumn] = $tableDefinition;
    if (! Schema::hasTable($table)) {
        return $apiSuccess([]);
    }

    $query = DB::table($table)
        ->where('organization_id', $organizationId)
        ->orderByDesc('updated_at')
        ->orderBy('id')
        ->limit($limit);

    if ($scopeId !== null && Schema::hasColumn($table, 'scope_id')) {
        $query->where(function ($nested) use ($scopeId): void {
            $nested->where('scope_id', $scopeId)->orWhereNull('scope_id');
        });
    }

    $rows = $query->get(['id', $labelColumn])
        ->map(static function (object $row) use ($labelColumn): array {
            $rawLabel = (string) ($row->{$labelColumn} ?? '');
            $label = trim($rawLabel) !== '' ? $rawLabel : (string) $row->id;

            return [
                'id' => (string) $row->id,
                'label' => $label,
            ];
        })
        ->values()
        ->all();

    return $apiSuccess($rows);
})->middleware('core.permission:plugin.automation-catalog.packs.view')->defaults('_openapi', [
    'operation_id' => 'automationCatalogLookupTargetSubjects',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'List target object options for automation output mappings',
    'responses' => [
        '200' => [
            'description' => 'Target object options',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
]);

Route::post('/packs', function (Request $request, AutomationCatalogRepository $repository) use (
    $apiSuccess,
    $apiPrincipalId,
    $apiOrganizationId,
) {
    $validated = $request->validate([
        'pack_key' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9][a-z0-9.-]*$/'],
        'name' => ['required', 'string', 'max:180'],
        'summary' => ['nullable', 'string', 'max:400'],
        'version' => ['nullable', 'string', 'max:64'],
        'provider_type' => ['required', 'string', 'in:native,community,vendor,internal'],
        'source_ref' => ['nullable', 'url', 'max:512'],
        'provenance_type' => ['required', 'string', 'in:plugin,marketplace,git,manual'],
        'scope_id' => ['nullable', 'string', 'max:64'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    $organizationId = $apiOrganizationId($request);

    $pack = $repository->createPack([
        ...$validated,
        'organization_id' => $organizationId,
        'owner_principal_id' => $principalId,
    ]);

    return $apiSuccess($pack);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->defaults('_openapi', [
    'operation_id' => 'automationCatalogCreatePack',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'Create or upsert one automation pack entry',
    'responses' => [
        '200' => [
            'description' => 'Pack saved',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_rules' => [
        'pack_key' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9][a-z0-9.-]*$/'],
        'name' => ['required', 'string', 'max:180'],
        'summary' => ['nullable', 'string', 'max:400'],
        'version' => ['nullable', 'string', 'max:64'],
        'provider_type' => ['required', 'string', 'in:native,community,vendor,internal'],
        'source_ref' => ['nullable', 'url', 'max:512'],
        'provenance_type' => ['required', 'string', 'in:plugin,marketplace,git,manual'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
    ],
    'lookup_fields' => [
        'scope_id' => '/api/v1/automation-catalog/lookups/scopes/options',
    ],
]);

Route::post('/packs/{packId}/install', function (string $packId, Request $request, AutomationCatalogRepository $repository) use ($apiSuccess, $apiPrincipalId) {
    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $pack = $repository->installPack($packId, $principalId);
    abort_if($pack === null, 404);

    return $apiSuccess($pack);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->defaults('_openapi', [
    'operation_id' => 'automationCatalogInstallPack',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'Install one automation pack',
    'responses' => [
        '200' => [
            'description' => 'Pack installed',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Pack not found',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['nullable', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'membership_id' => ['nullable', 'string', 'max:64'],
    ],
]);

Route::post('/packs/{packId}/enable', function (string $packId, Request $request, AutomationCatalogRepository $repository) use ($apiSuccess, $apiPrincipalId) {
    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $pack = $repository->enablePack($packId, $principalId);
    abort_if($pack === null, 404);

    return $apiSuccess($pack);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->defaults('_openapi', [
    'operation_id' => 'automationCatalogEnablePack',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'Enable one automation pack',
    'responses' => [
        '200' => [
            'description' => 'Pack enabled',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Pack not found',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['nullable', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'membership_id' => ['nullable', 'string', 'max:64'],
    ],
]);

Route::post('/packs/{packId}/disable', function (string $packId, Request $request, AutomationCatalogRepository $repository) use ($apiSuccess, $apiPrincipalId) {
    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $pack = $repository->disablePack($packId, $principalId);
    abort_if($pack === null, 404);

    return $apiSuccess($pack);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->defaults('_openapi', [
    'operation_id' => 'automationCatalogDisablePack',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'Disable one automation pack',
    'responses' => [
        '200' => [
            'description' => 'Pack disabled',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Pack not found',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['nullable', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'membership_id' => ['nullable', 'string', 'max:64'],
    ],
]);

Route::post('/packs/{packId}/uninstall', function (string $packId, AutomationCatalogRepository $repository) use ($apiSuccess) {
    abort_if($repository->uninstallPack($packId) === false, 404);

    return $apiSuccess([
        'pack_id' => $packId,
        'uninstalled' => true,
    ]);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->defaults('_openapi', [
    'operation_id' => 'automationCatalogUninstallPack',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'Uninstall one automation pack and remove related mappings',
    'responses' => [
        '200' => [
            'description' => 'Pack uninstalled',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Pack not found',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['nullable', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'membership_id' => ['nullable', 'string', 'max:64'],
    ],
]);

Route::post('/packs/{packId}/health', function (string $packId, Request $request, AutomationCatalogRepository $repository) use ($apiSuccess) {
    $validated = $request->validate([
        'health_state' => ['required', 'string', 'in:unknown,healthy,degraded,failing'],
        'last_failure_reason' => ['nullable', 'string', 'max:2000'],
    ]);

    $pack = $repository->updateHealth($packId, $validated);
    abort_if($pack === null, 404);

    return $apiSuccess($pack);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->defaults('_openapi', [
    'operation_id' => 'automationCatalogUpdatePackHealth',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'Update automation pack health state',
    'responses' => [
        '200' => [
            'description' => 'Pack health updated',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Pack not found',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_rules' => [
        'health_state' => ['required', 'string', 'in:unknown,healthy,degraded,failing'],
        'last_failure_reason' => ['nullable', 'string', 'max:2000'],
    ],
]);

Route::post('/packs/{packId}/schedule', function (string $packId, Request $request, AutomationCatalogRepository $repository) use ($apiSuccess) {
    $validated = $request->validate([
        'runtime_schedule_enabled' => ['nullable', 'boolean'],
        'runtime_schedule_cron' => ['nullable', 'string', 'max:120'],
        'runtime_schedule_timezone' => [
            'nullable',
            'string',
            'max:64',
            static function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || $value === '') {
                    return;
                }

                if (! in_array($value, timezone_identifiers_list(), true)) {
                    $fail(__('Timezone must be a valid IANA timezone identifier.'));
                }
            },
        ],
    ]);

    $scheduleEnabled = (bool) ($validated['runtime_schedule_enabled'] ?? false);
    $scheduleCron = trim((string) ($validated['runtime_schedule_cron'] ?? ''));
    $scheduleTimezone = trim((string) ($validated['runtime_schedule_timezone'] ?? ''));

    if ($scheduleEnabled && $scheduleCron === '') {
        throw ValidationException::withMessages([
            'runtime_schedule_cron' => __('Cron expression is required when runtime schedule is enabled.'),
        ]);
    }

    if ($scheduleEnabled && $scheduleTimezone === '') {
        $scheduleTimezone = 'UTC';
    }

    $pack = $repository->updatePackRuntimeSchedule($packId, [
        'runtime_schedule_enabled' => $scheduleEnabled,
        'runtime_schedule_cron' => $scheduleCron !== '' ? $scheduleCron : null,
        'runtime_schedule_timezone' => $scheduleTimezone !== '' ? $scheduleTimezone : null,
        'runtime_schedule_last_slot' => null,
    ]);
    abort_if($pack === null, 404);

    return $apiSuccess($pack);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->defaults('_openapi', [
    'operation_id' => 'automationCatalogUpdatePackSchedule',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'Update automation pack runtime schedule policy',
    'responses' => [
        '200' => [
            'description' => 'Pack schedule updated',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Pack not found',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_rules' => [
        'runtime_schedule_enabled' => ['nullable', 'boolean'],
        'runtime_schedule_cron' => ['nullable', 'string', 'max:120'],
        'runtime_schedule_timezone' => ['nullable', 'string', 'max:64'],
    ],
]);

Route::post('/packs/{packId}/run', function (
    string $packId,
    Request $request,
    AutomationPackRuntimeService $runtime,
) use ($apiSuccess, $apiPrincipalId, $apiMembershipId) {
    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $run = $runtime->runPack(
        packId: $packId,
        triggerMode: 'manual',
        principalId: $principalId,
        membershipId: $apiMembershipId($request),
    );
    abort_if($run === null, 404);

    return $apiSuccess($run);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->defaults('_openapi', [
    'operation_id' => 'automationCatalogRunPack',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'Execute one automation pack runtime manually',
    'responses' => [
        '200' => [
            'description' => 'Pack runtime execution summary',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Pack not found',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['nullable', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'membership_id' => ['nullable', 'string', 'max:64'],
    ],
]);

Route::post('/repositories', function (
    Request $request,
    AutomationCatalogRepository $repository,
) use (
    $apiSuccess,
    $apiPrincipalId,
    $apiOrganizationId,
) {
    $validated = $request->validate([
        'label' => ['required', 'string', 'max:180'],
        'repository_url' => ['required', 'url', 'max:1024'],
        'repository_sign_url' => ['nullable', 'url', 'max:1024'],
        'public_key_pem' => ['required', 'string', 'max:12000'],
        'trust_tier' => ['required', 'string', 'in:trusted-first-party,trusted-partner,community-reviewed,untrusted'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'is_enabled' => ['nullable', 'boolean'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    $organizationId = $apiOrganizationId($request);

    $saved = $repository->saveRepository([
        ...$validated,
        'organization_id' => $organizationId,
        'created_by_principal_id' => $principalId,
        'updated_by_principal_id' => $principalId,
        'is_enabled' => (bool) ($validated['is_enabled'] ?? true),
    ]);

    return $apiSuccess($saved);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->defaults('_openapi', [
    'operation_id' => 'automationCatalogSaveRepository',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'Create or update one automation package repository',
    'responses' => [
        '200' => [
            'description' => 'Repository saved',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_rules' => [
        'label' => ['required', 'string', 'max:180'],
        'repository_url' => ['required', 'url', 'max:1024'],
        'repository_sign_url' => ['nullable', 'url', 'max:1024'],
        'public_key_pem' => ['required', 'string', 'max:12000'],
        'trust_tier' => ['required', 'string', 'in:trusted-first-party,trusted-partner,community-reviewed,untrusted'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'is_enabled' => ['nullable', 'boolean'],
    ],
    'lookup_fields' => [
        'scope_id' => '/api/v1/automation-catalog/lookups/scopes/options',
    ],
]);

Route::post('/repositories/install-official', function (
    Request $request,
    AutomationCatalogRepository $repository,
    AutomationPackageRepositorySyncService $syncService,
) use (
    $apiSuccess,
    $apiPrincipalId,
    $apiOrganizationId,
    $apiScopeId,
    $officialRepositoryPreset,
) {
    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    $organizationId = $apiOrganizationId($request);
    $scopeId = $apiScopeId($request);
    $official = $officialRepositoryPreset();

    if ($official['repository_url'] === '' || $official['repository_sign_url'] === '' || $official['public_key_pem'] === '') {
        return $apiSuccess([
            'status' => 'failed',
            'message' => __('Official repository is not configured. Set repository URL/sign/public key in plugin config.'),
        ]);
    }

    $repositoryRecord = $repository->saveRepository([
        'label' => $official['label'],
        'repository_url' => $official['repository_url'],
        'repository_sign_url' => $official['repository_sign_url'],
        'public_key_pem' => $official['public_key_pem'],
        'trust_tier' => $official['trust_tier'],
        'scope_id' => $scopeId,
        'organization_id' => $organizationId,
        'created_by_principal_id' => $principalId,
        'updated_by_principal_id' => $principalId,
        'is_enabled' => true,
    ]);

    try {
        $result = $syncService->sync($repositoryRecord);
        $repository->markRepositorySyncResult((string) $repositoryRecord['id'], 'success');

        return $apiSuccess([
            'status' => 'success',
            'repository' => $repository->findRepository((string) $repositoryRecord['id']),
            'release_rows' => (int) ($result['release_rows'] ?? 0),
            'latest_rows' => (int) ($result['latest_rows'] ?? 0),
        ]);
    } catch (Throwable $exception) {
        $repository->markRepositorySyncResult((string) $repositoryRecord['id'], 'failed', $exception->getMessage());

        return $apiSuccess([
            'status' => 'failed',
            'repository' => $repository->findRepository((string) $repositoryRecord['id']),
            'message' => $exception->getMessage(),
        ]);
    }
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->defaults('_openapi', [
    'operation_id' => 'automationCatalogInstallOfficialRepository',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'Install and refresh the official automation package repository preset',
    'responses' => [
        '200' => [
            'description' => 'Official repository install and refresh outcome',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
    ],
    'lookup_fields' => [
        'scope_id' => '/api/v1/automation-catalog/lookups/scopes/options',
    ],
]);

Route::post('/repositories/{repositoryId}/refresh', function (
    string $repositoryId,
    AutomationCatalogRepository $repository,
    AutomationPackageRepositorySyncService $syncService,
) use ($apiSuccess) {
    $repositoryRecord = $repository->findRepository($repositoryId);
    abort_if($repositoryRecord === null, 404);

    try {
        $result = $syncService->sync($repositoryRecord);
        $repository->markRepositorySyncResult($repositoryId, 'success');

        return $apiSuccess([
            'status' => 'success',
            'repository' => $repository->findRepository($repositoryId),
            'release_rows' => (int) ($result['release_rows'] ?? 0),
            'latest_rows' => (int) ($result['latest_rows'] ?? 0),
        ]);
    } catch (Throwable $exception) {
        $repository->markRepositorySyncResult($repositoryId, 'failed', $exception->getMessage());

        return $apiSuccess([
            'status' => 'failed',
            'repository' => $repository->findRepository($repositoryId),
            'message' => $exception->getMessage(),
        ]);
    }
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->defaults('_openapi', [
    'operation_id' => 'automationCatalogRefreshRepository',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'Refresh one configured automation package repository',
    'responses' => [
        '200' => [
            'description' => 'Repository refresh outcome',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Repository not found',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['nullable', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'membership_id' => ['nullable', 'string', 'max:64'],
    ],
]);

Route::post('/packs/{packId}/output-mappings', function (
    string $packId,
    Request $request,
    AutomationCatalogRepository $repository,
) use ($apiSuccess, $apiPrincipalId) {
    $validated = $request->validate([
        'mapping_label' => ['required', 'string', 'max:180'],
        'mapping_kind' => ['required', 'string', 'in:evidence-refresh,workflow-transition'],
        'target_binding_mode' => ['nullable', 'string', 'in:explicit,scope'],
        'target_subject_type' => ['required', 'string', Rule::in([
            'asset',
            'control',
            'risk',
            'finding',
            'policy',
            'policy-exception',
            'privacy-data-flow',
            'privacy-processing-activity',
            'continuity-service',
            'continuity-plan',
            'recovery-plan',
            'assessment',
            'assessment-review',
            'vendor-review',
        ])],
        'target_subject_id' => ['nullable', 'string', 'max:80', 'required_if:target_binding_mode,explicit'],
        'target_scope_id' => ['nullable', 'string', 'max:64'],
        'target_tags' => ['nullable', 'string', 'max:600'],
        'posture_propagation_policy' => ['nullable', 'string', 'in:disabled,status-only'],
        'execution_mode' => ['nullable', 'string', 'in:both,runtime-only,manual-only'],
        'on_fail_policy' => ['nullable', 'string', 'in:no-op,raise-finding,raise-finding-and-action'],
        'evidence_policy' => ['nullable', 'string', 'in:always,on-fail,on-change'],
        'runtime_retry_max_attempts' => ['nullable', 'integer', 'min:0', 'max:5'],
        'runtime_retry_backoff_ms' => ['nullable', 'integer', 'min:0', 'max:60000'],
        'runtime_max_targets' => ['nullable', 'integer', 'min:1', 'max:2000'],
        'runtime_payload_max_kb' => ['nullable', 'integer', 'min:0', 'max:10240'],
        'workflow_key' => ['nullable', 'string', 'max:180', 'required_if:mapping_kind,workflow-transition'],
        'transition_key' => ['nullable', 'string', 'max:80', 'required_if:mapping_kind,workflow-transition'],
        'is_active' => ['nullable', 'boolean'],
    ]);

    $bindingMode = is_string($validated['target_binding_mode'] ?? null) && $validated['target_binding_mode'] !== ''
        ? (string) $validated['target_binding_mode']
        : 'explicit';

    if ($bindingMode === 'scope' && ! in_array((string) $validated['target_subject_type'], ['asset', 'risk'], true)) {
        throw ValidationException::withMessages([
            'target_subject_type' => 'Scope binding mode currently supports only asset and risk targets.',
        ]);
    }

    if ($bindingMode === 'explicit' && trim((string) ($validated['target_subject_id'] ?? '')) === '') {
        throw ValidationException::withMessages([
            'target_subject_id' => 'Target subject id is required in explicit binding mode.',
        ]);
    }

    $posturePolicy = is_string($validated['posture_propagation_policy'] ?? null) && $validated['posture_propagation_policy'] !== ''
        ? (string) $validated['posture_propagation_policy']
        : 'disabled';
    $executionMode = is_string($validated['execution_mode'] ?? null) && $validated['execution_mode'] !== ''
        ? (string) $validated['execution_mode']
        : 'both';
    $onFailPolicy = is_string($validated['on_fail_policy'] ?? null) && $validated['on_fail_policy'] !== ''
        ? (string) $validated['on_fail_policy']
        : 'no-op';
    $evidencePolicy = is_string($validated['evidence_policy'] ?? null) && $validated['evidence_policy'] !== ''
        ? (string) $validated['evidence_policy']
        : 'always';

    if ($posturePolicy !== 'disabled' && ! in_array((string) $validated['target_subject_type'], ['asset', 'risk'], true)) {
        throw ValidationException::withMessages([
            'posture_propagation_policy' => 'Posture propagation currently supports only asset and risk targets.',
        ]);
    }

    $rawSelectorTags = collect(explode(',', (string) ($validated['target_tags'] ?? '')))
        ->map(static fn (string $value): string => trim($value))
        ->filter(static fn (string $value): bool => $value !== '')
        ->values()
        ->all();
    $selectorTags = [];
    $invalidSelectorTags = [];

    foreach ($rawSelectorTags as $tag) {
        if (! str_contains($tag, ':')) {
            $invalidSelectorTags[] = $tag;

            continue;
        }

        [$rawKey, $rawValue] = array_pad(explode(':', $tag, 2), 2, '');
        $key = trim((string) $rawKey);
        $value = trim((string) $rawValue);

        if ($key === '' || $value === '') {
            $invalidSelectorTags[] = $tag;

            continue;
        }

        $selectorTags[] = $key.':'.$value;
    }

    if ($invalidSelectorTags !== []) {
        throw ValidationException::withMessages([
            'target_tags' => sprintf(
                __('Invalid selector tag format for: %s. Use key:value (comma-separated).'),
                implode(', ', $invalidSelectorTags),
            ),
        ]);
    }

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $mapping = $repository->createOutputMapping($packId, [
        ...$validated,
        'target_binding_mode' => $bindingMode,
        'posture_propagation_policy' => $posturePolicy,
        'execution_mode' => $executionMode,
        'on_fail_policy' => $onFailPolicy,
        'evidence_policy' => $evidencePolicy,
        'runtime_retry_max_attempts' => (int) ($validated['runtime_retry_max_attempts'] ?? 0),
        'runtime_retry_backoff_ms' => (int) ($validated['runtime_retry_backoff_ms'] ?? 0),
        'runtime_max_targets' => (int) ($validated['runtime_max_targets'] ?? 200),
        'runtime_payload_max_kb' => (int) ($validated['runtime_payload_max_kb'] ?? 512),
        'target_selector' => [
            'tags' => $selectorTags,
        ],
    ], $principalId);
    abort_if($mapping === null, 404);

    return $apiSuccess($mapping);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->defaults('_openapi', [
    'operation_id' => 'automationCatalogCreateOutputMapping',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'Create one automation output mapping for a pack',
    'responses' => [
        '200' => [
            'description' => 'Output mapping created',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Pack not found',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_rules' => [
        'mapping_label' => ['required', 'string', 'max:180'],
        'mapping_kind' => ['required', 'string', 'in:evidence-refresh,workflow-transition'],
        'target_binding_mode' => ['nullable', 'string', 'in:explicit,scope'],
        'target_subject_type' => ['required', 'string'],
        'target_subject_id' => ['nullable', 'string', 'max:80'],
        'target_scope_id' => ['nullable', 'string', 'max:64'],
        'target_tags' => ['nullable', 'string', 'max:600'],
        'posture_propagation_policy' => ['nullable', 'string', 'in:disabled,status-only'],
        'execution_mode' => ['nullable', 'string', 'in:both,runtime-only,manual-only'],
        'on_fail_policy' => ['nullable', 'string', 'in:no-op,raise-finding,raise-finding-and-action'],
        'evidence_policy' => ['nullable', 'string', 'in:always,on-fail,on-change'],
        'runtime_retry_max_attempts' => ['nullable', 'integer', 'min:0', 'max:5'],
        'runtime_retry_backoff_ms' => ['nullable', 'integer', 'min:0', 'max:60000'],
        'runtime_max_targets' => ['nullable', 'integer', 'min:1', 'max:2000'],
        'runtime_payload_max_kb' => ['nullable', 'integer', 'min:0', 'max:10240'],
        'workflow_key' => ['nullable', 'string', 'max:180'],
        'transition_key' => ['nullable', 'string', 'max:80'],
        'is_active' => ['nullable', 'boolean'],
    ],
    'lookup_fields' => [
        'target_subject_id' => '/api/v1/automation-catalog/lookups/target-subjects/options',
        'target_scope_id' => '/api/v1/automation-catalog/lookups/scopes/options',
    ],
]);

Route::post('/packs/{packId}/output-mappings/{mappingId}/apply', function (
    string $packId,
    string $mappingId,
    Request $request,
    AutomationCatalogRepository $repository,
    AutomationOutputMappingDeliveryService $delivery,
) use (
    $apiSuccess,
    $apiPrincipalId,
    $apiMembershipId,
    $apiOrganizationId,
    $apiScopeId,
) {
    $validated = $request->validate([
        'existing_artifact_id' => ['nullable', 'string', 'max:64'],
        'output_file' => ['nullable', 'file', 'max:10240'],
        'evidence_kind' => ['nullable', 'string', 'in:document,workpaper,snapshot,report,ticket,log-export,statement,other'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    $membershipId = $apiMembershipId($request);
    $organizationId = $apiOrganizationId($request);
    $scopeId = $apiScopeId($request);

    $pack = $repository->find($packId);
    $mapping = $repository->findOutputMapping($mappingId);
    abort_if($pack === null || $mapping === null || ($mapping['automation_pack_id'] ?? '') !== $packId, 404);

    if (($mapping['target_binding_mode'] ?? 'explicit') === 'scope') {
        return $apiSuccess([
            'status' => 'skipped',
            'message' => __('Scope resolver mappings execute from pack runtime. Use the run endpoint instead.'),
            'mapping' => $mapping,
        ]);
    }

    if (($mapping['execution_mode'] ?? 'both') === 'runtime-only') {
        return $apiSuccess([
            'status' => 'skipped',
            'message' => __('This mapping is runtime-only. Execute it from pack runtime.'),
            'mapping' => $mapping,
        ]);
    }

    $result = $delivery->deliver(
        mapping: $mapping,
        data: [
            ...$validated,
            'output_file' => $request->file('output_file'),
        ],
        principalId: $principalId,
        membershipId: $membershipId,
        organizationId: $organizationId,
        scopeId: $scopeId,
    );

    $updatedMapping = $repository->markOutputMappingDelivery($mappingId, $result['status'], $result['message']);

    return $apiSuccess([
        'status' => $result['status'],
        'message' => $result['message'],
        'mapping' => $updatedMapping,
    ]);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->defaults('_openapi', [
    'operation_id' => 'automationCatalogApplyOutputMapping',
    'tags' => ['automation-catalog'],
    'tag_descriptions' => [
        'automation-catalog' => 'Automation pack lifecycle, repository, runtime, and output mapping endpoints.',
    ],
    'summary' => 'Apply one output mapping manually',
    'responses' => [
        '200' => [
            'description' => 'Output mapping apply result',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Pack or mapping not found',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_rules' => [
        'existing_artifact_id' => ['nullable', 'string', 'max:64'],
        'output_file' => ['nullable', 'file', 'max:10240'],
        'evidence_kind' => ['nullable', 'string', 'in:document,workpaper,snapshot,report,ticket,log-export,statement,other'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'membership_id' => ['nullable', 'string', 'max:64'],
    ],
    'lookup_fields' => [
        'existing_artifact_id' => '/api/v1/automation-catalog/lookups/artifacts/options',
    ],
]);
