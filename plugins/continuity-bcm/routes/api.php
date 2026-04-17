<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\ReferenceData\ReferenceCatalogService;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Plugins\ContinuityBcm\ContinuityBcmRepository;

$apiContext = require dirname(__DIR__, 3).'/core/routes/api_context.php';
extract($apiContext, EXTR_SKIP);

Route::get('/continuity/services', function (
    Request $request,
    ContinuityBcmRepository $continuity,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);
    $scopeId = $request->input('scope_id');
    $principalId = $apiPrincipalId($request);

    $rows = $objectAccess->filterRecords(
        records: $continuity->allServices($organizationId, is_string($scopeId) ? $scopeId : null),
        idKey: 'id',
        principalId: $principalId,
        organizationId: $organizationId,
        scopeId: is_string($scopeId) ? $scopeId : null,
        domainObjectType: 'continuity-service',
    );

    return $apiSuccess($rows);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmListServices',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'List continuity services visible in current context',
    'responses' => [
        '200' => [
            'description' => 'Continuity service list',
        ],
        '422' => [
            'description' => 'Organization context required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.continuity-bcm.plans.view');

Route::get('/continuity/services/{serviceId}', function (
    Request $request,
    string $serviceId,
    ContinuityBcmRepository $continuity,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $service = $continuity->findService($serviceId);
    abort_if($service === null, 404);

    $organizationId = (string) $request->input('organization_id');
    abort_unless($organizationId !== '' && $service['organization_id'] === $organizationId, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $service['organization_id'],
        scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
        domainObjectType: 'continuity-service',
        domainObjectId: $service['id'],
    ), 403);

    return $apiSuccess($service);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmGetService',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Get one continuity service',
    'responses' => [
        '200' => [
            'description' => 'Continuity service detail',
        ],
        '404' => [
            'description' => 'Continuity service not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.continuity-bcm.plans.view');

Route::post('/continuity/services', function (
    Request $request,
    ContinuityBcmRepository $continuity,
    FunctionalActorServiceInterface $actors,
    ReferenceCatalogService $catalogs,
) use ($apiSuccess, $apiPrincipalId) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'impact_tier' => ['required', 'string', Rule::in($catalogs->keys('continuity.impact_tier', $organizationId))],
        'recovery_time_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        'recovery_point_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        'linked_asset_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $service = $continuity->createService([
        ...$validated,
        'organization_id' => $organizationId,
    ]);

    $principalId = $apiPrincipalId($request);
    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'continuity-service',
            domainObjectId: $service['id'],
            assignmentType: 'owner',
            organizationId: $service['organization_id'],
            scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
        );
    }

    return $apiSuccess($service);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmCreateService',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Create one continuity service',
    'responses' => [
        '200' => [
            'description' => 'Continuity service created',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'title' => ['required', 'string', 'max:160'],
        'impact_tier' => ['required', 'string'],
        'recovery_time_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        'recovery_point_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        'linked_asset_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'governed_fields' => [
        'impact_tier' => 'continuity.impact_tier',
    ],
    'lookup_fields' => [
        'linked_asset_id' => '/api/v1/assets',
        'linked_risk_id' => '/api/v1/lookups/risks/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

Route::patch('/continuity/services/{serviceId}', function (
    Request $request,
    string $serviceId,
    ContinuityBcmRepository $continuity,
    FunctionalActorServiceInterface $actors,
    ReferenceCatalogService $catalogs,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $existing = $continuity->findService($serviceId);
    abort_if($existing === null, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $existing['organization_id'],
        scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
        domainObjectType: 'continuity-service',
        domainObjectId: $existing['id'],
    ), 403);

    $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
    abort_unless($organizationId === $existing['organization_id'], 404);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'impact_tier' => ['required', 'string', Rule::in($catalogs->keys('continuity.impact_tier', $organizationId))],
        'recovery_time_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        'recovery_point_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        'linked_asset_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $service = $continuity->updateService($serviceId, [
        ...$validated,
        'organization_id' => $organizationId,
    ]);
    abort_if($service === null, 404);

    $principalId = $apiPrincipalId($request);
    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'continuity-service',
            domainObjectId: $service['id'],
            assignmentType: 'owner',
            organizationId: $service['organization_id'],
            scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
        );
    }

    DB::table('functional_assignments')
        ->where('domain_object_type', 'continuity-service')
        ->where('domain_object_id', $service['id'])
        ->where('organization_id', $service['organization_id'])
        ->where('is_active', true)
        ->update([
            'scope_id' => $service['scope_id'] !== '' ? $service['scope_id'] : null,
            'updated_at' => now(),
        ]);

    return $apiSuccess($service);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmUpdateService',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Update one continuity service',
    'responses' => [
        '200' => [
            'description' => 'Continuity service updated',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Continuity service not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'title' => ['required', 'string', 'max:160'],
        'impact_tier' => ['required', 'string'],
        'recovery_time_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        'recovery_point_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        'linked_asset_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'governed_fields' => [
        'impact_tier' => 'continuity.impact_tier',
    ],
    'lookup_fields' => [
        'linked_asset_id' => '/api/v1/assets',
        'linked_risk_id' => '/api/v1/lookups/risks/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

Route::post('/continuity/services/{serviceId}/dependencies', function (
    Request $request,
    string $serviceId,
    ContinuityBcmRepository $continuity,
    ReferenceCatalogService $catalogs,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $service = $continuity->findService($serviceId);
    abort_if($service === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $service['organization_id'],
        scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
        domainObjectType: 'continuity-service',
        domainObjectId: $service['id'],
    ), 403);

    $validated = $request->validate([
        'depends_on_service_id' => ['required', 'string', 'max:120'],
        'dependency_kind' => ['required', 'string', Rule::in($catalogs->keys('continuity.dependency_kind', $service['organization_id']))],
        'recovery_notes' => ['nullable', 'string', 'max:255'],
    ]);

    $continuity->addServiceDependency($serviceId, [
        ...$validated,
        'organization_id' => $service['organization_id'],
    ]);

    return $apiSuccess([
        'service_id' => $service['id'],
        'depends_on_service_id' => (string) $validated['depends_on_service_id'],
        'dependency_kind' => (string) $validated['dependency_kind'],
    ]);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmAddServiceDependency',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Create or update one continuity service dependency',
    'responses' => [
        '200' => [
            'description' => 'Dependency stored',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Continuity service not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'depends_on_service_id' => ['required', 'string', 'max:120'],
        'dependency_kind' => ['required', 'string'],
        'recovery_notes' => ['nullable', 'string', 'max:255'],
    ],
    'governed_fields' => [
        'dependency_kind' => 'continuity.dependency_kind',
    ],
    'lookup_fields' => [
        'depends_on_service_id' => '/api/v1/continuity/services',
    ],
])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

Route::patch('/continuity/services/{serviceId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $serviceId,
    string $assignmentId,
    ContinuityBcmRepository $continuity,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $service = $continuity->findService($serviceId);
    abort_if($service === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $service['organization_id'],
        scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
        domainObjectType: 'continuity-service',
        domainObjectId: $service['id'],
    ), 403);

    $assignment = DB::table('functional_assignments')
        ->where('id', $assignmentId)
        ->where('domain_object_type', 'continuity-service')
        ->where('domain_object_id', $service['id'])
        ->where('organization_id', $service['organization_id'])
        ->where('assignment_type', 'owner')
        ->where('is_active', true)
        ->first(['id']);
    abort_if($assignment === null, 404);

    $actors->deactivateAssignment(
        assignmentId: $assignmentId,
        deactivatedByPrincipalId: $principalId,
    );

    return $apiSuccess([
        'removed' => true,
        'assignment_id' => $assignmentId,
        'service_id' => $service['id'],
    ]);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmRemoveServiceOwner',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Remove one owner assignment from a continuity service',
    'responses' => [
        '200' => [
            'description' => 'Continuity service owner assignment removed',
        ],
        '404' => [
            'description' => 'Continuity service or assignment not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

Route::post('/continuity/services/{serviceId}/artifacts', function (
    Request $request,
    string $serviceId,
    ContinuityBcmRepository $continuity,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $service = $continuity->findService($serviceId);
    abort_if($service === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $service['organization_id'],
        scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
        domainObjectType: 'continuity-service',
        domainObjectId: $service['id'],
    ), 403);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
        'membership_id' => ['nullable', 'string', 'max:120'],
    ]);

    $record = $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'continuity-bcm',
        subjectType: 'continuity-service',
        subjectId: $serviceId,
        artifactType: (string) ($validated['artifact_type'] ?? 'continuity-record'),
        label: (string) ($validated['label'] ?? 'Continuity record'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($validated['membership_id'] ?? null) && $validated['membership_id'] !== ''
            ? $validated['membership_id']
            : null,
        organizationId: $service['organization_id'],
        scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
        metadata: [
            'plugin' => 'continuity-bcm',
            'impact_tier' => $service['impact_tier'],
            'linked_asset_id' => $service['linked_asset_id'],
            'linked_risk_id' => $service['linked_risk_id'],
        ],
    ));

    return $apiSuccess($record->toArray());
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmAttachServiceArtifact',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Upload one artifact to a continuity service',
    'responses' => [
        '200' => [
            'description' => 'Continuity service artifact uploaded',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Continuity service not found in current context',
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
])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

Route::post('/continuity/services/{serviceId}/transitions/{transitionKey}', function (
    Request $request,
    string $serviceId,
    string $transitionKey,
    ContinuityBcmRepository $continuity,
    WorkflowServiceInterface $workflows,
    ObjectAccessService $objectAccess,
    TenancyServiceInterface $tenancy,
) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
    $service = $continuity->findService($serviceId);
    abort_if($service === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $service['organization_id'],
        scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
        domainObjectType: 'continuity-service',
        domainObjectId: $service['id'],
    ), 403);

    $context = $resolveTenancy($request, $tenancy, $principalId);
    $organizationId = $context->organization?->id;
    abort_unless(is_string($organizationId) && $organizationId === $service['organization_id'], 404);

    $workflows->transition(
        workflowKey: 'plugin.continuity-bcm.service-lifecycle',
        subjectType: 'continuity-service',
        subjectId: $serviceId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'api'),
            memberships: $context->memberships,
            organizationId: $organizationId,
            scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
            membershipId: $context->membershipIds()[0] ?? null,
        ),
    );

    return $apiSuccess([
        'service' => $continuity->findService($serviceId),
        'transition' => $transitionKey,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmTransitionService',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Apply one workflow transition to a continuity service',
    'responses' => [
        '200' => [
            'description' => 'Continuity service transitioned',
        ],
        '404' => [
            'description' => 'Continuity service not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

Route::get('/continuity/plans', function (
    Request $request,
    ContinuityBcmRepository $continuity,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);
    $scopeId = $request->input('scope_id');
    $principalId = $apiPrincipalId($request);

    $rows = $objectAccess->filterRecords(
        records: $continuity->allPlans($organizationId, is_string($scopeId) ? $scopeId : null),
        idKey: 'id',
        principalId: $principalId,
        organizationId: $organizationId,
        scopeId: is_string($scopeId) ? $scopeId : null,
        domainObjectType: 'continuity-plan',
    );

    return $apiSuccess($rows);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmListPlans',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'List recovery plans visible in current context',
    'responses' => [
        '200' => [
            'description' => 'Recovery plan list',
        ],
        '422' => [
            'description' => 'Organization context required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.continuity-bcm.plans.view');

Route::get('/continuity/plans/{planId}', function (
    Request $request,
    string $planId,
    ContinuityBcmRepository $continuity,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $plan = $continuity->findPlan($planId);
    abort_if($plan === null, 404);

    $organizationId = (string) $request->input('organization_id');
    abort_unless($organizationId !== '' && $plan['organization_id'] === $organizationId, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $plan['organization_id'],
        scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
        domainObjectType: 'continuity-plan',
        domainObjectId: $plan['id'],
    ), 403);

    return $apiSuccess($plan);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmGetPlan',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Get one recovery plan',
    'responses' => [
        '200' => [
            'description' => 'Recovery plan detail',
        ],
        '404' => [
            'description' => 'Recovery plan not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.continuity-bcm.plans.view');

Route::post('/continuity/services/{serviceId}/plans', function (
    Request $request,
    string $serviceId,
    ContinuityBcmRepository $continuity,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $service = $continuity->findService($serviceId);
    abort_if($service === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $service['organization_id'],
        scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
        domainObjectType: 'continuity-service',
        domainObjectId: $service['id'],
    ), 403);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'strategy_summary' => ['required', 'string', 'max:255'],
        'test_due_on' => ['nullable', 'date'],
        'linked_policy_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $plan = $continuity->createPlan($serviceId, [
        ...$validated,
        'organization_id' => $service['organization_id'],
        'scope_id' => is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== ''
            ? $validated['scope_id']
            : ($service['scope_id'] !== '' ? $service['scope_id'] : null),
    ]);

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'continuity-plan',
            domainObjectId: $plan['id'],
            assignmentType: 'owner',
            organizationId: $plan['organization_id'],
            scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: $principalId,
        );
    }

    return $apiSuccess($plan);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmCreatePlan',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Create one recovery plan for a continuity service',
    'responses' => [
        '200' => [
            'description' => 'Recovery plan created',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Continuity service not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'title' => ['required', 'string', 'max:160'],
        'strategy_summary' => ['required', 'string', 'max:255'],
        'test_due_on' => ['nullable', 'date'],
        'linked_policy_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'lookup_fields' => [
        'linked_policy_id' => '/api/v1/policies',
        'linked_finding_id' => '/api/v1/lookups/findings/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

Route::patch('/continuity/plans/{planId}', function (
    Request $request,
    string $planId,
    ContinuityBcmRepository $continuity,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $existing = $continuity->findPlan($planId);
    abort_if($existing === null, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $existing['organization_id'],
        scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
        domainObjectType: 'continuity-plan',
        domainObjectId: $existing['id'],
    ), 403);

    $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
    abort_unless($organizationId === $existing['organization_id'], 404);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'strategy_summary' => ['required', 'string', 'max:255'],
        'test_due_on' => ['nullable', 'date'],
        'linked_policy_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $plan = $continuity->updatePlan($planId, [
        ...$validated,
        'organization_id' => $organizationId,
    ]);
    abort_if($plan === null, 404);

    $principalId = $apiPrincipalId($request);
    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'continuity-plan',
            domainObjectId: $plan['id'],
            assignmentType: 'owner',
            organizationId: $plan['organization_id'],
            scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
        );
    }

    DB::table('functional_assignments')
        ->where('domain_object_type', 'continuity-plan')
        ->where('domain_object_id', $plan['id'])
        ->where('organization_id', $plan['organization_id'])
        ->where('is_active', true)
        ->update([
            'scope_id' => $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
            'updated_at' => now(),
        ]);

    return $apiSuccess($plan);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmUpdatePlan',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Update one recovery plan',
    'responses' => [
        '200' => [
            'description' => 'Recovery plan updated',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Recovery plan not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'title' => ['required', 'string', 'max:160'],
        'strategy_summary' => ['required', 'string', 'max:255'],
        'test_due_on' => ['nullable', 'date'],
        'linked_policy_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'lookup_fields' => [
        'linked_policy_id' => '/api/v1/policies',
        'linked_finding_id' => '/api/v1/lookups/findings/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

Route::post('/continuity/plans/{planId}/exercises', function (
    Request $request,
    string $planId,
    ContinuityBcmRepository $continuity,
    ReferenceCatalogService $catalogs,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $plan = $continuity->findPlan($planId);
    abort_if($plan === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $plan['organization_id'],
        scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
        domainObjectType: 'continuity-plan',
        domainObjectId: $plan['id'],
    ), 403);

    $validated = $request->validate([
        'exercise_date' => ['required', 'date'],
        'exercise_type' => ['required', 'string', Rule::in($catalogs->keys('continuity.exercise_type', $plan['organization_id']))],
        'scenario_summary' => ['required', 'string', 'max:255'],
        'outcome' => ['required', 'string', Rule::in($catalogs->keys('continuity.exercise_outcome', $plan['organization_id']))],
        'follow_up_summary' => ['nullable', 'string', 'max:255'],
    ]);

    $continuity->recordExercise($planId, [
        ...$validated,
        'organization_id' => $plan['organization_id'],
    ]);

    return $apiSuccess([
        'plan_id' => $plan['id'],
        'exercise_date' => (string) $validated['exercise_date'],
        'exercise_type' => (string) $validated['exercise_type'],
    ]);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmRecordPlanExercise',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Record one recovery plan exercise',
    'responses' => [
        '200' => [
            'description' => 'Recovery plan exercise recorded',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Recovery plan not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'exercise_date' => ['required', 'date'],
        'exercise_type' => ['required', 'string'],
        'scenario_summary' => ['required', 'string', 'max:255'],
        'outcome' => ['required', 'string'],
        'follow_up_summary' => ['nullable', 'string', 'max:255'],
    ],
    'governed_fields' => [
        'exercise_type' => 'continuity.exercise_type',
        'outcome' => 'continuity.exercise_outcome',
    ],
])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

Route::post('/continuity/plans/{planId}/executions', function (
    Request $request,
    string $planId,
    ContinuityBcmRepository $continuity,
    ReferenceCatalogService $catalogs,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $plan = $continuity->findPlan($planId);
    abort_if($plan === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $plan['organization_id'],
        scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
        domainObjectType: 'continuity-plan',
        domainObjectId: $plan['id'],
    ), 403);

    $validated = $request->validate([
        'executed_on' => ['required', 'date'],
        'execution_type' => ['required', 'string', Rule::in($catalogs->keys('continuity.execution_type', $plan['organization_id']))],
        'status' => ['required', 'string', Rule::in($catalogs->keys('continuity.execution_status', $plan['organization_id']))],
        'participants' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string', 'max:255'],
    ]);

    $continuity->recordTestExecution($planId, [
        ...$validated,
        'organization_id' => $plan['organization_id'],
    ]);

    return $apiSuccess([
        'plan_id' => $plan['id'],
        'executed_on' => (string) $validated['executed_on'],
        'execution_type' => (string) $validated['execution_type'],
        'status' => (string) $validated['status'],
    ]);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmRecordPlanExecution',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Record one recovery plan test execution',
    'responses' => [
        '200' => [
            'description' => 'Recovery plan execution recorded',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Recovery plan not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'executed_on' => ['required', 'date'],
        'execution_type' => ['required', 'string'],
        'status' => ['required', 'string'],
        'participants' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string', 'max:255'],
    ],
    'governed_fields' => [
        'execution_type' => 'continuity.execution_type',
        'status' => 'continuity.execution_status',
    ],
])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

Route::patch('/continuity/plans/{planId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $planId,
    string $assignmentId,
    ContinuityBcmRepository $continuity,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $plan = $continuity->findPlan($planId);
    abort_if($plan === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $plan['organization_id'],
        scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
        domainObjectType: 'continuity-plan',
        domainObjectId: $plan['id'],
    ), 403);

    $assignment = DB::table('functional_assignments')
        ->where('id', $assignmentId)
        ->where('domain_object_type', 'continuity-plan')
        ->where('domain_object_id', $plan['id'])
        ->where('organization_id', $plan['organization_id'])
        ->where('assignment_type', 'owner')
        ->where('is_active', true)
        ->first(['id']);
    abort_if($assignment === null, 404);

    $actors->deactivateAssignment(
        assignmentId: $assignmentId,
        deactivatedByPrincipalId: $principalId,
    );

    return $apiSuccess([
        'removed' => true,
        'assignment_id' => $assignmentId,
        'plan_id' => $plan['id'],
    ]);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmRemovePlanOwner',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Remove one owner assignment from a recovery plan',
    'responses' => [
        '200' => [
            'description' => 'Recovery plan owner assignment removed',
        ],
        '404' => [
            'description' => 'Recovery plan or assignment not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

Route::post('/continuity/plans/{planId}/artifacts', function (
    Request $request,
    string $planId,
    ContinuityBcmRepository $continuity,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $plan = $continuity->findPlan($planId);
    abort_if($plan === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $plan['organization_id'],
        scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
        domainObjectType: 'continuity-plan',
        domainObjectId: $plan['id'],
    ), 403);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
        'membership_id' => ['nullable', 'string', 'max:120'],
    ]);

    $record = $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'continuity-bcm',
        subjectType: 'continuity-plan',
        subjectId: $planId,
        artifactType: (string) ($validated['artifact_type'] ?? 'recovery-plan'),
        label: (string) ($validated['label'] ?? 'Recovery plan'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($validated['membership_id'] ?? null) && $validated['membership_id'] !== ''
            ? $validated['membership_id']
            : null,
        organizationId: $plan['organization_id'],
        scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
        metadata: [
            'plugin' => 'continuity-bcm',
            'linked_policy_id' => $plan['linked_policy_id'],
            'linked_finding_id' => $plan['linked_finding_id'],
        ],
    ));

    return $apiSuccess($record->toArray());
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmAttachPlanArtifact',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Upload one artifact to a recovery plan',
    'responses' => [
        '200' => [
            'description' => 'Recovery plan artifact uploaded',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Recovery plan not found in current context',
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
])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

Route::post('/continuity/plans/{planId}/transitions/{transitionKey}', function (
    Request $request,
    string $planId,
    string $transitionKey,
    ContinuityBcmRepository $continuity,
    WorkflowServiceInterface $workflows,
    ObjectAccessService $objectAccess,
    TenancyServiceInterface $tenancy,
) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
    $plan = $continuity->findPlan($planId);
    abort_if($plan === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $plan['organization_id'],
        scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
        domainObjectType: 'continuity-plan',
        domainObjectId: $plan['id'],
    ), 403);

    $context = $resolveTenancy($request, $tenancy, $principalId);
    $organizationId = $context->organization?->id;
    abort_unless(is_string($organizationId) && $organizationId === $plan['organization_id'], 404);

    $workflows->transition(
        workflowKey: 'plugin.continuity-bcm.plan-lifecycle',
        subjectType: 'continuity-plan',
        subjectId: $planId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'api'),
            memberships: $context->memberships,
            organizationId: $organizationId,
            scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
            membershipId: $context->membershipIds()[0] ?? null,
        ),
    );

    return $apiSuccess([
        'plan' => $continuity->findPlan($planId),
        'transition' => $transitionKey,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'continuityBcmTransitionPlan',
    'tags' => ['continuity'],
    'tag_descriptions' => [
        'continuity' => 'Continuity service and recovery plan API surface.',
    ],
    'summary' => 'Apply one workflow transition to a recovery plan',
    'responses' => [
        '200' => [
            'description' => 'Recovery plan transitioned',
        ],
        '404' => [
            'description' => 'Recovery plan not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.continuity-bcm.plans.manage');
