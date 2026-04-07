<?php

use App\Http\Requests\Api\V1\AssessmentCreateRequest;
use App\Http\Requests\Api\V1\AssessmentReviewUpdateRequest;
use App\Http\Requests\Api\V1\AssessmentUpdateRequest;
use App\Http\Requests\Api\V1\AssetCreateRequest;
use App\Http\Requests\Api\V1\AssetUpdateRequest;
use App\Http\Requests\Api\V1\ControlCreateRequest;
use App\Http\Requests\Api\V1\ControlUpdateRequest;
use App\Http\Requests\Api\V1\FindingCreateRequest;
use App\Http\Requests\Api\V1\FindingUpdateRequest;
use App\Http\Requests\Api\V1\RemediationActionCreateRequest;
use App\Http\Requests\Api\V1\RemediationActionUpdateRequest;
use App\Http\Requests\Api\V1\RiskCreateRequest;
use App\Http\Requests\Api\V1\RiskUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\OpenApi\OpenApiDocumentBuilder;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\ReferenceData\ReferenceCatalogService;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Plugins\AssessmentsAudits\AssessmentReferenceData;
use PymeSec\Plugins\AssessmentsAudits\AssessmentsAuditsRepository;
use PymeSec\Plugins\AssetCatalog\AssetCatalogRepository;
use PymeSec\Plugins\ControlsCatalog\ControlsCatalogRepository;
use PymeSec\Plugins\FindingsRemediation\FindingsRemediationRepository;
use PymeSec\Plugins\RiskManagement\RiskRepository;

$apiPrincipalId = static function (Request $request): ?string {
    $principalId = $request->attributes->get('core.authenticated_principal_id');

    if (is_string($principalId) && $principalId !== '') {
        return $principalId;
    }

    $fallback = $request->input('principal_id', $request->query('principal_id'));

    return is_string($fallback) && $fallback !== '' ? $fallback : null;
};

$apiSuccess = static fn (mixed $data, array $meta = []) => response()->json([
    'data' => $data,
    'meta' => array_merge([
        'request_id' => request()->attributes->get('core.request_id'),
    ], $meta),
]);

$resolveTenancy = static function (Request $request, TenancyServiceInterface $tenancy, ?string $principalId = null) use ($apiPrincipalId) {
    $principal = $principalId ?? $apiPrincipalId($request);
    $organizationId = $request->input('organization_id', $request->query('organization_id'));
    $scopeId = $request->input('scope_id', $request->query('scope_id'));
    $membershipIds = $request->input('membership_ids', $request->query('membership_ids', []));

    if (! is_array($membershipIds)) {
        $membershipIds = [];
    }

    $membershipId = $request->input('membership_id', $request->query('membership_id'));

    if (is_string($membershipId) && $membershipId !== '') {
        $membershipIds[] = $membershipId;
    }

    return $tenancy->resolveContext(
        principalId: is_string($principal) && $principal !== '' ? $principal : null,
        requestedOrganizationId: is_string($organizationId) && $organizationId !== '' ? $organizationId : null,
        requestedScopeId: is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        requestedMembershipIds: $membershipIds,
    );
};

$assetCreateContractRules = AssetCreateRequest::contractRules();
$assetUpdateContractRules = AssetUpdateRequest::contractRules();
$riskCreateContractRules = RiskCreateRequest::contractRules();
$riskUpdateContractRules = RiskUpdateRequest::contractRules();
$controlCreateContractRules = ControlCreateRequest::contractRules();
$controlUpdateContractRules = ControlUpdateRequest::contractRules();
$assessmentCreateContractRules = AssessmentCreateRequest::contractRules();
$assessmentUpdateContractRules = AssessmentUpdateRequest::contractRules();
$findingCreateContractRules = FindingCreateRequest::contractRules();
$findingUpdateContractRules = FindingUpdateRequest::contractRules();
$remediationActionCreateContractRules = RemediationActionCreateRequest::contractRules();
$remediationActionUpdateContractRules = RemediationActionUpdateRequest::contractRules();
$assessmentReviewUpdateContractRules = AssessmentReviewUpdateRequest::contractRules();

$assetRuntimeRules = static function (array $contractRules, string $organizationId, ReferenceCatalogService $catalogs): array {
    return [
        ...$contractRules,
        'type' => ['required', 'string', Rule::in($catalogs->keys('assets.types', $organizationId))],
        'criticality' => ['required', 'string', Rule::in($catalogs->keys('assets.criticality', $organizationId))],
        'classification' => ['required', 'string', Rule::in($catalogs->keys('assets.classification', $organizationId))],
    ];
};

$riskRuntimeRules = static function (array $contractRules, string $organizationId, ReferenceCatalogService $catalogs): array {
    return [
        ...$contractRules,
        'category' => ['required', 'string', Rule::in($catalogs->keys('risks.categories', $organizationId))],
    ];
};

