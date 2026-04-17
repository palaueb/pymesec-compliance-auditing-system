<?php

use App\Http\Requests\Api\V1\AssessmentCreateRequest;
use App\Http\Requests\Api\V1\AssessmentReviewUpdateRequest;
use App\Http\Requests\Api\V1\AssessmentUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\ReferenceData\ReferenceCatalogService;
use PymeSec\Plugins\AssessmentsAudits\AssessmentsAuditsRepository;
use PymeSec\Plugins\FindingsRemediation\FindingsRemediationRepository;

$apiContext = require dirname(__DIR__, 3).'/core/routes/api_context.php';
extract($apiContext, EXTR_SKIP);

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

Route::patch('/assessments/{assessmentId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $assessmentId,
    string $assignmentId,
    AssessmentsAuditsRepository $assessments,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $assessment = $assessments->find($assessmentId);
    abort_if($assessment === null, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $assessment['organization_id'],
        scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
        domainObjectType: 'assessment',
        domainObjectId: $assessment['id'],
    ), 403);

    $assignment = DB::table('functional_assignments')
        ->where('id', $assignmentId)
        ->where('domain_object_type', 'assessment')
        ->where('domain_object_id', $assessment['id'])
        ->where('organization_id', $assessment['organization_id'])
        ->where('assignment_type', 'owner')
        ->where('is_active', true)
        ->first(['id']);
    abort_if($assignment === null, 404);

    $actors->deactivateAssignment(
        assignmentId: $assignmentId,
        deactivatedByPrincipalId: $apiPrincipalId($request),
    );

    return $apiSuccess([
        'removed' => true,
        'assignment_id' => $assignmentId,
        'assessment_id' => $assessment['id'],
    ]);
})->defaults('_openapi', [
    'operation_id' => 'assessmentsAuditsRemoveAssessmentOwner',
    'tags' => ['assessments'],
    'tag_descriptions' => [
        'assessments' => 'Assessment campaigns and review API surface.',
    ],
    'summary' => 'Remove one assessment owner assignment',
    'responses' => [
        '200' => [
            'description' => 'Assessment owner assignment removed',
        ],
        '404' => [
            'description' => 'Assessment or assignment not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.assessments-audits.assessments.manage');

Route::post('/assessments/{assessmentId}/reviews/{controlId}/artifacts', function (
    Request $request,
    string $assessmentId,
    string $controlId,
    AssessmentsAuditsRepository $assessments,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $assessment = $assessments->find($assessmentId);
    abort_if($assessment === null, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $assessment['organization_id'],
        scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
        domainObjectType: 'assessment',
        domainObjectId: $assessment['id'],
    ), 403);

    $review = $assessments->review($assessmentId, $controlId);
    abort_if($review === null, 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
    ]);

    $principalId = $apiPrincipalId($request);
    $membershipId = $request->input('membership_id');

    $record = $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'assessments-audits',
        subjectType: 'assessment-review',
        subjectId: (string) $review['id'],
        artifactType: (string) ($validated['artifact_type'] ?? 'workpaper'),
        label: (string) ($validated['label'] ?? 'Assessment workpaper'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $assessment['organization_id'],
        scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
        metadata: [
            'plugin' => 'assessments-audits',
            'assessment_id' => $assessmentId,
            'control_id' => $controlId,
            'result' => $review['result'] ?? null,
        ],
    ));

    return $apiSuccess($record->toArray());
})->defaults('_openapi', [
    'operation_id' => 'assessmentsAuditsAttachAssessmentReviewArtifact',
    'tags' => ['assessments'],
    'tag_descriptions' => [
        'assessments' => 'Assessment campaigns and review API surface.',
    ],
    'summary' => 'Attach one artifact to an assessment review',
    'responses' => [
        '200' => [
            'description' => 'Assessment review artifact attached',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Assessment or review not found in current context',
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
                        'principal_id' => ['type' => 'string'],
                        'organization_id' => ['type' => 'string'],
                        'scope_id' => ['type' => 'string'],
                        'membership_id' => ['type' => 'string'],
                        'artifact' => ['type' => 'string', 'format' => 'binary'],
                        'label' => ['type' => 'string'],
                        'artifact_type' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
])->middleware('core.permission:plugin.assessments-audits.assessments.manage');

Route::post('/assessments/{assessmentId}/reviews/{controlId}/findings', function (
    Request $request,
    string $assessmentId,
    string $controlId,
    AssessmentsAuditsRepository $assessments,
    FindingsRemediationRepository $findings,
    ReferenceCatalogService $catalogs,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $assessment = $assessments->find($assessmentId);
    abort_if($assessment === null, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $assessment['organization_id'],
        scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
        domainObjectType: 'assessment',
        domainObjectId: $assessment['id'],
    ), 403);

    $review = $assessments->review($assessmentId, $controlId);
    abort_if($review === null, 404);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'severity' => ['required', 'string', Rule::in($catalogs->keys('findings.severity', $assessment['organization_id']))],
        'description' => ['required', 'string', 'max:5000'],
        'due_on' => ['nullable', 'date'],
    ]);

    $controlScopeId = DB::table('controls')
        ->where('id', $controlId)
        ->where('organization_id', $assessment['organization_id'])
        ->value('scope_id');

    $findingScopeId = is_string($controlScopeId) && $controlScopeId !== ''
        ? $controlScopeId
        : ($assessment['scope_id'] !== '' ? $assessment['scope_id'] : null);

    $finding = $findings->createFinding([
        'organization_id' => $assessment['organization_id'],
        'scope_id' => $findingScopeId,
        'title' => (string) $validated['title'],
        'severity' => (string) $validated['severity'],
        'description' => (string) $validated['description'],
        'linked_control_id' => $controlId,
        'linked_risk_id' => null,
        'due_on' => is_string($validated['due_on'] ?? null) ? $validated['due_on'] : null,
    ]);

    $assessments->linkFinding($assessmentId, $controlId, $finding['id']);

    return $apiSuccess($finding);
})->defaults('_openapi', [
    'operation_id' => 'assessmentsAuditsCreateReviewFinding',
    'tags' => ['assessments'],
    'tag_descriptions' => [
        'assessments' => 'Assessment campaigns and review API surface.',
    ],
    'summary' => 'Create and link one finding from an assessment review',
    'responses' => [
        '200' => [
            'description' => 'Finding created and linked to assessment review',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Assessment or review not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'title' => ['required', 'string', 'max:160'],
        'severity' => ['required', 'string'],
        'description' => ['required', 'string', 'max:5000'],
        'due_on' => ['nullable', 'date'],
    ],
    'governed_fields' => [
        'severity' => 'findings.severity',
    ],
])->middleware('core.permission:plugin.assessments-audits.assessments.manage');

Route::post('/assessments/{assessmentId}/transitions/{transitionKey}', function (
    Request $request,
    string $assessmentId,
    string $transitionKey,
    AssessmentsAuditsRepository $assessments,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $assessment = $assessments->find($assessmentId);
    abort_if($assessment === null, 404);

    abort_unless($objectAccess->canAccessObject(
        principalId: $apiPrincipalId($request),
        organizationId: $assessment['organization_id'],
        scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
        domainObjectType: 'assessment',
        domainObjectId: $assessment['id'],
    ), 403);

    $validated = $request->validate([
        'signoff_notes' => ['nullable', 'string', 'max:5000'],
        'signed_off_on' => ['nullable', 'date'],
        'closure_summary' => ['nullable', 'string', 'max:5000'],
        'closed_on' => ['nullable', 'date'],
    ]);

    $principalId = $apiPrincipalId($request) ?? 'principal-org-a';

    $updated = match ($transitionKey) {
        'activate' => $assessments->update($assessmentId, [...$assessment, 'status' => 'active']),
        'sign-off' => $assessments->signOff(
            $assessmentId,
            $principalId,
            is_string($validated['signoff_notes'] ?? null) ? $validated['signoff_notes'] : null,
            is_string($validated['signed_off_on'] ?? null) ? $validated['signed_off_on'] : null,
        ),
        'close' => $assessments->close(
            $assessmentId,
            $principalId,
            is_string($validated['closure_summary'] ?? null) ? $validated['closure_summary'] : null,
            is_string($validated['closed_on'] ?? null) ? $validated['closed_on'] : null,
        ),
        'reopen' => $assessments->reopen($assessmentId),
        default => null,
    };
    abort_if($updated === null, 404);

    return $apiSuccess([
        'assessment_id' => $updated['id'],
        'status' => $updated['status'] ?? null,
        'transition' => $transitionKey,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'assessmentsAuditsTransitionAssessment',
    'tags' => ['assessments'],
    'tag_descriptions' => [
        'assessments' => 'Assessment campaigns and review API surface.',
    ],
    'summary' => 'Transition one assessment campaign lifecycle state',
    'responses' => [
        '200' => [
            'description' => 'Assessment transitioned',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '404' => [
            'description' => 'Assessment or transition not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'signoff_notes' => ['nullable', 'string', 'max:5000'],
        'signed_off_on' => ['nullable', 'date'],
        'closure_summary' => ['nullable', 'string', 'max:5000'],
        'closed_on' => ['nullable', 'date'],
    ],
])->middleware('core.permission:plugin.assessments-audits.assessments.manage');
