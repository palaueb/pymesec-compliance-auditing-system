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
use PymeSec\Plugins\DataFlowsPrivacy\DataFlowsPrivacyRepository;

$apiContext = require base_path('routes/api_context.php');
extract($apiContext, EXTR_SKIP);

Route::get('/privacy/data-flows', function (
    Request $request,
    DataFlowsPrivacyRepository $privacy,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);
    $scopeId = $request->input('scope_id');
    $principalId = $apiPrincipalId($request);

    $rows = $objectAccess->filterRecords(
        records: $privacy->allDataFlows($organizationId, is_string($scopeId) ? $scopeId : null),
        idKey: 'id',
        principalId: $principalId,
        organizationId: $organizationId,
        scopeId: is_string($scopeId) ? $scopeId : null,
        domainObjectType: 'privacy-data-flow',
    );

    return $apiSuccess($rows);
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyListDataFlows',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'List privacy data flows visible in current context',
    'responses' => [
        '200' => [
            'description' => 'Privacy data flow list',
        ],
        '422' => [
            'description' => 'Organization context required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.data-flows-privacy.records.view');

Route::get('/privacy/data-flows/{flowId}', function (
    Request $request,
    string $flowId,
    DataFlowsPrivacyRepository $privacy,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $flow = $privacy->findDataFlow($flowId);
    abort_if($flow === null, 404);

    $organizationId = (string) $request->input('organization_id');
    abort_unless($organizationId !== '' && $flow['organization_id'] === $organizationId, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $flow['organization_id'],
        scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
        domainObjectType: 'privacy-data-flow',
        domainObjectId: $flow['id'],
    ), 403);

    return $apiSuccess($flow);
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyGetDataFlow',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'Get one privacy data flow',
    'responses' => [
        '200' => [
            'description' => 'Privacy data flow detail',
        ],
        '404' => [
            'description' => 'Privacy data flow not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.data-flows-privacy.records.view');

Route::post('/privacy/data-flows', function (
    Request $request,
    DataFlowsPrivacyRepository $privacy,
    FunctionalActorServiceInterface $actors,
    ReferenceCatalogService $catalogs,
) use ($apiSuccess, $apiPrincipalId) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'source' => ['required', 'string', 'max:160'],
        'destination' => ['required', 'string', 'max:160'],
        'data_category_summary' => ['required', 'string', 'max:200'],
        'transfer_type' => ['required', 'string', Rule::in($catalogs->keys('privacy.transfer_type', $organizationId))],
        'review_due_on' => ['nullable', 'date'],
        'linked_asset_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $flow = $privacy->createDataFlow([
        ...$validated,
        'organization_id' => $organizationId,
    ]);

    $principalId = $apiPrincipalId($request);
    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'privacy-data-flow',
            domainObjectId: $flow['id'],
            assignmentType: 'owner',
            organizationId: $flow['organization_id'],
            scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
        );
    }

    return $apiSuccess($flow);
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyCreateDataFlow',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'Create one privacy data flow',
    'responses' => [
        '200' => [
            'description' => 'Privacy data flow created',
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
        'source' => ['required', 'string', 'max:160'],
        'destination' => ['required', 'string', 'max:160'],
        'data_category_summary' => ['required', 'string', 'max:200'],
        'transfer_type' => ['required', 'string'],
        'review_due_on' => ['nullable', 'date'],
        'linked_asset_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'governed_fields' => [
        'transfer_type' => 'privacy.transfer_type',
    ],
    'lookup_fields' => [
        'linked_asset_id' => '/api/v1/assets',
        'linked_risk_id' => '/api/v1/lookups/risks/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

Route::patch('/privacy/data-flows/{flowId}', function (
    Request $request,
    string $flowId,
    DataFlowsPrivacyRepository $privacy,
    FunctionalActorServiceInterface $actors,
    ReferenceCatalogService $catalogs,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $existing = $privacy->findDataFlow($flowId);
    abort_if($existing === null, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $existing['organization_id'],
        scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
        domainObjectType: 'privacy-data-flow',
        domainObjectId: $existing['id'],
    ), 403);

    $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
    abort_unless($organizationId === $existing['organization_id'], 404);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'source' => ['required', 'string', 'max:160'],
        'destination' => ['required', 'string', 'max:160'],
        'data_category_summary' => ['required', 'string', 'max:200'],
        'transfer_type' => ['required', 'string', Rule::in($catalogs->keys('privacy.transfer_type', $organizationId))],
        'review_due_on' => ['nullable', 'date'],
        'linked_asset_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $flow = $privacy->updateDataFlow($flowId, [
        ...$validated,
        'organization_id' => $organizationId,
    ]);
    abort_if($flow === null, 404);

    $principalId = $apiPrincipalId($request);
    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'privacy-data-flow',
            domainObjectId: $flow['id'],
            assignmentType: 'owner',
            organizationId: $flow['organization_id'],
            scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
        );
    }

    DB::table('functional_assignments')
        ->where('domain_object_type', 'privacy-data-flow')
        ->where('domain_object_id', $flow['id'])
        ->where('organization_id', $flow['organization_id'])
        ->where('is_active', true)
        ->update([
            'scope_id' => $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
            'updated_at' => now(),
        ]);

    return $apiSuccess($flow);
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyUpdateDataFlow',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'Update one privacy data flow',
    'responses' => [
        '200' => [
            'description' => 'Privacy data flow updated',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Privacy data flow not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'title' => ['required', 'string', 'max:160'],
        'source' => ['required', 'string', 'max:160'],
        'destination' => ['required', 'string', 'max:160'],
        'data_category_summary' => ['required', 'string', 'max:200'],
        'transfer_type' => ['required', 'string'],
        'review_due_on' => ['nullable', 'date'],
        'linked_asset_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'governed_fields' => [
        'transfer_type' => 'privacy.transfer_type',
    ],
    'lookup_fields' => [
        'linked_asset_id' => '/api/v1/assets',
        'linked_risk_id' => '/api/v1/lookups/risks/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

Route::patch('/privacy/data-flows/{flowId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $flowId,
    string $assignmentId,
    DataFlowsPrivacyRepository $privacy,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $flow = $privacy->findDataFlow($flowId);
    abort_if($flow === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $flow['organization_id'],
        scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
        domainObjectType: 'privacy-data-flow',
        domainObjectId: $flow['id'],
    ), 403);

    $assignment = DB::table('functional_assignments')
        ->where('id', $assignmentId)
        ->where('domain_object_type', 'privacy-data-flow')
        ->where('domain_object_id', $flow['id'])
        ->where('organization_id', $flow['organization_id'])
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
        'data_flow_id' => $flow['id'],
    ]);
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyRemoveDataFlowOwner',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'Remove one owner assignment from a privacy data flow',
    'responses' => [
        '200' => [
            'description' => 'Privacy data flow owner assignment removed',
        ],
        '404' => [
            'description' => 'Privacy data flow or assignment not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

Route::post('/privacy/data-flows/{flowId}/artifacts', function (
    Request $request,
    string $flowId,
    DataFlowsPrivacyRepository $privacy,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $flow = $privacy->findDataFlow($flowId);
    abort_if($flow === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $flow['organization_id'],
        scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
        domainObjectType: 'privacy-data-flow',
        domainObjectId: $flow['id'],
    ), 403);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
        'membership_id' => ['nullable', 'string', 'max:120'],
    ]);

    $record = $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'data-flows-privacy',
        subjectType: 'privacy-data-flow',
        subjectId: $flowId,
        artifactType: (string) ($validated['artifact_type'] ?? 'record'),
        label: (string) ($validated['label'] ?? 'Privacy record'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($validated['membership_id'] ?? null) && $validated['membership_id'] !== ''
            ? $validated['membership_id']
            : null,
        organizationId: $flow['organization_id'],
        scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
        metadata: [
            'plugin' => 'data-flows-privacy',
            'transfer_type' => $flow['transfer_type'],
            'linked_asset_id' => $flow['linked_asset_id'],
            'linked_risk_id' => $flow['linked_risk_id'],
        ],
    ));

    return $apiSuccess($record->toArray());
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyAttachDataFlowArtifact',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'Upload one artifact to a privacy data flow',
    'responses' => [
        '200' => [
            'description' => 'Privacy data flow artifact uploaded',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Privacy data flow not found in current context',
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
])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

