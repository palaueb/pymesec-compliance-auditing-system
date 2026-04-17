<?php

use App\Http\Requests\Api\V1\RiskCreateRequest;
use App\Http\Requests\Api\V1\RiskUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\ReferenceData\ReferenceCatalogService;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Plugins\RiskManagement\RiskRepository;

$apiContext = require dirname(__DIR__, 3).'/core/routes/api_context.php';
extract($apiContext, EXTR_SKIP);

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

Route::patch('/risks/{riskId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $riskId,
    string $assignmentId,
    RiskRepository $risks,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $risk = $risks->find($riskId);
    abort_if($risk === null, 404);
    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $risk['organization_id'],
        scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
        domainObjectType: 'risk',
        domainObjectId: $risk['id'],
    ), 403);

    $assignment = collect($actors->assignmentsFor(
        domainObjectType: 'risk',
        domainObjectId: $risk['id'],
        organizationId: $risk['organization_id'],
        scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
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
    'operation_id' => 'riskManagementRemoveRiskOwner',
    'tags' => ['risks'],
    'tag_descriptions' => [
        'risks' => 'Risk register API surface.',
    ],
    'summary' => 'Remove one owner assignment from a risk',
    'responses' => [
        '200' => [
            'description' => 'Owner assignment removed',
        ],
        '404' => [
            'description' => 'Risk or assignment not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.risk-management.risks.manage');

Route::post('/risks/{riskId}/artifacts', function (
    Request $request,
    string $riskId,
    RiskRepository $risks,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $risk = $risks->find($riskId);
    abort_if($risk === null, 404);
    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $risk['organization_id'],
        scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
        domainObjectType: 'risk',
        domainObjectId: $risk['id'],
    ), 403);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
        'membership_id' => ['nullable', 'string', 'max:120'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    $membershipId = $validated['membership_id'] ?? $request->input('membership_id');

    $record = $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'risk-management',
        subjectType: 'risk',
        subjectId: $riskId,
        artifactType: (string) ($validated['artifact_type'] ?? 'evidence'),
        label: (string) ($validated['label'] ?? 'Risk evidence'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $risk['organization_id'],
        scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
        metadata: [
            'plugin' => 'risk-management',
            'category' => $risk['category'],
            'linked_asset_id' => $risk['linked_asset_id'],
            'linked_control_id' => $risk['linked_control_id'],
        ],
    ));

    return $apiSuccess($record->toArray());
})->defaults('_openapi', [
    'operation_id' => 'riskManagementAttachRiskArtifact',
    'tags' => ['risks'],
    'tag_descriptions' => [
        'risks' => 'Risk register API surface.',
    ],
    'summary' => 'Upload one artifact to a risk',
    'responses' => [
        '200' => [
            'description' => 'Artifact uploaded',
        ],
        '404' => [
            'description' => 'Risk not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_body' => [
        'required' => true,
        'content' => [
            'multipart/form-data' => [
                'schema' => [
                    'type' => 'object',
                    'required' => ['artifact'],
                    'properties' => [
                        'artifact' => ['type' => 'string', 'format' => 'binary'],
                        'label' => ['type' => 'string'],
                        'artifact_type' => ['type' => 'string'],
                        'membership_id' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
])->middleware('core.permission:plugin.risk-management.risks.manage');

Route::post('/risks/{riskId}/transitions/{transitionKey}', function (
    Request $request,
    string $riskId,
    string $transitionKey,
    WorkflowServiceInterface $workflows,
    RiskRepository $risks,
    ObjectAccessService $objectAccess,
    TenancyServiceInterface $tenancy,
) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
    $risk = $risks->find($riskId);
    abort_if($risk === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $risk['organization_id'],
        scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
        domainObjectType: 'risk',
        domainObjectId: $risk['id'],
    ), 403);

    $context = $resolveTenancy($request, $tenancy, $principalId);
    $organizationId = $context->organization?->id;
    abort_unless(is_string($organizationId) && $organizationId === $risk['organization_id'], 404);

    $workflows->transition(
        workflowKey: 'plugin.risk-management.risk-lifecycle',
        subjectType: 'risk',
        subjectId: $riskId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'api'),
            memberships: $context->memberships,
            organizationId: $organizationId,
            scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
            membershipId: $context->membershipIds()[0] ?? null,
        ),
    );

    return $apiSuccess([
        'risk' => $risks->find($riskId),
        'transition' => $transitionKey,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'riskManagementTransitionRisk',
    'tags' => ['risks'],
    'tag_descriptions' => [
        'risks' => 'Risk register API surface.',
    ],
    'summary' => 'Apply one workflow transition to a risk',
    'responses' => [
        '200' => [
            'description' => 'Transition applied',
        ],
        '404' => [
            'description' => 'Risk not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.risk-management.risks.manage');
