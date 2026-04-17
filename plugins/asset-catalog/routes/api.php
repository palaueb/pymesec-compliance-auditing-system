<?php

use App\Http\Requests\Api\V1\AssetCreateRequest;
use App\Http\Requests\Api\V1\AssetUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\ReferenceData\ReferenceCatalogService;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Plugins\AssetCatalog\AssetCatalogRepository;

$apiContext = require dirname(__DIR__, 3).'/core/routes/api_context.php';
extract($apiContext, EXTR_SKIP);

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

Route::patch('/assets/{assetId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $assetId,
    string $assignmentId,
    AssetCatalogRepository $assets,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $asset = $assets->find($assetId);
    abort_if($asset === null, 404);
    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $asset['organization_id'],
        scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
        domainObjectType: 'asset',
        domainObjectId: $asset['id'],
    ), 403);

    $assignment = collect($actors->assignmentsFor(
        domainObjectType: 'asset',
        domainObjectId: $asset['id'],
        organizationId: $asset['organization_id'],
        scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
    ))->first(fn ($candidate) => $candidate->id === $assignmentId && $candidate->assignmentType === 'owner');
    abort_if($assignment === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $actors->deactivateAssignment(
        assignmentId: $assignmentId,
        deactivatedByPrincipalId: $principalId,
    );

    return $apiSuccess([
        'assignment_id' => $assignmentId,
        'removed' => true,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'assetCatalogRemoveAssetOwner',
    'tags' => ['assets'],
    'tag_descriptions' => [
        'assets' => 'Asset catalog API surface.',
    ],
    'summary' => 'Remove one owner assignment from an asset',
    'responses' => [
        '200' => [
            'description' => 'Owner assignment removed',
        ],
        '404' => [
            'description' => 'Asset or assignment not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.asset-catalog.assets.manage');

Route::post('/assets/{assetId}/transitions/{transitionKey}', function (
    Request $request,
    string $assetId,
    string $transitionKey,
    WorkflowServiceInterface $workflows,
    AssetCatalogRepository $assets,
    ObjectAccessService $objectAccess,
    TenancyServiceInterface $tenancy,
) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
    $asset = $assets->find($assetId);
    abort_if($asset === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $asset['organization_id'],
        scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
        domainObjectType: 'asset',
        domainObjectId: $asset['id'],
    ), 403);

    $context = $resolveTenancy($request, $tenancy, $principalId);
    $organizationId = $context->organization?->id;
    abort_unless(is_string($organizationId) && $organizationId === $asset['organization_id'], 404);

    $workflows->transition(
        workflowKey: 'plugin.asset-catalog.asset-lifecycle',
        subjectType: 'asset',
        subjectId: $assetId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'api'),
            memberships: $context->memberships,
            organizationId: $organizationId,
            scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
            membershipId: $context->membershipIds()[0] ?? null,
        ),
    );

    return $apiSuccess([
        'asset' => $assets->find($assetId),
        'transition' => $transitionKey,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'assetCatalogTransitionAsset',
    'tags' => ['assets'],
    'tag_descriptions' => [
        'assets' => 'Asset catalog API surface.',
    ],
    'summary' => 'Apply one workflow transition to an asset',
    'responses' => [
        '200' => [
            'description' => 'Transition applied',
        ],
        '404' => [
            'description' => 'Asset not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.asset-catalog.assets.manage');