Route::post('/privacy/data-flows/{flowId}/transitions/{transitionKey}', function (
    Request $request,
    string $flowId,
    string $transitionKey,
    DataFlowsPrivacyRepository $privacy,
    WorkflowServiceInterface $workflows,
    ObjectAccessService $objectAccess,
    TenancyServiceInterface $tenancy,
) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
    $flow = $privacy->findDataFlow($flowId);
    abort_if($flow === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $flow['organization_id'],
        scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
        domainObjectType: 'privacy-data-flow',
        domainObjectId: $flow['id'],
    ), 403);

    $context = $resolveTenancy($request, $tenancy, $principalId);
    $organizationId = $context->organization?->id;
    abort_unless(is_string($organizationId) && $organizationId === $flow['organization_id'], 404);

    $workflows->transition(
        workflowKey: 'plugin.data-flows-privacy.data-flow-lifecycle',
        subjectType: 'privacy-data-flow',
        subjectId: $flowId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'api'),
            memberships: $context->memberships,
            organizationId: $organizationId,
            scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
            membershipId: $context->membershipIds()[0] ?? null,
        ),
    );

    return $apiSuccess([
        'data_flow' => $privacy->findDataFlow($flowId),
        'transition' => $transitionKey,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyTransitionDataFlow',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'Apply one workflow transition to a privacy data flow',
    'responses' => [
        '200' => [
            'description' => 'Privacy data flow transitioned',
        ],
        '404' => [
            'description' => 'Privacy data flow not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

Route::get('/privacy/activities', function (
    Request $request,
    DataFlowsPrivacyRepository $privacy,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);
    $scopeId = $request->input('scope_id');
    $principalId = $apiPrincipalId($request);

    $rows = $objectAccess->filterRecords(
        records: $privacy->allProcessingActivities($organizationId, is_string($scopeId) ? $scopeId : null),
        idKey: 'id',
        principalId: $principalId,
        organizationId: $organizationId,
        scopeId: is_string($scopeId) ? $scopeId : null,
        domainObjectType: 'privacy-processing-activity',
    );

    return $apiSuccess($rows);
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyListProcessingActivities',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'List processing activities visible in current context',
    'responses' => [
        '200' => [
            'description' => 'Processing activity list',
        ],
        '422' => [
            'description' => 'Organization context required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.data-flows-privacy.records.view');

Route::get('/privacy/activities/{activityId}', function (
    Request $request,
    string $activityId,
    DataFlowsPrivacyRepository $privacy,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $activity = $privacy->findProcessingActivity($activityId);
    abort_if($activity === null, 404);

    $organizationId = (string) $request->input('organization_id');
    abort_unless($organizationId !== '' && $activity['organization_id'] === $organizationId, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $activity['organization_id'],
        scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
        domainObjectType: 'privacy-processing-activity',
        domainObjectId: $activity['id'],
    ), 403);

    return $apiSuccess($activity);
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyGetProcessingActivity',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'Get one processing activity',
    'responses' => [
        '200' => [
            'description' => 'Processing activity detail',
        ],
        '404' => [
            'description' => 'Processing activity not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.data-flows-privacy.records.view');

Route::post('/privacy/activities', function (
    Request $request,
    DataFlowsPrivacyRepository $privacy,
    FunctionalActorServiceInterface $actors,
    ReferenceCatalogService $catalogs,
) use ($apiSuccess, $apiPrincipalId) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'purpose' => ['required', 'string', 'max:200'],
        'lawful_basis' => ['required', 'string', Rule::in($catalogs->keys('privacy.lawful_basis', $organizationId))],
        'linked_data_flow_ids' => ['nullable', 'string', 'max:255'],
        'linked_risk_ids' => ['nullable', 'string', 'max:255'],
        'linked_policy_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'review_due_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $activity = $privacy->createProcessingActivity([
        ...$validated,
        'organization_id' => $organizationId,
    ]);

    $principalId = $apiPrincipalId($request);
    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'privacy-processing-activity',
            domainObjectId: $activity['id'],
            assignmentType: 'owner',
            organizationId: $activity['organization_id'],
            scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
        );
    }

    return $apiSuccess($activity);
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyCreateProcessingActivity',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'Create one processing activity',
    'responses' => [
        '200' => [
            'description' => 'Processing activity created',
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
        'purpose' => ['required', 'string', 'max:200'],
        'lawful_basis' => ['required', 'string'],
        'linked_data_flow_ids' => ['nullable', 'string', 'max:255'],
        'linked_risk_ids' => ['nullable', 'string', 'max:255'],
        'linked_policy_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'review_due_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'governed_fields' => [
        'lawful_basis' => 'privacy.lawful_basis',
    ],
    'lookup_fields' => [
        'linked_data_flow_ids' => '/api/v1/privacy/data-flows',
        'linked_risk_ids' => '/api/v1/lookups/risks/options',
        'linked_policy_id' => '/api/v1/policies',
        'linked_finding_id' => '/api/v1/lookups/findings/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

Route::patch('/privacy/activities/{activityId}', function (
    Request $request,
    string $activityId,
    DataFlowsPrivacyRepository $privacy,
    FunctionalActorServiceInterface $actors,
    ReferenceCatalogService $catalogs,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $existing = $privacy->findProcessingActivity($activityId);
    abort_if($existing === null, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $existing['organization_id'],
        scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
        domainObjectType: 'privacy-processing-activity',
        domainObjectId: $existing['id'],
    ), 403);

    $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
    abort_unless($organizationId === $existing['organization_id'], 404);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'purpose' => ['required', 'string', 'max:200'],
        'lawful_basis' => ['required', 'string', Rule::in($catalogs->keys('privacy.lawful_basis', $organizationId))],
        'linked_data_flow_ids' => ['nullable', 'string', 'max:255'],
        'linked_risk_ids' => ['nullable', 'string', 'max:255'],
        'linked_policy_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'review_due_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $activity = $privacy->updateProcessingActivity($activityId, [
        ...$validated,
        'organization_id' => $organizationId,
    ]);
    abort_if($activity === null, 404);

    $principalId = $apiPrincipalId($request);
    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'privacy-processing-activity',
            domainObjectId: $activity['id'],
            assignmentType: 'owner',
            organizationId: $activity['organization_id'],
            scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
        );
    }

    DB::table('functional_assignments')
        ->where('domain_object_type', 'privacy-processing-activity')
        ->where('domain_object_id', $activity['id'])
        ->where('organization_id', $activity['organization_id'])
        ->where('is_active', true)
        ->update([
            'scope_id' => $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
            'updated_at' => now(),
        ]);

    return $apiSuccess($activity);
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyUpdateProcessingActivity',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'Update one processing activity',
    'responses' => [
        '200' => [
            'description' => 'Processing activity updated',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Processing activity not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'title' => ['required', 'string', 'max:160'],
        'purpose' => ['required', 'string', 'max:200'],
        'lawful_basis' => ['required', 'string'],
        'linked_data_flow_ids' => ['nullable', 'string', 'max:255'],
        'linked_risk_ids' => ['nullable', 'string', 'max:255'],
        'linked_policy_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'review_due_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'governed_fields' => [
        'lawful_basis' => 'privacy.lawful_basis',
    ],
    'lookup_fields' => [
        'linked_data_flow_ids' => '/api/v1/privacy/data-flows',
        'linked_risk_ids' => '/api/v1/lookups/risks/options',
        'linked_policy_id' => '/api/v1/policies',
        'linked_finding_id' => '/api/v1/lookups/findings/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

Route::patch('/privacy/activities/{activityId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $activityId,
    string $assignmentId,
    DataFlowsPrivacyRepository $privacy,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $activity = $privacy->findProcessingActivity($activityId);
    abort_if($activity === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $activity['organization_id'],
        scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
        domainObjectType: 'privacy-processing-activity',
        domainObjectId: $activity['id'],
    ), 403);

    $assignment = DB::table('functional_assignments')
        ->where('id', $assignmentId)
        ->where('domain_object_type', 'privacy-processing-activity')
        ->where('domain_object_id', $activity['id'])
        ->where('organization_id', $activity['organization_id'])
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
        'activity_id' => $activity['id'],
    ]);
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyRemoveProcessingActivityOwner',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'Remove one owner assignment from a processing activity',
    'responses' => [
        '200' => [
            'description' => 'Processing activity owner assignment removed',
        ],
        '404' => [
            'description' => 'Processing activity or assignment not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

Route::post('/privacy/activities/{activityId}/artifacts', function (
    Request $request,
    string $activityId,
    DataFlowsPrivacyRepository $privacy,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $activity = $privacy->findProcessingActivity($activityId);
    abort_if($activity === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $activity['organization_id'],
        scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
        domainObjectType: 'privacy-processing-activity',
        domainObjectId: $activity['id'],
    ), 403);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
        'membership_id' => ['nullable', 'string', 'max:120'],
    ]);

    $record = $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'data-flows-privacy',
        subjectType: 'privacy-processing-activity',
        subjectId: $activityId,
        artifactType: (string) ($validated['artifact_type'] ?? 'record'),
        label: (string) ($validated['label'] ?? 'Processing record'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($validated['membership_id'] ?? null) && $validated['membership_id'] !== ''
            ? $validated['membership_id']
            : null,
        organizationId: $activity['organization_id'],
        scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
        metadata: [
            'plugin' => 'data-flows-privacy',
            'lawful_basis' => $activity['lawful_basis'],
            'linked_policy_id' => $activity['linked_policy_id'],
            'linked_finding_id' => $activity['linked_finding_id'],
        ],
    ));

    return $apiSuccess($record->toArray());
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyAttachProcessingActivityArtifact',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'Upload one artifact to a processing activity',
    'responses' => [
        '200' => [
            'description' => 'Processing activity artifact uploaded',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Processing activity not found in current context',
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
])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

Route::post('/privacy/activities/{activityId}/transitions/{transitionKey}', function (
    Request $request,
    string $activityId,
    string $transitionKey,
    DataFlowsPrivacyRepository $privacy,
    WorkflowServiceInterface $workflows,
    ObjectAccessService $objectAccess,
    TenancyServiceInterface $tenancy,
) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
    $activity = $privacy->findProcessingActivity($activityId);
    abort_if($activity === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $activity['organization_id'],
        scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
        domainObjectType: 'privacy-processing-activity',
        domainObjectId: $activity['id'],
    ), 403);

    $context = $resolveTenancy($request, $tenancy, $principalId);
    $organizationId = $context->organization?->id;
    abort_unless(is_string($organizationId) && $organizationId === $activity['organization_id'], 404);

    $workflows->transition(
        workflowKey: 'plugin.data-flows-privacy.processing-activity-lifecycle',
        subjectType: 'privacy-processing-activity',
        subjectId: $activityId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'api'),
            memberships: $context->memberships,
            organizationId: $organizationId,
            scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
            membershipId: $context->membershipIds()[0] ?? null,
        ),
    );

    return $apiSuccess([
        'activity' => $privacy->findProcessingActivity($activityId),
        'transition' => $transitionKey,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'dataFlowsPrivacyTransitionProcessingActivity',
    'tags' => ['privacy'],
    'tag_descriptions' => [
        'privacy' => 'Data flow and processing activity API surface.',
    ],
    'summary' => 'Apply one workflow transition to a processing activity',
    'responses' => [
        '200' => [
            'description' => 'Processing activity transitioned',
        ],
        '404' => [
            'description' => 'Processing activity not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.data-flows-privacy.records.manage');
