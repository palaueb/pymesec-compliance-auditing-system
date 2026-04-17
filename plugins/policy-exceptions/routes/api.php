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
use PymeSec\Plugins\PolicyExceptions\PolicyExceptionsRepository;

$apiContext = require dirname(__DIR__, 3).'/core/routes/api_context.php';
extract($apiContext, EXTR_SKIP);

Route::get('/policies', function (
    Request $request,
    PolicyExceptionsRepository $policies,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);
    $scopeId = $request->input('scope_id');
    $principalId = $apiPrincipalId($request);

    $rows = $objectAccess->filterRecords(
        records: $policies->allPolicies($organizationId, is_string($scopeId) ? $scopeId : null),
        idKey: 'id',
        principalId: $principalId,
        organizationId: $organizationId,
        scopeId: is_string($scopeId) ? $scopeId : null,
        domainObjectType: 'policy',
    );

    return $apiSuccess($rows);
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsListPolicies',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'List policies visible in current context',
    'responses' => [
        '200' => [
            'description' => 'Policy list',
        ],
        '422' => [
            'description' => 'Organization context required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.policy-exceptions.policies.view');

Route::get('/policies/{policyId}', function (
    Request $request,
    string $policyId,
    PolicyExceptionsRepository $policies,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $policy = $policies->findPolicy($policyId);
    abort_if($policy === null, 404);

    $organizationId = (string) $request->input('organization_id');
    abort_unless($organizationId !== '' && $policy['organization_id'] === $organizationId, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $policy['organization_id'],
        scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        domainObjectType: 'policy',
        domainObjectId: $policy['id'],
    ), 403);

    return $apiSuccess($policy);
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsGetPolicy',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'Get one policy',
    'responses' => [
        '200' => [
            'description' => 'Policy detail',
        ],
        '404' => [
            'description' => 'Policy not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.policy-exceptions.policies.view');

Route::post('/policies', function (
    Request $request,
    PolicyExceptionsRepository $policies,
    FunctionalActorServiceInterface $actors,
    ReferenceCatalogService $catalogs,
) use ($apiSuccess, $apiPrincipalId) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'area' => ['required', 'string', Rule::in($catalogs->keys('policies.areas', $organizationId))],
        'version_label' => ['required', 'string', 'max:40'],
        'statement' => ['required', 'string', 'max:2000'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'review_due_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $policy = $policies->createPolicy([
        ...$validated,
        'organization_id' => $organizationId,
    ]);

    $principalId = $apiPrincipalId($request);
    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'policy',
            domainObjectId: $policy['id'],
            assignmentType: 'owner',
            organizationId: $policy['organization_id'],
            scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
        );
    }

    return $apiSuccess($policy);
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsCreatePolicy',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'Create one policy',
    'responses' => [
        '200' => [
            'description' => 'Policy created',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'title' => ['required', 'string', 'max:140'],
        'area' => ['required', 'string'],
        'version_label' => ['required', 'string', 'max:40'],
        'statement' => ['required', 'string', 'max:2000'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'review_due_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'governed_fields' => [
        'area' => 'policies.areas',
    ],
    'lookup_fields' => [
        'linked_control_id' => '/api/v1/lookups/controls/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

Route::patch('/policies/{policyId}', function (
    Request $request,
    string $policyId,
    PolicyExceptionsRepository $policies,
    FunctionalActorServiceInterface $actors,
    ReferenceCatalogService $catalogs,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $existing = $policies->findPolicy($policyId);
    abort_if($existing === null, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $existing['organization_id'],
        scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
        domainObjectType: 'policy',
        domainObjectId: $existing['id'],
    ), 403);

    $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
    abort_unless($organizationId === $existing['organization_id'], 404);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'area' => ['required', 'string', Rule::in($catalogs->keys('policies.areas', $organizationId))],
        'version_label' => ['required', 'string', 'max:40'],
        'statement' => ['required', 'string', 'max:2000'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'review_due_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $policy = $policies->updatePolicy($policyId, [
        ...$validated,
        'organization_id' => $organizationId,
    ]);
    abort_if($policy === null, 404);

    $principalId = $apiPrincipalId($request);
    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'policy',
            domainObjectId: $policy['id'],
            assignmentType: 'owner',
            organizationId: $policy['organization_id'],
            scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
        );
    }

    DB::table('functional_assignments')
        ->where('domain_object_type', 'policy')
        ->where('domain_object_id', $policy['id'])
        ->where('organization_id', $policy['organization_id'])
        ->where('is_active', true)
        ->update([
            'scope_id' => $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
            'updated_at' => now(),
        ]);

    return $apiSuccess($policy);
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsUpdatePolicy',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'Update one policy',
    'responses' => [
        '200' => [
            'description' => 'Policy updated',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Policy not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'title' => ['required', 'string', 'max:140'],
        'area' => ['required', 'string'],
        'version_label' => ['required', 'string', 'max:40'],
        'statement' => ['required', 'string', 'max:2000'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'review_due_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'governed_fields' => [
        'area' => 'policies.areas',
    ],
    'lookup_fields' => [
        'linked_control_id' => '/api/v1/lookups/controls/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

Route::patch('/policies/{policyId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $policyId,
    string $assignmentId,
    PolicyExceptionsRepository $policies,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $policy = $policies->findPolicy($policyId);
    abort_if($policy === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $policy['organization_id'],
        scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        domainObjectType: 'policy',
        domainObjectId: $policy['id'],
    ), 403);

    $assignment = DB::table('functional_assignments')
        ->where('id', $assignmentId)
        ->where('domain_object_type', 'policy')
        ->where('domain_object_id', $policy['id'])
        ->where('organization_id', $policy['organization_id'])
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
        'policy_id' => $policy['id'],
    ]);
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsRemovePolicyOwner',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'Remove one owner assignment from a policy',
    'responses' => [
        '200' => [
            'description' => 'Policy owner assignment removed',
        ],
        '404' => [
            'description' => 'Policy or assignment not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

Route::post('/policies/{policyId}/artifacts', function (
    Request $request,
    string $policyId,
    PolicyExceptionsRepository $policies,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $policy = $policies->findPolicy($policyId);
    abort_if($policy === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $policy['organization_id'],
        scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        domainObjectType: 'policy',
        domainObjectId: $policy['id'],
    ), 403);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
        'membership_id' => ['nullable', 'string', 'max:120'],
    ]);

    $record = $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'policy-exceptions',
        subjectType: 'policy',
        subjectId: $policyId,
        artifactType: (string) ($validated['artifact_type'] ?? 'document'),
        label: (string) ($validated['label'] ?? 'Policy document'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($validated['membership_id'] ?? null) && $validated['membership_id'] !== ''
            ? $validated['membership_id']
            : null,
        organizationId: $policy['organization_id'],
        scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        metadata: [
            'plugin' => 'policy-exceptions',
            'area' => $policy['area'],
            'version_label' => $policy['version_label'],
        ],
    ));

    return $apiSuccess($record->toArray());
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsAttachPolicyArtifact',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'Upload one artifact to a policy',
    'responses' => [
        '200' => [
            'description' => 'Policy artifact uploaded',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Policy not found in current context',
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
])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

Route::post('/policies/{policyId}/transitions/{transitionKey}', function (
    Request $request,
    string $policyId,
    string $transitionKey,
    PolicyExceptionsRepository $policies,
    WorkflowServiceInterface $workflows,
    ObjectAccessService $objectAccess,
    TenancyServiceInterface $tenancy,
) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
    $policy = $policies->findPolicy($policyId);
    abort_if($policy === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $policy['organization_id'],
        scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        domainObjectType: 'policy',
        domainObjectId: $policy['id'],
    ), 403);

    $context = $resolveTenancy($request, $tenancy, $principalId);
    $organizationId = $context->organization?->id;
    abort_unless(is_string($organizationId) && $organizationId === $policy['organization_id'], 404);

    $workflows->transition(
        workflowKey: 'plugin.policy-exceptions.policy-lifecycle',
        subjectType: 'policy',
        subjectId: $policyId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'api'),
            memberships: $context->memberships,
            organizationId: $organizationId,
            scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
            membershipId: $context->membershipIds()[0] ?? null,
        ),
    );

    return $apiSuccess([
        'policy' => $policies->findPolicy($policyId),
        'transition' => $transitionKey,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsTransitionPolicy',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'Apply one workflow transition to a policy',
    'responses' => [
        '200' => [
            'description' => 'Policy transitioned',
        ],
        '404' => [
            'description' => 'Policy not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

Route::get('/policies/exceptions', function (
    Request $request,
    PolicyExceptionsRepository $policies,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);
    $scopeId = $request->input('scope_id');
    $principalId = $apiPrincipalId($request);

    $rows = $objectAccess->filterRecords(
        records: $policies->exceptions($organizationId, is_string($scopeId) ? $scopeId : null),
        idKey: 'id',
        principalId: $principalId,
        organizationId: $organizationId,
        scopeId: is_string($scopeId) ? $scopeId : null,
        domainObjectType: 'policy-exception',
    );

    return $apiSuccess($rows);
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsListExceptions',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'List policy exceptions visible in current context',
    'responses' => [
        '200' => [
            'description' => 'Policy exception list',
        ],
        '422' => [
            'description' => 'Organization context required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.policy-exceptions.policies.view');

Route::get('/policies/exceptions/{exceptionId}', function (
    Request $request,
    string $exceptionId,
    PolicyExceptionsRepository $policies,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $exception = $policies->findException($exceptionId);
    abort_if($exception === null, 404);

    $organizationId = (string) $request->input('organization_id');
    abort_unless($organizationId !== '' && $exception['organization_id'] === $organizationId, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $exception['organization_id'],
        scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
        domainObjectType: 'policy-exception',
        domainObjectId: $exception['id'],
    ), 403);

    return $apiSuccess($exception);
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsGetException',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'Get one policy exception',
    'responses' => [
        '200' => [
            'description' => 'Policy exception detail',
        ],
        '404' => [
            'description' => 'Policy exception not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.policy-exceptions.policies.view');

Route::post('/policies/{policyId}/exceptions', function (
    Request $request,
    string $policyId,
    PolicyExceptionsRepository $policies,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $policy = $policies->findPolicy($policyId);
    abort_if($policy === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $policy['organization_id'],
        scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        domainObjectType: 'policy',
        domainObjectId: $policy['id'],
    ), 403);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'rationale' => ['required', 'string', 'max:2000'],
        'compensating_control' => ['nullable', 'string', 'max:1000'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'expires_on' => ['nullable', 'date'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $exception = $policies->createException($policyId, [
        ...$validated,
        'organization_id' => $policy['organization_id'],
        'scope_id' => $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
    ]);

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'policy-exception',
            domainObjectId: $exception['id'],
            assignmentType: 'owner',
            organizationId: $exception['organization_id'],
            scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: $principalId,
        );
    }

    return $apiSuccess($exception);
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsCreateException',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'Create one policy exception',
    'responses' => [
        '200' => [
            'description' => 'Policy exception created',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Policy not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'title' => ['required', 'string', 'max:140'],
        'rationale' => ['required', 'string', 'max:2000'],
        'compensating_control' => ['nullable', 'string', 'max:1000'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'expires_on' => ['nullable', 'date'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'lookup_fields' => [
        'linked_finding_id' => '/api/v1/lookups/findings/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

Route::patch('/policies/exceptions/{exceptionId}', function (
    Request $request,
    string $exceptionId,
    PolicyExceptionsRepository $policies,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $existing = $policies->findException($exceptionId);
    abort_if($existing === null, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $existing['organization_id'],
        scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
        domainObjectType: 'policy-exception',
        domainObjectId: $existing['id'],
    ), 403);

    $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
    abort_unless($organizationId === $existing['organization_id'], 404);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'rationale' => ['required', 'string', 'max:2000'],
        'compensating_control' => ['nullable', 'string', 'max:1000'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'expires_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $exception = $policies->updateException($exceptionId, [
        ...$validated,
        'organization_id' => $organizationId,
    ]);
    abort_if($exception === null, 404);

    $principalId = $apiPrincipalId($request);
    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'policy-exception',
            domainObjectId: $exception['id'],
            assignmentType: 'owner',
            organizationId: $exception['organization_id'],
            scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
        );
    }

    DB::table('functional_assignments')
        ->where('domain_object_type', 'policy-exception')
        ->where('domain_object_id', $exception['id'])
        ->where('organization_id', $exception['organization_id'])
        ->where('is_active', true)
        ->update([
            'scope_id' => $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
            'updated_at' => now(),
        ]);

    return $apiSuccess($exception);
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsUpdateException',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'Update one policy exception',
    'responses' => [
        '200' => [
            'description' => 'Policy exception updated',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Policy exception not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'title' => ['required', 'string', 'max:140'],
        'rationale' => ['required', 'string', 'max:2000'],
        'compensating_control' => ['nullable', 'string', 'max:1000'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'expires_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'lookup_fields' => [
        'linked_finding_id' => '/api/v1/lookups/findings/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

Route::patch('/policies/exceptions/{exceptionId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $exceptionId,
    string $assignmentId,
    PolicyExceptionsRepository $policies,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiSuccess, $apiPrincipalId) {
    $exception = $policies->findException($exceptionId);
    abort_if($exception === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $exception['organization_id'],
        scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
        domainObjectType: 'policy-exception',
        domainObjectId: $exception['id'],
    ), 403);

    $assignment = DB::table('functional_assignments')
        ->where('id', $assignmentId)
        ->where('domain_object_type', 'policy-exception')
        ->where('domain_object_id', $exception['id'])
        ->where('organization_id', $exception['organization_id'])
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
        'exception_id' => $exception['id'],
    ]);
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsRemoveExceptionOwner',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'Remove one owner assignment from a policy exception',
    'responses' => [
        '200' => [
            'description' => 'Policy exception owner assignment removed',
        ],
        '404' => [
            'description' => 'Policy exception or assignment not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

Route::post('/policies/exceptions/{exceptionId}/artifacts', function (
    Request $request,
    string $exceptionId,
    PolicyExceptionsRepository $policies,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $exception = $policies->findException($exceptionId);
    abort_if($exception === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $exception['organization_id'],
        scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
        domainObjectType: 'policy-exception',
        domainObjectId: $exception['id'],
    ), 403);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
        'membership_id' => ['nullable', 'string', 'max:120'],
    ]);

    $record = $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'policy-exceptions',
        subjectType: 'policy-exception',
        subjectId: $exceptionId,
        artifactType: (string) ($validated['artifact_type'] ?? 'evidence'),
        label: (string) ($validated['label'] ?? 'Exception evidence'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($validated['membership_id'] ?? null) && $validated['membership_id'] !== ''
            ? $validated['membership_id']
            : null,
        organizationId: $exception['organization_id'],
        scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
        metadata: [
            'plugin' => 'policy-exceptions',
            'policy_id' => $exception['policy_id'],
            'linked_finding_id' => $exception['linked_finding_id'],
        ],
    ));

    return $apiSuccess($record->toArray());
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsAttachExceptionArtifact',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'Upload one artifact to a policy exception',
    'responses' => [
        '200' => [
            'description' => 'Policy exception artifact uploaded',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Policy exception not found in current context',
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
])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

Route::post('/policies/exceptions/{exceptionId}/transitions/{transitionKey}', function (
    Request $request,
    string $exceptionId,
    string $transitionKey,
    PolicyExceptionsRepository $policies,
    WorkflowServiceInterface $workflows,
    ObjectAccessService $objectAccess,
    TenancyServiceInterface $tenancy,
) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
    $exception = $policies->findException($exceptionId);
    abort_if($exception === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $exception['organization_id'],
        scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
        domainObjectType: 'policy-exception',
        domainObjectId: $exception['id'],
    ), 403);

    $context = $resolveTenancy($request, $tenancy, $principalId);
    $organizationId = $context->organization?->id;
    abort_unless(is_string($organizationId) && $organizationId === $exception['organization_id'], 404);

    $workflows->transition(
        workflowKey: 'plugin.policy-exceptions.exception-lifecycle',
        subjectType: 'policy-exception',
        subjectId: $exceptionId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'api'),
            memberships: $context->memberships,
            organizationId: $organizationId,
            scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
            membershipId: $context->membershipIds()[0] ?? null,
        ),
    );

    return $apiSuccess([
        'exception' => $policies->findException($exceptionId),
        'transition' => $transitionKey,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'policyExceptionsTransitionException',
    'tags' => ['policies'],
    'tag_descriptions' => [
        'policies' => 'Policy and exception API surface.',
    ],
    'summary' => 'Apply one workflow transition to a policy exception',
    'responses' => [
        '200' => [
            'description' => 'Policy exception transitioned',
        ],
        '404' => [
            'description' => 'Policy exception not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.policy-exceptions.policies.manage');
