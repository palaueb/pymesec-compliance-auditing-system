<?php

use App\Http\Requests\Api\V1\FindingCreateRequest;
use App\Http\Requests\Api\V1\FindingUpdateRequest;
use App\Http\Requests\Api\V1\RemediationActionCreateRequest;
use App\Http\Requests\Api\V1\RemediationActionUpdateRequest;
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
use PymeSec\Plugins\FindingsRemediation\FindingsRemediationRepository;

$apiContext = require dirname(__DIR__, 3).'/core/routes/api_context.php';
extract($apiContext, EXTR_SKIP);

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

Route::patch('/findings/{findingId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $findingId,
    string $assignmentId,
    FindingsRemediationRepository $findings,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $finding = $findings->findFinding($findingId);
    abort_if($finding === null, 404);
    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $finding['organization_id'],
        scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        domainObjectType: 'finding',
        domainObjectId: $finding['id'],
    ), 403);

    $assignment = collect($actors->assignmentsFor(
        domainObjectType: 'finding',
        domainObjectId: $finding['id'],
        organizationId: $finding['organization_id'],
        scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
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
    'operation_id' => 'findingsRemediationRemoveFindingOwner',
    'tags' => ['findings'],
    'tag_descriptions' => [
        'findings' => 'Findings and remediation API surface.',
    ],
    'summary' => 'Remove one owner assignment from a finding',
    'responses' => [
        '200' => [
            'description' => 'Owner assignment removed',
        ],
        '404' => [
            'description' => 'Finding or assignment not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.findings-remediation.findings.manage');

Route::post('/findings/{findingId}/artifacts', function (
    Request $request,
    string $findingId,
    FindingsRemediationRepository $findings,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $finding = $findings->findFinding($findingId);
    abort_if($finding === null, 404);
    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $finding['organization_id'],
        scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        domainObjectType: 'finding',
        domainObjectId: $finding['id'],
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
        ownerComponent: 'findings-remediation',
        subjectType: 'finding',
        subjectId: $findingId,
        artifactType: (string) ($validated['artifact_type'] ?? 'evidence'),
        label: (string) ($validated['label'] ?? 'Finding evidence'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $finding['organization_id'],
        scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        metadata: [
            'plugin' => 'findings-remediation',
            'severity' => $finding['severity'],
            'linked_control_id' => $finding['linked_control_id'],
            'linked_risk_id' => $finding['linked_risk_id'],
        ],
    ));

    return $apiSuccess($record->toArray());
})->defaults('_openapi', [
    'operation_id' => 'findingsRemediationAttachFindingArtifact',
    'tags' => ['findings'],
    'tag_descriptions' => [
        'findings' => 'Findings and remediation API surface.',
    ],
    'summary' => 'Upload one artifact to a finding',
    'responses' => [
        '200' => [
            'description' => 'Artifact uploaded',
        ],
        '404' => [
            'description' => 'Finding not found in current context',
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
])->middleware('core.permission:plugin.findings-remediation.findings.manage');

Route::post('/findings/{findingId}/transitions/{transitionKey}', function (
    Request $request,
    string $findingId,
    string $transitionKey,
    WorkflowServiceInterface $workflows,
    FindingsRemediationRepository $findings,
    ObjectAccessService $objectAccess,
    TenancyServiceInterface $tenancy,
) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
    $finding = $findings->findFinding($findingId);
    abort_if($finding === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $finding['organization_id'],
        scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        domainObjectType: 'finding',
        domainObjectId: $finding['id'],
    ), 403);

    $context = $resolveTenancy($request, $tenancy, $principalId);
    $organizationId = $context->organization?->id;
    abort_unless(is_string($organizationId) && $organizationId === $finding['organization_id'], 404);

    $workflows->transition(
        workflowKey: 'plugin.findings-remediation.finding-lifecycle',
        subjectType: 'finding',
        subjectId: $findingId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'api'),
            memberships: $context->memberships,
            organizationId: $organizationId,
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            membershipId: $context->membershipIds()[0] ?? null,
        ),
    );

    return $apiSuccess([
        'finding' => $findings->findFinding($findingId),
        'transition' => $transitionKey,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'findingsRemediationTransitionFinding',
    'tags' => ['findings'],
    'tag_descriptions' => [
        'findings' => 'Findings and remediation API surface.',
    ],
    'summary' => 'Apply one workflow transition to a finding',
    'responses' => [
        '200' => [
            'description' => 'Transition applied',
        ],
        '404' => [
            'description' => 'Finding not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
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

Route::patch('/findings/actions/{actionId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $actionId,
    string $assignmentId,
    FindingsRemediationRepository $findings,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $action = $findings->findAction($actionId);
    abort_if($action === null, 404);
    $finding = $findings->findFinding((string) $action['finding_id']);
    abort_if($finding === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

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

    $assignment = collect($actors->assignmentsFor(
        domainObjectType: 'remediation-action',
        domainObjectId: $action['id'],
        organizationId: $action['organization_id'],
        scopeId: $action['scope_id'] !== '' ? $action['scope_id'] : null,
    ))->first(fn ($candidate) => $candidate->id === $assignmentId && $candidate->assignmentType === 'owner');
    abort_if($assignment === null, 404);

    $actors->deactivateAssignment(
        assignmentId: $assignmentId,
        deactivatedByPrincipalId: $principalId,
    );

    return $apiSuccess([
        'assignment_id' => $assignmentId,
        'removed' => true,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'findingsRemediationRemoveActionOwner',
    'tags' => ['findings'],
    'tag_descriptions' => [
        'findings' => 'Findings and remediation API surface.',
    ],
    'summary' => 'Remove one owner assignment from a remediation action',
    'responses' => [
        '200' => [
            'description' => 'Owner assignment removed',
        ],
        '404' => [
            'description' => 'Action or assignment not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.findings-remediation.findings.manage');