$controlRuntimeRules = static function (array $contractRules, string $organizationId, ControlsCatalogRepository $controls): array {
    $frameworkIds = array_values(array_map(
        static fn (array $framework): string => $framework['id'],
        $controls->frameworks($organizationId),
    ));

    return [
        ...$contractRules,
        'framework_id' => ['nullable', 'string', 'max:64', 'required_without:framework', Rule::in($frameworkIds)],
        'framework' => ['nullable', 'string', 'max:80', 'required_without:framework_id'],
    ];
};

$assessmentCreateRuntimeRules = static function (
    array $contractRules,
    string $organizationId,
    ?string $scopeId,
    AssessmentsAuditsRepository $assessments,
): array {
    return [
        ...$contractRules,
        'framework_id' => ['nullable', 'string', 'max:64', Rule::in($assessments->frameworkOptionIds($organizationId, $scopeId))],
        'status' => ['nullable', 'string', Rule::in(AssessmentReferenceData::statusKeys())],
    ];
};

$assessmentUpdateRuntimeRules = static function (
    array $contractRules,
    string $organizationId,
    ?string $scopeId,
    AssessmentsAuditsRepository $assessments,
): array {
    return [
        ...$contractRules,
        'framework_id' => ['nullable', 'string', 'max:64', Rule::in($assessments->frameworkOptionIds($organizationId, $scopeId))],
        'status' => ['required', 'string', Rule::in(AssessmentReferenceData::statusKeys())],
    ];
};

$findingRuntimeRules = static function (array $contractRules, string $organizationId, ReferenceCatalogService $catalogs): array {
    return [
        ...$contractRules,
        'severity' => ['required', 'string', Rule::in($catalogs->keys('findings.severity', $organizationId))],
    ];
};

$remediationActionRuntimeRules = static function (array $contractRules, string $organizationId, ReferenceCatalogService $catalogs): array {
    return [
        ...$contractRules,
        'status' => ['required', 'string', Rule::in($catalogs->keys('findings.remediation_status', $organizationId))],
    ];
};

$assessmentReviewRuntimeRules = static function (array $contractRules, string $organizationId, ReferenceCatalogService $catalogs): array {
    return [
        ...$contractRules,
        'result' => ['required', 'string', Rule::in($catalogs->keys('assessments.review_result', $organizationId))],
    ];
};

Route::prefix('v1')->group(function () use (
    $apiPrincipalId,
    $apiSuccess,
    $resolveTenancy,
    $assetCreateContractRules,
    $assetUpdateContractRules,
    $riskCreateContractRules,
    $riskUpdateContractRules,
    $controlCreateContractRules,
    $controlUpdateContractRules,
    $assessmentCreateContractRules,
    $assessmentUpdateContractRules,
    $findingCreateContractRules,
    $findingUpdateContractRules,
    $remediationActionCreateContractRules,
    $remediationActionUpdateContractRules,
    $assessmentReviewUpdateContractRules,
    $assetRuntimeRules,
    $riskRuntimeRules,
    $controlRuntimeRules,
    $assessmentCreateRuntimeRules,
    $assessmentUpdateRuntimeRules,
    $findingRuntimeRules,
    $remediationActionRuntimeRules,
    $assessmentReviewRuntimeRules,
): void {
    Route::get('/openapi', function (OpenApiDocumentBuilder $openApi) use ($apiSuccess) {
        return $apiSuccess($openApi->build());
    })->defaults('_openapi', [
        'operation_id' => 'coreGetOpenApi',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Get OpenAPI contract for active API routes',
        'responses' => [
            '200' => [
                'description' => 'OpenAPI contract payload',
            ],
        ],
    ]);

    Route::get('/meta/capabilities', function (
        Request $request,
        PermissionRegistryInterface $permissions,
        AuthorizationServiceInterface $authorization,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $allowedPermissions = [];

        foreach ($permissions->all() as $permission) {
            $allowed = $authorization->authorize(new AuthorizationContext(
                principal: new PrincipalReference(
                    id: $principalId,
                    provider: 'api',
                ),
                permission: $permission->key,
                memberships: $context->memberships,
                organizationId: $context->organization?->id,
                scopeId: $context->scope?->id,
            ))->allowed();

            if ($allowed) {
                $allowedPermissions[] = $permission->key;
            }
        }

        return $apiSuccess([
            'principal_id' => $principalId,
            'organization_id' => $context->organization?->id,
            'scope_id' => $context->scope?->id,
            'membership_ids' => array_values(array_map(
                static fn ($membership): string => $membership->id,
                $context->memberships,
            )),
            'permissions' => $allowedPermissions,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'coreGetCapabilities',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Resolve effective capabilities for current principal',
        'responses' => [
            '200' => [
                'description' => 'Capability snapshot resolved for caller context',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
        ],
    ]);

    Route::get('/lookups/reference-catalogs', function (
        Request $request,
        ReferenceCatalogService $catalogs,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;

        $rows = array_map(function (array $catalog) use ($catalogs, $organizationId): array {
            return [
                ...$catalog,
                'options' => $catalogs->optionRows($catalog['key'], $organizationId),
            ];
        }, $catalogs->manageableCatalogs());

        return $apiSuccess($rows, [
            'organization_id' => $organizationId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListCatalogs',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List managed reference catalogs with effective options',
        'responses' => [
            '200' => [
                'description' => 'Catalog list with effective options',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
        ],
    ]);

    Route::get('/lookups/reference-catalogs/{catalogKey}/options', function (
        Request $request,
        string $catalogKey,
        ReferenceCatalogService $catalogs,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $allowedCatalogKeys = array_values(array_map(
            static fn (array $catalog): string => $catalog['key'],
            $catalogs->manageableCatalogs(),
        ));
        abort_unless(in_array($catalogKey, $allowedCatalogKeys, true), 404);

        $context = $resolveTenancy($request, $tenancy, $principalId);

        return $apiSuccess([
            'catalog_key' => $catalogKey,
            'options' => $catalogs->optionRows($catalogKey, $context->organization?->id),
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListCatalogOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List effective options for one reference catalog',
        'responses' => [
            '200' => [
                'description' => 'Catalog options',
            ],
            '404' => [
                'description' => 'Unknown catalog',
            ],
        ],
    ]);

    Route::get('/lookups/actors/options', function (
        Request $request,
        FunctionalActorServiceInterface $actors,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId !== '', 422);

        $scopeId = $context->scope?->id;
        $rows = array_map(static fn ($actor): array => [
            'id' => $actor->id,
            'label' => $actor->displayName,
            'kind' => $actor->kind,
        ], $actors->actors($organizationId, $scopeId));

        return $apiSuccess($rows, [
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListActorOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List functional actor options for current organization and scope',
        'responses' => [
            '200' => [
                'description' => 'Actor option list',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
        ],
    ])->middleware('core.permission:plugin.actor-directory.actors.view');

    Route::get('/lookups/frameworks/options', function (
        Request $request,
        AssessmentsAuditsRepository $assessments,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId !== '', 422);

        $scopeId = $context->scope?->id;
        $rows = $assessments->frameworkOptions($organizationId, $scopeId);

        return $apiSuccess($rows, [
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListFrameworkOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List framework options for current organization and scope',
        'responses' => [
            '200' => [
                'description' => 'Framework option list',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
        ],
    ])->middleware('core.permission:plugin.controls-catalog.controls.view');

    Route::get('/lookups/controls/options', function (
        Request $request,
        ControlsCatalogRepository $controls,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId !== '', 422);
        $scopeId = $context->scope?->id;

        $rows = $objectAccess->filterRecords(
            records: $controls->all($organizationId, $scopeId),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            domainObjectType: 'control',
        );

        $options = array_map(static fn (array $control): array => [
            'id' => (string) ($control['id'] ?? ''),
            'label' => trim(sprintf(
                '%s · %s · %s',
                (string) ($control['name'] ?? ''),
                (string) ($control['framework'] ?? ''),
                (string) ($control['domain'] ?? ''),
            ), ' ·'),
        ], $rows);

        return $apiSuccess(array_values($options), [
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListControlOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List control options visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Control option list',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
        ],
    ])->middleware('core.permission:plugin.controls-catalog.controls.view');

    Route::get('/lookups/risks/options', function (
        Request $request,
        RiskRepository $risks,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId !== '', 422);
        $scopeId = $context->scope?->id;

        $rows = $objectAccess->filterRecords(
            records: $risks->all($organizationId, $scopeId),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            domainObjectType: 'risk',
        );

        $options = array_map(static fn (array $risk): array => [
            'id' => (string) ($risk['id'] ?? ''),
            'label' => trim(sprintf(
                '%s · %s',
                (string) ($risk['title'] ?? ''),
                (string) ($risk['category'] ?? ''),
            ), ' ·'),
        ], $rows);

        return $apiSuccess(array_values($options), [
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListRiskOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List risk options visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Risk option list',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
        ],
    ])->middleware('core.permission:plugin.risk-management.risks.view');

    Route::get('/assets', function (
        Request $request,
        AssetCatalogRepository $assets,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');
        $principalId = $apiPrincipalId($request);

        $rows = $objectAccess->filterRecords(
            records: $assets->all($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'asset',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'assetCatalogListAssets',
        'tags' => ['assets'],
        'tag_descriptions' => [
            'assets' => 'Asset catalog API surface.',
        ],
        'summary' => 'List assets visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Asset list',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.asset-catalog.assets.view');

    Route::get('/assets/{assetId}', function (
        Request $request,
        string $assetId,
        AssetCatalogRepository $assets,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $asset = $assets->find($assetId);
        abort_if($asset === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $asset['organization_id'] === $organizationId, 404);

        $scopeId = $request->input('scope_id');
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $asset['organization_id'],
            scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
            domainObjectType: 'asset',
            domainObjectId: $asset['id'],
        ), 403);

        if (is_string($scopeId) && $scopeId !== '' && $asset['scope_id'] !== '' && $asset['scope_id'] !== $scopeId) {
            abort(404);
        }

        return $apiSuccess($asset);
    })->defaults('_openapi', [
        'operation_id' => 'assetCatalogGetAsset',
        'tags' => ['assets'],
        'tag_descriptions' => [
            'assets' => 'Asset catalog API surface.',
        ],
        'summary' => 'Get one asset',
        'responses' => [
            '200' => [
                'description' => 'Asset detail',
            ],
            '404' => [
                'description' => 'Asset not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.asset-catalog.assets.view');

    Route::post('/assets', function (
        Request $request,
        AssetCatalogRepository $assets,
        ReferenceCatalogService $catalogs,
        FunctionalActorServiceInterface $actors,
    ) use ($apiSuccess, $assetCreateContractRules, $assetRuntimeRules) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);

        $validated = $request->validate($assetRuntimeRules(
            contractRules: $assetCreateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $asset = $assets->create($validated);
        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'asset',
                domainObjectId: $asset['id'],
                assignmentType: 'owner',
                organizationId: $asset['organization_id'],
                scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($asset);
    })->defaults('_openapi', [
        'operation_id' => 'assetCatalogCreateAsset',
        'tags' => ['assets'],
        'tag_descriptions' => [
            'assets' => 'Asset catalog API surface.',
        ],
        'summary' => 'Create an asset',
        'responses' => [
            '200' => [
                'description' => 'Asset created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => AssetCreateRequest::class,
        'governed_fields' => [
            'type' => 'assets.types',
            'criticality' => 'assets.criticality',
            'classification' => 'assets.classification',
        ],
        'lookup_fields' => [
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.asset-catalog.assets.manage');

    Route::patch('/assets/{assetId}', function (
        Request $request,
        string $assetId,
        AssetCatalogRepository $assets,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $assetRuntimeRules, $assetUpdateContractRules) {
        $existing = $assets->find($assetId);
        abort_if($existing === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'asset',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate($assetRuntimeRules(
            contractRules: $assetUpdateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $asset = $assets->update($assetId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);

        abort_if($asset === null, 404);

        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'asset',
                domainObjectId: $asset['id'],
                assignmentType: 'owner',
                organizationId: $asset['organization_id'],
                scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'asset')
            ->where('domain_object_id', $asset['id'])
            ->where('organization_id', $asset['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($asset);
    })->defaults('_openapi', [
        'operation_id' => 'assetCatalogUpdateAsset',
        'tags' => ['assets'],
        'tag_descriptions' => [
            'assets' => 'Asset catalog API surface.',
        ],
        'summary' => 'Update one asset',
        'responses' => [
            '200' => [
                'description' => 'Asset updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Asset not found in current context',
            ],
        ],
        'request_form_request' => AssetUpdateRequest::class,
        'governed_fields' => [
            'type' => 'assets.types',
            'criticality' => 'assets.criticality',
            'classification' => 'assets.classification',
        ],
        'lookup_fields' => [
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.asset-catalog.assets.manage');

    Route::get('/risks', function (
        Request $request,
        RiskRepository $risks,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');

        $rows = $objectAccess->filterRecords(
            records: $risks->all($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $apiPrincipalId($request),
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'risk',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'riskManagementListRisks',
        'tags' => ['risks'],
        'tag_descriptions' => [
            'risks' => 'Risk register API surface.',
        ],
        'summary' => 'List risks visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Risk list',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.risk-management.risks.view');

    Route::get('/risks/{riskId}', function (
        Request $request,
        string $riskId,
        RiskRepository $risks,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $risk = $risks->find($riskId);
        abort_if($risk === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $risk['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $risk['organization_id'],
            scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
            domainObjectType: 'risk',
            domainObjectId: $risk['id'],
        ), 403);

        return $apiSuccess($risk);
    })->defaults('_openapi', [
        'operation_id' => 'riskManagementGetRisk',
        'tags' => ['risks'],
        'tag_descriptions' => [
            'risks' => 'Risk register API surface.',
        ],
        'summary' => 'Get one risk',
        'responses' => [
            '200' => [
                'description' => 'Risk detail',
            ],
            '404' => [
                'description' => 'Risk not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.risk-management.risks.view');

    Route::post('/risks', function (
        Request $request,
        RiskRepository $risks,
        ReferenceCatalogService $catalogs,
        FunctionalActorServiceInterface $actors,
    ) use ($apiSuccess, $riskCreateContractRules, $riskRuntimeRules) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);

        $validated = $request->validate($riskRuntimeRules(
            contractRules: $riskCreateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $risk = $risks->create($validated);
        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'risk',
                domainObjectId: $risk['id'],
                assignmentType: 'owner',
                organizationId: $risk['organization_id'],
                scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($risk);
    })->defaults('_openapi', [
        'operation_id' => 'riskManagementCreateRisk',
        'tags' => ['risks'],
        'tag_descriptions' => [
            'risks' => 'Risk register API surface.',
        ],
        'summary' => 'Create a risk',
        'responses' => [
            '200' => [
                'description' => 'Risk created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => RiskCreateRequest::class,
        'governed_fields' => [
            'category' => 'risks.categories',
        ],
        'lookup_fields' => [
            'linked_asset_id' => '/api/v1/assets',
            'linked_control_id' => '/api/v1/lookups/controls/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.risk-management.risks.manage');

    Route::patch('/risks/{riskId}', function (
        Request $request,
        string $riskId,
        RiskRepository $risks,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $riskRuntimeRules, $riskUpdateContractRules) {
        $existing = $risks->find($riskId);
        abort_if($existing === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'risk',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate($riskRuntimeRules(
            contractRules: $riskUpdateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $risk = $risks->update($riskId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);

        abort_if($risk === null, 404);

        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'risk',
                domainObjectId: $risk['id'],
                assignmentType: 'owner',
                organizationId: $risk['organization_id'],
                scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'risk')
            ->where('domain_object_id', $risk['id'])
            ->where('organization_id', $risk['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($risk);
    })->defaults('_openapi', [
        'operation_id' => 'riskManagementUpdateRisk',
        'tags' => ['risks'],
        'tag_descriptions' => [
            'risks' => 'Risk register API surface.',
        ],
        'summary' => 'Update one risk',
        'responses' => [
            '200' => [
                'description' => 'Risk updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Risk not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => RiskUpdateRequest::class,
        'governed_fields' => [
            'category' => 'risks.categories',
        ],
        'lookup_fields' => [
            'linked_asset_id' => '/api/v1/assets',
            'linked_control_id' => '/api/v1/lookups/controls/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.risk-management.risks.manage');

    Route::get('/controls', function (
        Request $request,
        ControlsCatalogRepository $controls,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');

        $rows = $objectAccess->filterRecords(
            records: $controls->all($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $apiPrincipalId($request),
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'control',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'controlsCatalogListControls',
        'tags' => ['controls'],
        'tag_descriptions' => [
            'controls' => 'Controls catalog and mapping API surface.',
        ],
        'summary' => 'List controls visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Control list',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.controls-catalog.controls.view');

    Route::get('/controls/{controlId}', function (
        Request $request,
        string $controlId,
        ControlsCatalogRepository $controls,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $control = $controls->find($controlId);
        abort_if($control === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $control['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $control['organization_id'],
            scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
            domainObjectType: 'control',
            domainObjectId: $control['id'],
        ), 403);

        return $apiSuccess($control);
    })->defaults('_openapi', [
        'operation_id' => 'controlsCatalogGetControl',
        'tags' => ['controls'],
        'tag_descriptions' => [
            'controls' => 'Controls catalog and mapping API surface.',
        ],
        'summary' => 'Get one control',
        'responses' => [
            '200' => [
                'description' => 'Control detail',
            ],
            '404' => [
                'description' => 'Control not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.controls-catalog.controls.view');

    Route::post('/controls', function (
        Request $request,
        ControlsCatalogRepository $controls,
        FunctionalActorServiceInterface $actors,
    ) use ($apiSuccess, $controlCreateContractRules, $controlRuntimeRules) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);

        $validated = $request->validate($controlRuntimeRules(
            contractRules: $controlCreateContractRules,
            organizationId: $organizationId,
            controls: $controls,
        ));

        $control = $controls->create($validated);
        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'control',
                domainObjectId: $control['id'],
                assignmentType: 'owner',
                organizationId: $control['organization_id'],
                scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($control);
    })->defaults('_openapi', [
        'operation_id' => 'controlsCatalogCreateControl',
        'tags' => ['controls'],
        'tag_descriptions' => [
            'controls' => 'Controls catalog and mapping API surface.',
        ],
        'summary' => 'Create a control',
        'responses' => [
            '200' => [
                'description' => 'Control created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => ControlCreateRequest::class,
        'lookup_fields' => [
            'framework_id' => '/api/v1/lookups/frameworks/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.controls-catalog.controls.manage');

    Route::patch('/controls/{controlId}', function (
        Request $request,
        string $controlId,
        ControlsCatalogRepository $controls,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $controlRuntimeRules, $controlUpdateContractRules) {
        $existing = $controls->find($controlId);
        abort_if($existing === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'control',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate($controlRuntimeRules(
            contractRules: $controlUpdateContractRules,
            organizationId: $organizationId,
            controls: $controls,
        ));

        $control = $controls->update($controlId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);

        abort_if($control === null, 404);
        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'control',
                domainObjectId: $control['id'],
                assignmentType: 'owner',
                organizationId: $control['organization_id'],
                scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'control')
            ->where('domain_object_id', $control['id'])
            ->where('organization_id', $control['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $control['scope_id'] !== '' ? $control['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($control);
    })->defaults('_openapi', [
        'operation_id' => 'controlsCatalogUpdateControl',
        'tags' => ['controls'],
        'tag_descriptions' => [
            'controls' => 'Controls catalog and mapping API surface.',
        ],
        'summary' => 'Update one control',
        'responses' => [
            '200' => [
                'description' => 'Control updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Control not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => ControlUpdateRequest::class,
        'lookup_fields' => [
            'framework_id' => '/api/v1/lookups/frameworks/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.controls-catalog.controls.manage');

    Route::get('/assessments', function (
        Request $request,
        AssessmentsAuditsRepository $assessments,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');

        $rows = $objectAccess->filterRecords(
            records: $assessments->all($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $apiPrincipalId($request),
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'assessment',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsListAssessments',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'List assessments visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Assessment list',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.view');

    Route::get('/assessments/{assessmentId}', function (
        Request $request,
        string $assessmentId,
        AssessmentsAuditsRepository $assessments,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $assessment = $assessments->find($assessmentId);
        abort_if($assessment === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $assessment['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $assessment['organization_id'],
            scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
            domainObjectType: 'assessment',
            domainObjectId: $assessment['id'],
        ), 403);

        return $apiSuccess($assessment);
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsGetAssessment',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'Get one assessment',
        'responses' => [
            '200' => [
                'description' => 'Assessment detail',
            ],
            '404' => [
                'description' => 'Assessment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.view');

    Route::post('/assessments', function (
        Request $request,
        AssessmentsAuditsRepository $assessments,
        FunctionalActorServiceInterface $actors,
    ) use ($apiSuccess, $assessmentCreateContractRules, $assessmentCreateRuntimeRules) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');

        $validated = $request->validate($assessmentCreateRuntimeRules(
            contractRules: $assessmentCreateContractRules,
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            assessments: $assessments,
        ));

        $assessment = $assessments->create($validated);
        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'assessment',
                domainObjectId: $assessment['id'],
                assignmentType: 'owner',
                organizationId: $assessment['organization_id'],
                scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($assessment);
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsCreateAssessment',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'Create an assessment campaign',
        'responses' => [
            '200' => [
                'description' => 'Assessment created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => AssessmentCreateRequest::class,
        'governed_fields' => [
            'status' => 'assessments.status',
        ],
        'lookup_fields' => [
            'framework_id' => '/api/v1/lookups/frameworks/options',
            'control_ids' => '/api/v1/lookups/controls/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.manage');

    Route::patch('/assessments/{assessmentId}', function (
        Request $request,
        string $assessmentId,
        AssessmentsAuditsRepository $assessments,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $assessmentUpdateContractRules, $assessmentUpdateRuntimeRules) {
        $existing = $assessments->find($assessmentId);
        abort_if($existing === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'assessment',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $scopeId = $request->input('scope_id', $existing['scope_id'] !== '' ? $existing['scope_id'] : null);
        $validated = $request->validate($assessmentUpdateRuntimeRules(
            contractRules: $assessmentUpdateContractRules,
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            assessments: $assessments,
        ));

        $assessment = $assessments->update($assessmentId, $validated);
        abort_if($assessment === null, 404);

        $principalId = (string) $request->input('principal_id');
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'assessment',
                domainObjectId: $assessment['id'],
                assignmentType: 'owner',
                organizationId: $assessment['organization_id'],
                scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'assessment')
            ->where('domain_object_id', $assessment['id'])
            ->where('organization_id', $assessment['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($assessment);
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsUpdateAssessment',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'Update one assessment campaign',
        'responses' => [
            '200' => [
                'description' => 'Assessment updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Assessment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => AssessmentUpdateRequest::class,
        'governed_fields' => [
            'status' => 'assessments.status',
        ],
        'lookup_fields' => [
            'framework_id' => '/api/v1/lookups/frameworks/options',
            'control_ids' => '/api/v1/lookups/controls/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.manage');

    Route::get('/assessments/{assessmentId}/reviews', function (
        Request $request,
        string $assessmentId,
        AssessmentsAuditsRepository $assessments,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $assessment = $assessments->find($assessmentId);
        abort_if($assessment === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $assessment['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $assessment['organization_id'],
            scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
            domainObjectType: 'assessment',
            domainObjectId: $assessment['id'],
        ), 403);

        return $apiSuccess($assessments->reviews($assessmentId));
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsListAssessmentReviews',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'List control reviews for one assessment',
        'responses' => [
            '200' => [
                'description' => 'Assessment review list',
            ],
            '404' => [
                'description' => 'Assessment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.view');

    Route::patch('/assessments/{assessmentId}/reviews/{controlId}', function (
        Request $request,
        string $assessmentId,
        string $controlId,
        AssessmentsAuditsRepository $assessments,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess, $assessmentReviewUpdateContractRules, $assessmentReviewRuntimeRules) {
        $assessment = $assessments->find($assessmentId);
        abort_if($assessment === null, 404);

        $organizationId = (string) $request->input('organization_id', $assessment['organization_id']);
        abort_unless($organizationId === $assessment['organization_id'], 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $assessment['organization_id'],
            scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
            domainObjectType: 'assessment',
            domainObjectId: $assessment['id'],
        ), 403);

        $validated = $request->validate($assessmentReviewRuntimeRules(
            contractRules: $assessmentReviewUpdateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $review = $assessments->upsertReview(
            assessmentId: $assessmentId,
            controlId: $controlId,
            data: $validated,
            principalId: (string) $request->input('principal_id'),
        );
        abort_if($review === null, 404);

        return $apiSuccess($review);
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsUpdateAssessmentReview',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'Update one assessment control review',
        'responses' => [
            '200' => [
                'description' => 'Assessment review updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Assessment or control review not found',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => AssessmentReviewUpdateRequest::class,
        'governed_fields' => [
            'result' => 'assessments.review_result',
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.manage');

    Route::get('/findings', function (
        Request $request,
        FindingsRemediationRepository $findings,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');

        $rows = $objectAccess->filterRecords(
            records: $findings->allFindings($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $apiPrincipalId($request),
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'finding',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationListFindings',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'List findings visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Findings list',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.view');

    Route::get('/findings/{findingId}', function (
        Request $request,
        string $findingId,
        FindingsRemediationRepository $findings,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $finding = $findings->findFinding($findingId);
        abort_if($finding === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $finding['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ), 403);

        return $apiSuccess($finding);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationGetFinding',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Get one finding',
        'responses' => [
            '200' => [
                'description' => 'Finding detail',
            ],
            '404' => [
                'description' => 'Finding not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.view');

    Route::post('/findings', function (
        Request $request,
        FindingsRemediationRepository $findings,
        ReferenceCatalogService $catalogs,
        FunctionalActorServiceInterface $actors,
    ) use ($apiSuccess, $findingCreateContractRules, $findingRuntimeRules) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);

        $validated = $request->validate($findingRuntimeRules(
            contractRules: $findingCreateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $finding = $findings->createFinding($validated);
        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'finding',
                domainObjectId: $finding['id'],
                assignmentType: 'owner',
                organizationId: $finding['organization_id'],
                scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($finding);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationCreateFinding',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Create a finding',
        'responses' => [
            '200' => [
                'description' => 'Finding created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => FindingCreateRequest::class,
        'governed_fields' => [
            'severity' => 'findings.severity',
        ],
        'lookup_fields' => [
            'linked_control_id' => '/api/v1/lookups/controls/options',
            'linked_risk_id' => '/api/v1/lookups/risks/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.manage');

    Route::patch('/findings/{findingId}', function (
        Request $request,
        string $findingId,
        FindingsRemediationRepository $findings,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $findingUpdateContractRules, $findingRuntimeRules) {
        $existing = $findings->findFinding($findingId);
        abort_if($existing === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate($findingRuntimeRules(
            contractRules: $findingUpdateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $finding = $findings->updateFinding($findingId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);
        abort_if($finding === null, 404);

        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'finding',
                domainObjectId: $finding['id'],
                assignmentType: 'owner',
                organizationId: $finding['organization_id'],
                scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'finding')
            ->where('domain_object_id', $finding['id'])
            ->where('organization_id', $finding['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($finding);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationUpdateFinding',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Update one finding',
        'responses' => [
            '200' => [
                'description' => 'Finding updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Finding not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => FindingUpdateRequest::class,
        'governed_fields' => [
            'severity' => 'findings.severity',
        ],
        'lookup_fields' => [
            'linked_control_id' => '/api/v1/lookups/controls/options',
            'linked_risk_id' => '/api/v1/lookups/risks/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.manage');

    Route::get('/findings/{findingId}/actions', function (
        Request $request,
        string $findingId,
        FindingsRemediationRepository $findings,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $finding = $findings->findFinding($findingId);
        abort_if($finding === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $finding['organization_id'] === $organizationId, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ), 403);

        $rows = $objectAccess->filterRecords(
            records: $findings->actionsForFinding($findingId),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'remediation-action',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationListActionsForFinding',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'List remediation actions for one finding',
        'responses' => [
            '200' => [
                'description' => 'Remediation action list',
            ],
            '404' => [
                'description' => 'Finding not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.view');

    Route::post('/findings/{findingId}/actions', function (
        Request $request,
        string $findingId,
        FindingsRemediationRepository $findings,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $remediationActionCreateContractRules, $remediationActionRuntimeRules) {
        $finding = $findings->findFinding($findingId);
        abort_if($finding === null, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ), 403);

        $validated = $request->validate($remediationActionRuntimeRules(
            contractRules: $remediationActionCreateContractRules,
            organizationId: $finding['organization_id'],
            catalogs: $catalogs,
        ));

        $action = $findings->createAction($findingId, [
            ...$validated,
            'organization_id' => $finding['organization_id'],
            'scope_id' => $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        ]);

        $principalId = (string) $request->input('principal_id');
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'remediation-action',
                domainObjectId: $action['id'],
                assignmentType: 'owner',
                organizationId: $action['organization_id'],
                scopeId: $action['scope_id'] !== '' ? $action['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($action);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationCreateAction',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Create one remediation action for a finding',
        'responses' => [
            '200' => [
                'description' => 'Remediation action created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Finding not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => RemediationActionCreateRequest::class,
        'governed_fields' => [
            'status' => 'findings.remediation_status',
        ],
        'lookup_fields' => [
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.manage');

    Route::get('/remediation-actions/{actionId}', function (
        Request $request,
        string $actionId,
        FindingsRemediationRepository $findings,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $action = $findings->findAction($actionId);
        abort_if($action === null, 404);

        $finding = $findings->findFinding((string) $action['finding_id']);
        abort_if($finding === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $action['organization_id'] === $organizationId, 404);

        $principalId = $apiPrincipalId($request);
        $canAccess = $objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ) || $objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $action['organization_id'],
            scopeId: $action['scope_id'] !== '' ? $action['scope_id'] : null,
            domainObjectType: 'remediation-action',
            domainObjectId: $action['id'],
        );
        abort_unless($canAccess, 403);

        return $apiSuccess($action);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationGetAction',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Get one remediation action',
        'responses' => [
            '200' => [
                'description' => 'Remediation action detail',
            ],
            '404' => [
                'description' => 'Action not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.view');

    Route::patch('/remediation-actions/{actionId}', function (
        Request $request,
        string $actionId,
        FindingsRemediationRepository $findings,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $remediationActionUpdateContractRules, $remediationActionRuntimeRules) {
        $action = $findings->findAction($actionId);
        abort_if($action === null, 404);

        $finding = $findings->findFinding((string) $action['finding_id']);
        abort_if($finding === null, 404);

        $canAccess = $objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ) || $objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $action['organization_id'],
            scopeId: $action['scope_id'] !== '' ? $action['scope_id'] : null,
            domainObjectType: 'remediation-action',
            domainObjectId: $action['id'],
        );
        abort_unless($canAccess, 403);

        $organizationId = (string) $request->input('organization_id', $action['organization_id']);
        abort_unless($organizationId === $action['organization_id'], 404);

        $validated = $request->validate($remediationActionRuntimeRules(
            contractRules: $remediationActionUpdateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $updated = $findings->updateAction($actionId, [
            ...$validated,
            'organization_id' => $action['organization_id'],
            'scope_id' => $action['scope_id'] !== '' ? $action['scope_id'] : null,
        ]);
        abort_if($updated === null, 404);

        $principalId = (string) $request->input('principal_id');
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'remediation-action',
                domainObjectId: $updated['id'],
                assignmentType: 'owner',
                organizationId: $updated['organization_id'],
                scopeId: $updated['scope_id'] !== '' ? $updated['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'remediation-action')
            ->where('domain_object_id', $updated['id'])
            ->where('organization_id', $updated['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $updated['scope_id'] !== '' ? $updated['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($updated);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationUpdateAction',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Update one remediation action',
        'responses' => [
            '200' => [
                'description' => 'Remediation action updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Action not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => RemediationActionUpdateRequest::class,
        'governed_fields' => [
            'status' => 'findings.remediation_status',
        ],
        'lookup_fields' => [
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.manage');
});
