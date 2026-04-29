<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Collaboration\Contracts\CollaborationEngineInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Questionnaires\Contracts\QuestionnaireEngineInterface;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Plugins\ThirdPartyRisk\ThirdPartyRiskRepository;

$apiContext = require base_path('routes/api_context.php');
extract($apiContext, EXTR_SKIP);

Route::get('/vendors', function (
    Request $request,
    ThirdPartyRiskRepository $vendors,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);
    $scopeId = $request->input('scope_id');
    $principalId = $apiPrincipalId($request);

    $rows = [];

    foreach ($vendors->all($organizationId, is_string($scopeId) ? $scopeId : null) as $vendor) {
        $reviews = $vendors->reviewsForVendor((string) ($vendor['id'] ?? ''));
        $visibleReviews = array_values(array_filter($reviews, static function (array $review) use ($objectAccess, $principalId, $organizationId): bool {
            return $objectAccess->canAccessObject(
                principalId: $principalId,
                organizationId: $organizationId,
                scopeId: ($review['scope_id'] ?? '') !== '' ? $review['scope_id'] : null,
                domainObjectType: 'vendor-review',
                domainObjectId: (string) ($review['id'] ?? ''),
            );
        }));

        if ($visibleReviews === []) {
            continue;
        }

        $rows[] = [
            ...$vendor,
            'current_review' => $visibleReviews[0],
            'review_count' => count($visibleReviews),
        ];
    }

    return $apiSuccess($rows);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskListVendors',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'List vendors with visible current review context',
    'responses' => [
        '200' => [
            'description' => 'Vendor list',
        ],
        '422' => [
            'description' => 'Organization context required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.view');

Route::get('/vendors/{vendorId}', function (
    Request $request,
    string $vendorId,
    ThirdPartyRiskRepository $vendors,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    abort_if($vendor === null, 404);

    $organizationId = (string) $request->input('organization_id');
    abort_unless($organizationId !== '' && $vendor['organization_id'] === $organizationId, 404);

    $scopeId = $request->input('scope_id');
    if (is_string($scopeId) && $scopeId !== '' && ($vendor['scope_id'] ?? '') !== '' && $vendor['scope_id'] !== $scopeId) {
        abort(404);
    }

    $principalId = $apiPrincipalId($request);
    $reviews = $vendors->reviewsForVendor($vendorId);
    $visibleReviews = array_values(array_filter($reviews, static function (array $review) use ($objectAccess, $principalId, $organizationId): bool {
        return $objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: ($review['scope_id'] ?? '') !== '' ? $review['scope_id'] : null,
            domainObjectType: 'vendor-review',
            domainObjectId: (string) ($review['id'] ?? ''),
        );
    }));

    abort_if($visibleReviews === [], 403);

    return $apiSuccess([
        ...$vendor,
        'current_review' => $visibleReviews[0],
        'reviews' => $visibleReviews,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskGetVendor',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Get one vendor with visible review details',
    'responses' => [
        '200' => [
            'description' => 'Vendor detail',
        ],
        '404' => [
            'description' => 'Vendor not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.view');

Route::post('/vendors', function (
    Request $request,
    ThirdPartyRiskRepository $vendors,
    FunctionalActorServiceInterface $actors,
) use ($apiPrincipalId, $apiSuccess) {
    $validated = $request->validate([
        'legal_name' => ['required', 'string', 'max:140'],
        'service_summary' => ['required', 'string', 'max:255'],
        'tier' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
        'website' => ['nullable', 'url', 'max:255'],
        'primary_contact_name' => ['nullable', 'string', 'max:120'],
        'primary_contact_email' => ['nullable', 'email', 'max:160'],
        'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
        'scope_id' => ['nullable', 'string', 'max:64', 'exists:scopes,id'],
        'review_profile_id' => ['nullable', 'string', 'max:120'],
        'questionnaire_template_id' => ['nullable', 'string', 'max:120'],
        'review_title' => ['required', 'string', 'max:140'],
        'inherent_risk' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
        'review_summary' => ['required', 'string', 'max:2000'],
        'decision_notes' => ['nullable', 'string', 'max:2000'],
        'linked_asset_id' => ['nullable', 'string', 'max:120', 'exists:assets,id'],
        'linked_control_id' => ['nullable', 'string', 'max:120', 'exists:controls,id'],
        'linked_risk_id' => ['nullable', 'string', 'max:120', 'exists:risks,id'],
        'linked_finding_id' => ['nullable', 'string', 'max:120', 'exists:findings,id'],
        'next_review_due_on' => ['nullable', 'date'],
        'owner_actor_id' => ['nullable', 'string', 'max:64', 'exists:functional_actors,id'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    [$vendor, $review] = $vendors->createVendorWithReview([
        ...$validated,
        'created_by_principal_id' => $principalId,
    ]);

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'vendor-review',
            domainObjectId: $review['id'],
            assignmentType: 'owner',
            organizationId: $vendor['organization_id'],
            scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: $principalId,
        );
    }

    return $apiSuccess([
        'vendor' => $vendor,
        'review' => $review,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskCreateVendorWithReview',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Create a vendor with its initial review workspace',
    'responses' => [
        '200' => [
            'description' => 'Vendor and review created',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'legal_name' => ['required', 'string', 'max:140'],
        'service_summary' => ['required', 'string', 'max:255'],
        'tier' => ['required', 'string', 'in:low,medium,high,critical'],
        'website' => ['nullable', 'url', 'max:255'],
        'primary_contact_name' => ['nullable', 'string', 'max:120'],
        'primary_contact_email' => ['nullable', 'email', 'max:160'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'review_profile_id' => ['nullable', 'string', 'max:120'],
        'questionnaire_template_id' => ['nullable', 'string', 'max:120'],
        'review_title' => ['required', 'string', 'max:140'],
        'inherent_risk' => ['required', 'string', 'in:low,medium,high,critical'],
        'review_summary' => ['required', 'string', 'max:2000'],
        'decision_notes' => ['nullable', 'string', 'max:2000'],
        'linked_asset_id' => ['nullable', 'string', 'max:120'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'next_review_due_on' => ['nullable', 'date'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'lookup_fields' => [
        'review_profile_id' => '/api/v1/lookups/vendor-review-profiles/options',
        'questionnaire_template_id' => '/api/v1/lookups/vendor-questionnaire-templates/options',
        'linked_asset_id' => '/api/v1/assets',
        'linked_control_id' => '/api/v1/lookups/controls/options',
        'linked_risk_id' => '/api/v1/lookups/risks/options',
        'linked_finding_id' => '/api/v1/lookups/findings/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::patch('/vendors/{vendorId}/reviews/{reviewId}', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $vendors,
    FunctionalActorServiceInterface $actors,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'legal_name' => ['required', 'string', 'max:140'],
        'service_summary' => ['required', 'string', 'max:255'],
        'tier' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
        'website' => ['nullable', 'url', 'max:255'],
        'primary_contact_name' => ['nullable', 'string', 'max:120'],
        'primary_contact_email' => ['nullable', 'email', 'max:160'],
        'scope_id' => ['nullable', 'string', 'max:64', 'exists:scopes,id'],
        'review_profile_id' => ['nullable', 'string', 'max:120'],
        'questionnaire_template_id' => ['nullable', 'string', 'max:120'],
        'review_title' => ['required', 'string', 'max:140'],
        'inherent_risk' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
        'review_summary' => ['required', 'string', 'max:2000'],
        'decision_notes' => ['nullable', 'string', 'max:2000'],
        'linked_asset_id' => ['nullable', 'string', 'max:120', 'exists:assets,id'],
        'linked_control_id' => ['nullable', 'string', 'max:120', 'exists:controls,id'],
        'linked_risk_id' => ['nullable', 'string', 'max:120', 'exists:risks,id'],
        'linked_finding_id' => ['nullable', 'string', 'max:120', 'exists:findings,id'],
        'next_review_due_on' => ['nullable', 'date'],
        'owner_actor_id' => ['nullable', 'string', 'max:64', 'exists:functional_actors,id'],
    ]);

    $organizationId = (string) $request->input('organization_id', $vendor['organization_id']);
    abort_unless($organizationId === $vendor['organization_id'], 404);

    [$updatedVendor, $updatedReview] = $vendors->updateVendorWithReview($vendorId, [
        ...$validated,
        'organization_id' => $organizationId,
        'review_id' => $reviewId,
    ]);
    abort_if($updatedVendor === null || $updatedReview === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'vendor-review',
            domainObjectId: $updatedReview['id'],
            assignmentType: 'owner',
            organizationId: $updatedVendor['organization_id'],
            scopeId: $updatedVendor['scope_id'] !== '' ? $updatedVendor['scope_id'] : null,
            metadata: ['source' => 'api'],
            assignedByPrincipalId: $principalId,
        );
    }

    DB::table('functional_assignments')
        ->where('domain_object_type', 'vendor-review')
        ->where('domain_object_id', $updatedReview['id'])
        ->where('organization_id', $updatedVendor['organization_id'])
        ->where('is_active', true)
        ->update([
            'scope_id' => $updatedVendor['scope_id'] !== '' ? $updatedVendor['scope_id'] : null,
            'updated_at' => now(),
        ]);

    return $apiSuccess([
        'vendor' => $updatedVendor,
        'review' => $updatedReview,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskUpdateVendorWithReview',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Update a vendor and one review workspace',
    'responses' => [
        '200' => [
            'description' => 'Vendor and review updated',
        ],
        '404' => [
            'description' => 'Vendor or review not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'legal_name' => ['required', 'string', 'max:140'],
        'service_summary' => ['required', 'string', 'max:255'],
        'tier' => ['required', 'string', 'in:low,medium,high,critical'],
        'website' => ['nullable', 'url', 'max:255'],
        'primary_contact_name' => ['nullable', 'string', 'max:120'],
        'primary_contact_email' => ['nullable', 'email', 'max:160'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'review_profile_id' => ['nullable', 'string', 'max:120'],
        'questionnaire_template_id' => ['nullable', 'string', 'max:120'],
        'review_title' => ['required', 'string', 'max:140'],
        'inherent_risk' => ['required', 'string', 'in:low,medium,high,critical'],
        'review_summary' => ['required', 'string', 'max:2000'],
        'decision_notes' => ['nullable', 'string', 'max:2000'],
        'linked_asset_id' => ['nullable', 'string', 'max:120'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'next_review_due_on' => ['nullable', 'date'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ],
    'lookup_fields' => [
        'review_profile_id' => '/api/v1/lookups/vendor-review-profiles/options',
        'questionnaire_template_id' => '/api/v1/lookups/vendor-questionnaire-templates/options',
        'linked_asset_id' => '/api/v1/assets',
        'linked_control_id' => '/api/v1/lookups/controls/options',
        'linked_risk_id' => '/api/v1/lookups/risks/options',
        'linked_finding_id' => '/api/v1/lookups/findings/options',
        'owner_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::post('/vendors/{vendorId}/reviews/{reviewId}/external-links', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $vendors,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'contact_name' => ['nullable', 'string', 'max:120'],
        'contact_email' => ['required', 'email', 'max:160'],
        'expires_at' => ['nullable', 'date', 'after:now'],
        'can_answer_questionnaire' => ['nullable', 'boolean'],
        'can_upload_artifacts' => ['nullable', 'boolean'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    [$link, $token] = $vendors->issueExternalLinkForReview($reviewId, [
        ...$validated,
        'issued_by_principal_id' => $principalId,
        'can_answer_questionnaire' => (bool) ($validated['can_answer_questionnaire'] ?? false),
        'can_upload_artifacts' => (bool) ($validated['can_upload_artifacts'] ?? false),
    ]);

    return $apiSuccess([
        'link' => $link,
        'portal_url' => route('plugin.third-party-risk.external.portal.show', ['token' => $token]),
        'portal_token' => $token,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskIssueExternalLink',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Issue one external collaboration link for a vendor review',
    'responses' => [
        '200' => [
            'description' => 'External link issued',
        ],
        '404' => [
            'description' => 'Vendor or review not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'contact_name' => ['nullable', 'string', 'max:120'],
        'contact_email' => ['required', 'email', 'max:160'],
        'expires_at' => ['nullable', 'date'],
        'can_answer_questionnaire' => ['nullable', 'boolean'],
        'can_upload_artifacts' => ['nullable', 'boolean'],
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::patch('/vendors/{vendorId}/reviews/{reviewId}/external-links/{linkId}/revoke', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $linkId,
    ThirdPartyRiskRepository $vendors,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $record = $vendors->revokeExternalLink($reviewId, $linkId, $principalId);
    abort_if($record === null, 404);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskRevokeExternalLink',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Revoke one external collaboration link',
    'responses' => [
        '200' => [
            'description' => 'External link revoked',
        ],
        '404' => [
            'description' => 'Vendor, review, or external link not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::patch('/vendors/{vendorId}/reviews/{reviewId}/external-collaborators/{collaboratorId}', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $collaboratorId,
    ThirdPartyRiskRepository $vendors,
    CollaborationEngineInterface $collaboration,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'lifecycle_state' => ['required', 'string', Rule::in($collaboration->collaboratorLifecycleStateKeys())],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $record = $vendors->updateExternalCollaboratorLifecycleForReview(
        reviewId: $reviewId,
        collaboratorId: $collaboratorId,
        lifecycleState: $validated['lifecycle_state'],
        principalId: $principalId,
    );
    abort_if($record === null, 404);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskUpdateExternalCollaboratorLifecycle',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Update one external collaborator lifecycle state',
    'responses' => [
        '200' => [
            'description' => 'External collaborator lifecycle updated',
        ],
        '404' => [
            'description' => 'Vendor, review, or collaborator not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'lifecycle_state' => ['required', 'string'],
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::post('/external/vendor-review/{token}/questionnaire-items/{itemId}', function (
    Request $request,
    string $token,
    string $itemId,
    ThirdPartyRiskRepository $vendors,
    AuditTrailInterface $audit,
    QuestionnaireEngineInterface $questionnaires,
) use ($apiSuccess) {
    $link = $vendors->resolveExternalLinkByToken($token);
    abort_if($link === null || $link['can_answer_questionnaire'] !== '1', 404);

    $review = $vendors->findReview($link['review_id']);
    $item = $vendors->findQuestionnaireItem($itemId);

    abort_if($review === null || $item === null || $item['review_id'] !== $review['id'], 404);

    $validated = $request->validate([
        'answer_text' => $questionnaires->answerValidationRules($item['response_type']),
    ]);

    $updated = $vendors->submitExternalQuestionnaireAnswer($review['id'], $itemId, (string) $validated['answer_text']);
    abort_if($updated === null, 404);

    $audit->record(new AuditRecordData(
        eventType: 'plugin.third-party-risk.external-link.questionnaire-submitted',
        outcome: 'success',
        originComponent: 'third-party-risk',
        organizationId: $link['organization_id'],
        scopeId: $link['scope_id'] !== '' ? $link['scope_id'] : null,
        targetType: 'vendor-review',
        targetId: $review['id'],
        summary: [
            'external_link_id' => $link['id'],
            'questionnaire_item_id' => $itemId,
            'contact_email' => $link['contact_email'],
        ],
        executionOrigin: 'third-party-risk-external-portal',
    ));

    return $apiSuccess($updated);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskExternalSubmitQuestionnaireAnswer',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Submit one external questionnaire answer using a review token',
    'responses' => [
        '200' => [
            'description' => 'Questionnaire answer submitted',
        ],
        '404' => [
            'description' => 'Token, review, or questionnaire item not found',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_rules' => [
        'answer_text' => ['required', 'string'],
    ],
]);

Route::post('/external/vendor-review/{token}/questionnaire-items/{itemId}/artifacts', function (
    Request $request,
    string $token,
    string $itemId,
    ThirdPartyRiskRepository $vendors,
    ArtifactServiceInterface $artifacts,
    AuditTrailInterface $audit,
) use ($apiSuccess) {
    $link = $vendors->resolveExternalLinkByToken($token);
    abort_if($link === null || $link['can_upload_artifacts'] !== '1', 404);

    $review = $vendors->findReview($link['review_id']);
    $item = $vendors->findQuestionnaireItem($itemId);

    abort_if($review === null || $item === null || $item['review_id'] !== $review['id'] || ($item['attachment_mode'] ?? 'none') === 'none', 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['required', 'string', 'max:120'],
    ]);

    $record = $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'questionnaires',
        subjectType: 'questionnaire-subject-item',
        subjectId: $itemId,
        artifactType: ($item['attachment_mode'] ?? 'none') === 'supporting-evidence' ? 'evidence' : 'document',
        label: (string) $validated['label'],
        file: $validated['artifact'],
        organizationId: $link['organization_id'],
        scopeId: $link['scope_id'] !== '' ? $link['scope_id'] : null,
        metadata: [
            'plugin' => 'questionnaires',
            'questionnaire_owner_component' => 'third-party-risk',
            'questionnaire_subject_type' => 'vendor-review',
            'questionnaire_subject_id' => $review['id'],
            'questionnaire_item_id' => $itemId,
            'questionnaire_prompt' => $item['prompt'],
            'review_id' => $review['id'],
            'contact_email' => $link['contact_email'],
            'external_link_id' => $link['id'],
            'source' => 'external-portal',
        ],
        uploadProfile: ($item['attachment_upload_profile'] ?? '') !== '' ? $item['attachment_upload_profile'] : 'documents_only',
        executionOrigin: 'third-party-risk-external-portal',
    ));

    $audit->record(new AuditRecordData(
        eventType: 'plugin.third-party-risk.external-link.questionnaire-artifact-submitted',
        outcome: 'success',
        originComponent: 'third-party-risk',
        organizationId: $link['organization_id'],
        scopeId: $link['scope_id'] !== '' ? $link['scope_id'] : null,
        targetType: 'artifact',
        targetId: $record->id,
        summary: [
            'external_link_id' => $link['id'],
            'review_id' => $review['id'],
            'questionnaire_item_id' => $itemId,
            'contact_email' => $link['contact_email'],
            'label' => $record->label,
        ],
        executionOrigin: 'third-party-risk-external-portal',
    ));

    return $apiSuccess($record->toArray());
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskExternalAttachQuestionnaireArtifact',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Upload one external questionnaire artifact using a review token',
    'responses' => [
        '200' => [
            'description' => 'Questionnaire artifact uploaded',
        ],
        '404' => [
            'description' => 'Token, review, item, or attachment mode not valid',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_body' => [
        'required' => true,
        'content' => [
            'multipart/form-data' => [
                'schema' => [
                    'type' => 'object',
                    'required' => ['artifact', 'label'],
                    'properties' => [
                        'artifact' => ['type' => 'string', 'format' => 'binary'],
                        'label' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
]);

Route::post('/external/vendor-review/{token}/artifacts', function (
    Request $request,
    string $token,
    ThirdPartyRiskRepository $vendors,
    ArtifactServiceInterface $artifacts,
    AuditTrailInterface $audit,
) use ($apiSuccess) {
    $link = $vendors->resolveExternalLinkByToken($token);
    abort_if($link === null || $link['can_upload_artifacts'] !== '1', 404);

    $review = $vendors->findReview($link['review_id']);
    abort_if($review === null, 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['required', 'string', 'max:120'],
    ]);

    $record = $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'third-party-risk',
        subjectType: 'vendor-review',
        subjectId: $review['id'],
        artifactType: 'evidence',
        label: (string) $validated['label'],
        file: $validated['artifact'],
        organizationId: $link['organization_id'],
        scopeId: $link['scope_id'] !== '' ? $link['scope_id'] : null,
        metadata: [
            'plugin' => 'third-party-risk',
            'external_link_id' => $link['id'],
            'review_id' => $review['id'],
            'contact_email' => $link['contact_email'],
            'source' => 'external-portal',
        ],
        uploadProfile: 'review_artifacts',
        executionOrigin: 'third-party-risk-external-portal',
    ));

    $audit->record(new AuditRecordData(
        eventType: 'plugin.third-party-risk.external-link.artifact-submitted',
        outcome: 'success',
        originComponent: 'third-party-risk',
        organizationId: $link['organization_id'],
        scopeId: $link['scope_id'] !== '' ? $link['scope_id'] : null,
        targetType: 'artifact',
        targetId: $record->id,
        summary: [
            'external_link_id' => $link['id'],
            'review_id' => $review['id'],
            'contact_email' => $link['contact_email'],
            'label' => $record->label,
        ],
        executionOrigin: 'third-party-risk-external-portal',
    ));

    return $apiSuccess($record->toArray());
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskExternalAttachReviewArtifact',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Upload one external review artifact using a review token',
    'responses' => [
        '200' => [
            'description' => 'Review artifact uploaded',
        ],
        '404' => [
            'description' => 'Token or review not found',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_body' => [
        'required' => true,
        'content' => [
            'multipart/form-data' => [
                'schema' => [
                    'type' => 'object',
                    'required' => ['artifact', 'label'],
                    'properties' => [
                        'artifact' => ['type' => 'string', 'format' => 'binary'],
                        'label' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
]);

Route::post('/vendors/{vendorId}/reviews/{reviewId}/collaboration/drafts', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $vendors,
    CollaborationEngineInterface $collaboration,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'draft_type' => ['required', 'string', Rule::in($collaboration->draftTypeKeys())],
        'title' => ['nullable', 'string', 'max:200'],
        'body' => ['nullable', 'string', 'max:4000'],
        'details' => ['nullable', 'string', 'max:4000'],
        'priority' => ['nullable', 'string', Rule::in($collaboration->requestPriorityKeys())],
        'handoff_state' => ['nullable', 'string', Rule::in($collaboration->handoffStateKeys())],
        'mentioned_actor_ids' => ['nullable', 'array'],
        'mentioned_actor_ids.*' => ['string', 'max:64', 'exists:functional_actors,id'],
        'assigned_actor_id' => ['nullable', 'string', 'max:64', 'exists:functional_actors,id'],
        'due_on' => ['nullable', 'date'],
    ]);

    if (($validated['draft_type'] ?? 'comment') === 'comment' && trim((string) ($validated['body'] ?? '')) === '') {
        throw ValidationException::withMessages([
            'body' => 'Comment drafts require body text.',
        ]);
    }

    if (($validated['draft_type'] ?? 'comment') !== 'comment' && trim((string) ($validated['title'] ?? '')) === '') {
        throw ValidationException::withMessages([
            'title' => 'Follow-up drafts require a title.',
        ]);
    }

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $record = $vendors->createCollaborationDraftForReview($reviewId, [
        ...$validated,
        'edited_by_principal_id' => $principalId,
    ]);
    abort_if($record === null, 404);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskCreateVendorReviewDraft',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Create one shared collaboration draft for a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Collaboration draft created',
        ],
        '404' => [
            'description' => 'Vendor or review not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'draft_type' => ['required', 'string'],
        'title' => ['nullable', 'string', 'max:200'],
        'body' => ['nullable', 'string', 'max:4000'],
        'details' => ['nullable', 'string', 'max:4000'],
        'priority' => ['nullable', 'string'],
        'handoff_state' => ['nullable', 'string'],
        'mentioned_actor_ids' => ['nullable', 'array'],
        'mentioned_actor_ids.*' => ['string', 'max:64'],
        'assigned_actor_id' => ['nullable', 'string', 'max:64'],
        'due_on' => ['nullable', 'date'],
    ],
    'lookup_fields' => [
        'mentioned_actor_ids' => '/api/v1/lookups/actors/options',
        'assigned_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::patch('/vendors/{vendorId}/reviews/{reviewId}/collaboration/drafts/{draftId}', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $draftId,
    ThirdPartyRiskRepository $vendors,
    CollaborationEngineInterface $collaboration,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'draft_type' => ['required', 'string', Rule::in($collaboration->draftTypeKeys())],
        'title' => ['nullable', 'string', 'max:200'],
        'body' => ['nullable', 'string', 'max:4000'],
        'details' => ['nullable', 'string', 'max:4000'],
        'priority' => ['nullable', 'string', Rule::in($collaboration->requestPriorityKeys())],
        'handoff_state' => ['nullable', 'string', Rule::in($collaboration->handoffStateKeys())],
        'mentioned_actor_ids' => ['nullable', 'array'],
        'mentioned_actor_ids.*' => ['string', 'max:64', 'exists:functional_actors,id'],
        'assigned_actor_id' => ['nullable', 'string', 'max:64', 'exists:functional_actors,id'],
        'due_on' => ['nullable', 'date'],
    ]);

    if (($validated['draft_type'] ?? 'comment') === 'comment' && trim((string) ($validated['body'] ?? '')) === '') {
        throw ValidationException::withMessages([
            'body' => 'Comment drafts require body text.',
        ]);
    }

    if (($validated['draft_type'] ?? 'comment') !== 'comment' && trim((string) ($validated['title'] ?? '')) === '') {
        throw ValidationException::withMessages([
            'title' => 'Follow-up drafts require a title.',
        ]);
    }

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $record = $vendors->updateCollaborationDraftForReview(
        reviewId: $reviewId,
        draftId: $draftId,
        data: [
            ...$validated,
            'edited_by_principal_id' => $principalId,
        ],
        principalId: $principalId,
    );
    abort_if($record === null, 404);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskUpdateVendorReviewDraft',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Update one shared collaboration draft for a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Collaboration draft updated',
        ],
        '404' => [
            'description' => 'Vendor, review, or draft not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'draft_type' => ['required', 'string'],
        'title' => ['nullable', 'string', 'max:200'],
        'body' => ['nullable', 'string', 'max:4000'],
        'details' => ['nullable', 'string', 'max:4000'],
        'priority' => ['nullable', 'string'],
        'handoff_state' => ['nullable', 'string'],
        'mentioned_actor_ids' => ['nullable', 'array'],
        'mentioned_actor_ids.*' => ['string', 'max:64'],
        'assigned_actor_id' => ['nullable', 'string', 'max:64'],
        'due_on' => ['nullable', 'date'],
    ],
    'lookup_fields' => [
        'mentioned_actor_ids' => '/api/v1/lookups/actors/options',
        'assigned_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::post('/vendors/{vendorId}/reviews/{reviewId}/collaboration/drafts/{draftId}/promote-comment', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $draftId,
    ThirdPartyRiskRepository $vendors,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $record = $vendors->promoteCollaborationDraftToComment($reviewId, $draftId, $principalId);
    abort_if($record === null, 404);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskPromoteVendorReviewDraftToComment',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Promote one collaboration draft to comment',
    'responses' => [
        '200' => [
            'description' => 'Draft promoted to comment',
        ],
        '404' => [
            'description' => 'Vendor, review, or draft not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::post('/vendors/{vendorId}/reviews/{reviewId}/collaboration/drafts/{draftId}/promote-request', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $draftId,
    ThirdPartyRiskRepository $vendors,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $record = $vendors->promoteCollaborationDraftToRequest($reviewId, $draftId, $principalId);
    abort_if($record === null, 404);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskPromoteVendorReviewDraftToRequest',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Promote one collaboration draft to follow-up request',
    'responses' => [
        '200' => [
            'description' => 'Draft promoted to follow-up request',
        ],
        '404' => [
            'description' => 'Vendor, review, or draft not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::patch('/vendors/{vendorId}/reviews/{reviewId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $assignmentId,
    ThirdPartyRiskRepository $vendors,
    FunctionalActorServiceInterface $actors,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $assignment = collect($actors->assignmentsFor(
        domainObjectType: 'vendor-review',
        domainObjectId: $reviewId,
        organizationId: $vendor['organization_id'],
        scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
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
    'operation_id' => 'thirdPartyRiskRemoveVendorReviewOwner',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Remove one owner assignment from a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Owner assignment removed',
        ],
        '404' => [
            'description' => 'Vendor, review, or assignment not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::post('/vendors/{vendorId}/reviews/{reviewId}/artifacts', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $vendors,
    ArtifactServiceInterface $artifacts,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

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
        ownerComponent: 'third-party-risk',
        subjectType: 'vendor-review',
        subjectId: $reviewId,
        artifactType: (string) ($validated['artifact_type'] ?? 'evidence'),
        label: (string) ($validated['label'] ?? 'Vendor review evidence'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $vendor['organization_id'],
        scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        uploadProfile: 'review_artifacts',
        metadata: [
            'plugin' => 'third-party-risk',
            'vendor_id' => $vendorId,
            'review_id' => $reviewId,
        ],
    ));

    return $apiSuccess($record->toArray());
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskAttachVendorReviewArtifact',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Upload one artifact to a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Artifact uploaded',
        ],
        '404' => [
            'description' => 'Vendor or review not found in current context',
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
                        'artifact' => [
                            'type' => 'string',
                            'format' => 'binary',
                        ],
                        'label' => [
                            'type' => 'string',
                        ],
                        'artifact_type' => [
                            'type' => 'string',
                        ],
                        'membership_id' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ],
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::post('/vendors/{vendorId}/reviews/{reviewId}/questionnaire-items/{itemId}/artifacts', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $itemId,
    ThirdPartyRiskRepository $vendors,
    ArtifactServiceInterface $artifacts,
    AuditTrailInterface $audit,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    $item = $vendors->findQuestionnaireItem($itemId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId || $item === null || $item['review_id'] !== $reviewId || ($item['attachment_mode'] ?? 'none') === 'none', 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'membership_id' => ['nullable', 'string', 'max:120'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $membershipId = $validated['membership_id'] ?? $request->input('membership_id');
    $record = $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'questionnaires',
        subjectType: 'questionnaire-subject-item',
        subjectId: $itemId,
        artifactType: ($item['attachment_mode'] ?? 'none') === 'supporting-evidence' ? 'evidence' : 'document',
        label: (string) ($validated['label'] ?? 'Questionnaire attachment'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $vendor['organization_id'],
        scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        uploadProfile: ($item['attachment_upload_profile'] ?? '') !== '' ? $item['attachment_upload_profile'] : 'documents_only',
        metadata: [
            'plugin' => 'questionnaires',
            'questionnaire_owner_component' => 'third-party-risk',
            'questionnaire_subject_type' => 'vendor-review',
            'questionnaire_subject_id' => $reviewId,
            'questionnaire_item_id' => $itemId,
            'questionnaire_prompt' => $item['prompt'],
            'vendor_id' => $vendorId,
            'review_id' => $reviewId,
        ],
        executionOrigin: 'third-party-risk-questionnaire-item',
    ));

    $audit->record(new AuditRecordData(
        eventType: 'plugin.third-party-risk.questionnaire-item.artifact-uploaded',
        outcome: 'success',
        originComponent: 'third-party-risk',
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $vendor['organization_id'],
        scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        targetType: 'artifact',
        targetId: $record->id,
        summary: [
            'review_id' => $reviewId,
            'questionnaire_item_id' => $itemId,
            'label' => $record->label,
        ],
        executionOrigin: 'third-party-risk',
    ));

    return $apiSuccess($record->toArray());
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskAttachQuestionnaireItemArtifact',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Upload one artifact to a vendor review questionnaire item',
    'responses' => [
        '200' => [
            'description' => 'Questionnaire attachment uploaded',
        ],
        '404' => [
            'description' => 'Vendor, review, or questionnaire item not found in current context',
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
                        'artifact' => [
                            'type' => 'string',
                            'format' => 'binary',
                        ],
                        'label' => [
                            'type' => 'string',
                        ],
                        'membership_id' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ],
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::post('/vendors/{vendorId}/reviews/{reviewId}/collaboration/comments', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $vendors,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'body' => ['required', 'string', 'max:4000'],
        'mentioned_actor_ids' => ['nullable', 'array'],
        'mentioned_actor_ids.*' => ['string', 'max:64', 'exists:functional_actors,id'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $comment = $vendors->addCommentToReview($reviewId, [
        'author_principal_id' => $principalId,
        'body' => $validated['body'],
        'mentioned_actor_ids' => $validated['mentioned_actor_ids'] ?? [],
    ]);
    abort_if($comment === null, 404);

    return $apiSuccess($comment);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskAddVendorReviewComment',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Add one collaboration comment to a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Comment created',
        ],
        '404' => [
            'description' => 'Vendor or review not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'body' => ['required', 'string', 'max:4000'],
        'mentioned_actor_ids' => ['nullable', 'array'],
        'mentioned_actor_ids.*' => ['string', 'max:64'],
    ],
    'lookup_fields' => [
        'mentioned_actor_ids' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::post('/vendors/{vendorId}/reviews/{reviewId}/collaboration/requests', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $vendors,
    CollaborationEngineInterface $collaboration,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:200'],
        'details' => ['nullable', 'string', 'max:4000'],
        'status' => ['required', 'string', Rule::in($collaboration->requestStatusKeys())],
        'priority' => ['required', 'string', Rule::in($collaboration->requestPriorityKeys())],
        'handoff_state' => ['required', 'string', Rule::in($collaboration->handoffStateKeys())],
        'mentioned_actor_ids' => ['nullable', 'array'],
        'mentioned_actor_ids.*' => ['string', 'max:64', 'exists:functional_actors,id'],
        'assigned_actor_id' => ['nullable', 'string', 'max:64', 'exists:functional_actors,id'],
        'due_on' => ['nullable', 'date'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $record = $vendors->createCollaborationRequestForReview($reviewId, [
        ...$validated,
        'requested_by_principal_id' => $principalId,
    ]);
    abort_if($record === null, 404);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskCreateVendorReviewRequest',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Create one collaboration follow-up request for a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Follow-up request created',
        ],
        '404' => [
            'description' => 'Vendor or review not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'title' => ['required', 'string', 'max:200'],
        'details' => ['nullable', 'string', 'max:4000'],
        'status' => ['required', 'string'],
        'priority' => ['required', 'string'],
        'handoff_state' => ['required', 'string'],
        'mentioned_actor_ids' => ['nullable', 'array'],
        'mentioned_actor_ids.*' => ['string', 'max:64'],
        'assigned_actor_id' => ['nullable', 'string', 'max:64'],
        'due_on' => ['nullable', 'date'],
    ],
    'lookup_fields' => [
        'mentioned_actor_ids' => '/api/v1/lookups/actors/options',
        'assigned_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::patch('/vendors/{vendorId}/reviews/{reviewId}/collaboration/requests/{requestId}', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $requestId,
    ThirdPartyRiskRepository $vendors,
    CollaborationEngineInterface $collaboration,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:200'],
        'details' => ['nullable', 'string', 'max:4000'],
        'status' => ['required', 'string', Rule::in($collaboration->requestStatusKeys())],
        'priority' => ['required', 'string', Rule::in($collaboration->requestPriorityKeys())],
        'handoff_state' => ['required', 'string', Rule::in($collaboration->handoffStateKeys())],
        'mentioned_actor_ids' => ['nullable', 'array'],
        'mentioned_actor_ids.*' => ['string', 'max:64', 'exists:functional_actors,id'],
        'assigned_actor_id' => ['nullable', 'string', 'max:64', 'exists:functional_actors,id'],
        'due_on' => ['nullable', 'date'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $record = $vendors->updateCollaborationRequestForReview(
        reviewId: $reviewId,
        requestId: $requestId,
        data: $validated,
        principalId: $principalId,
    );
    abort_if($record === null, 404);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskUpdateVendorReviewRequest',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Update one collaboration follow-up request for a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Follow-up request updated',
        ],
        '404' => [
            'description' => 'Vendor, review, or request not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'title' => ['required', 'string', 'max:200'],
        'details' => ['nullable', 'string', 'max:4000'],
        'status' => ['required', 'string'],
        'priority' => ['required', 'string'],
        'handoff_state' => ['required', 'string'],
        'mentioned_actor_ids' => ['nullable', 'array'],
        'mentioned_actor_ids.*' => ['string', 'max:64'],
        'assigned_actor_id' => ['nullable', 'string', 'max:64'],
        'due_on' => ['nullable', 'date'],
    ],
    'lookup_fields' => [
        'mentioned_actor_ids' => '/api/v1/lookups/actors/options',
        'assigned_actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::post('/vendors/{vendorId}/reviews/{reviewId}/brokered-requests', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $vendors,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'contact_name' => ['required', 'string', 'max:120'],
        'contact_email' => ['nullable', 'email', 'max:160'],
        'collection_channel' => ['required', Rule::in(['email', 'meeting', 'call', 'uploaded-docs', 'broker-note'])],
        'instructions' => ['nullable', 'string', 'max:2000'],
        'broker_principal_id' => ['nullable', 'string', 'max:64'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $record = $vendors->issueBrokeredRequestForReview($reviewId, [
        ...$validated,
        'issued_by_principal_id' => $principalId,
        'broker_principal_id' => is_string($validated['broker_principal_id'] ?? null) && $validated['broker_principal_id'] !== ''
            ? $validated['broker_principal_id']
            : $principalId,
    ]);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskIssueBrokeredRequest',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Issue one brokered collection request for a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Brokered request created',
        ],
        '404' => [
            'description' => 'Vendor or review not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'contact_name' => ['required', 'string', 'max:120'],
        'contact_email' => ['nullable', 'email', 'max:160'],
        'collection_channel' => ['required', 'string'],
        'instructions' => ['nullable', 'string', 'max:2000'],
        'broker_principal_id' => ['nullable', 'string', 'max:64'],
    ],
    'lookup_fields' => [
        'broker_principal_id' => '/api/v1/lookups/principals/options',
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::patch('/vendors/{vendorId}/reviews/{reviewId}/brokered-requests/{requestId}', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $requestId,
    ThirdPartyRiskRepository $vendors,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'collection_status' => ['required', Rule::in(['queued', 'in-progress', 'submitted', 'completed', 'cancelled'])],
        'broker_notes' => ['nullable', 'string', 'max:2000'],
        'broker_principal_id' => ['nullable', 'string', 'max:64'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $record = $vendors->updateBrokeredRequestForReview(
        reviewId: $reviewId,
        requestId: $requestId,
        data: [
            ...$validated,
            'broker_principal_id' => is_string($validated['broker_principal_id'] ?? null) && $validated['broker_principal_id'] !== ''
                ? $validated['broker_principal_id']
                : $principalId,
        ],
        principalId: $principalId,
    );
    abort_if($record === null, 404);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskUpdateBrokeredRequest',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Update one brokered collection request for a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Brokered request updated',
        ],
        '404' => [
            'description' => 'Vendor, review, or brokered request not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'collection_status' => ['required', 'string'],
        'broker_notes' => ['nullable', 'string', 'max:2000'],
        'broker_principal_id' => ['nullable', 'string', 'max:64'],
    ],
    'lookup_fields' => [
        'broker_principal_id' => '/api/v1/lookups/principals/options',
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::post('/vendors/{vendorId}/reviews/{reviewId}/questionnaire-items', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $vendors,
    QuestionnaireEngineInterface $questionnaires,
) use ($apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'section_title' => ['nullable', 'string', 'max:120'],
        'prompt' => ['required', 'string', 'max:2000'],
        'guidance_text' => ['nullable', 'string', 'max:2000'],
        'response_type' => ['required', 'string', Rule::in($questionnaires->responseTypeKeys())],
        'attachment_mode' => ['nullable', 'string', Rule::in($questionnaires->attachmentModeKeys())],
        'attachment_upload_profile' => ['nullable', 'string', Rule::in(['documents_only', 'documents_and_spreadsheets', 'images_only', 'review_artifacts'])],
        'is_required' => ['nullable', 'boolean'],
    ]);

    $record = $vendors->addQuestionnaireItem($reviewId, $validated);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskAddQuestionnaireItem',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Add one questionnaire item to a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Questionnaire item created',
        ],
        '404' => [
            'description' => 'Vendor or review not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'section_title' => ['nullable', 'string', 'max:120'],
        'prompt' => ['required', 'string', 'max:2000'],
        'guidance_text' => ['nullable', 'string', 'max:2000'],
        'response_type' => ['required', 'string'],
        'attachment_mode' => ['nullable', 'string'],
        'attachment_upload_profile' => ['nullable', 'string'],
        'is_required' => ['nullable', 'boolean'],
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::patch('/vendors/{vendorId}/reviews/{reviewId}/questionnaire-items/{itemId}', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $itemId,
    ThirdPartyRiskRepository $vendors,
    QuestionnaireEngineInterface $questionnaires,
) use ($apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    $item = $vendors->findQuestionnaireItem($itemId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId || $item === null || $item['review_id'] !== $reviewId, 404);

    $validated = $request->validate([
        'section_title' => ['nullable', 'string', 'max:120'],
        'prompt' => ['required', 'string', 'max:2000'],
        'guidance_text' => ['nullable', 'string', 'max:2000'],
        'response_type' => ['required', 'string', Rule::in($questionnaires->responseTypeKeys())],
        'response_status' => ['nullable', 'string', Rule::in($questionnaires->responseStatusKeys())],
        'attachment_mode' => ['nullable', 'string', Rule::in($questionnaires->attachmentModeKeys())],
        'attachment_upload_profile' => ['nullable', 'string', Rule::in(['documents_only', 'documents_and_spreadsheets', 'images_only', 'review_artifacts'])],
        'is_required' => ['nullable', 'boolean'],
        'answer_text' => ['nullable', 'string', 'max:4000'],
        'follow_up_notes' => ['nullable', 'string', 'max:2000'],
    ]);

    $record = $vendors->updateQuestionnaireItem($reviewId, $itemId, $validated);
    abort_if($record === null, 404);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskUpdateQuestionnaireItem',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Update one questionnaire item for a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Questionnaire item updated',
        ],
        '404' => [
            'description' => 'Vendor, review, or questionnaire item not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'section_title' => ['nullable', 'string', 'max:120'],
        'prompt' => ['required', 'string', 'max:2000'],
        'guidance_text' => ['nullable', 'string', 'max:2000'],
        'response_type' => ['required', 'string'],
        'response_status' => ['nullable', 'string'],
        'attachment_mode' => ['nullable', 'string'],
        'attachment_upload_profile' => ['nullable', 'string'],
        'is_required' => ['nullable', 'boolean'],
        'answer_text' => ['nullable', 'string', 'max:4000'],
        'follow_up_notes' => ['nullable', 'string', 'max:2000'],
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::patch('/vendors/{vendorId}/reviews/{reviewId}/questionnaire-items/{itemId}/review', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $itemId,
    ThirdPartyRiskRepository $vendors,
) use ($apiPrincipalId, $apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    $item = $vendors->findQuestionnaireItem($itemId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId || $item === null || $item['review_id'] !== $reviewId, 404);

    $validated = $request->validate([
        'response_status' => ['required', 'string', Rule::in(['under-review', 'accepted', 'needs-follow-up'])],
        'review_notes' => ['nullable', 'string', 'max:2000'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $record = $vendors->reviewQuestionnaireItem(
        reviewId: $reviewId,
        itemId: $itemId,
        responseStatus: $validated['response_status'],
        reviewNotes: $validated['review_notes'] ?? null,
        reviewedByPrincipalId: $principalId,
    );
    abort_if($record === null, 404);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskReviewQuestionnaireItem',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Review one questionnaire item for a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Questionnaire review updated',
        ],
        '404' => [
            'description' => 'Vendor, review, or questionnaire item not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'response_status' => ['required', 'string'],
        'review_notes' => ['nullable', 'string', 'max:2000'],
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::post('/vendors/{vendorId}/reviews/{reviewId}/questionnaire-template/apply', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $vendors,
) use ($apiSuccess) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'questionnaire_template_id' => ['required', 'string', 'max:120'],
    ]);

    $created = $vendors->applyQuestionnaireTemplateToReview($reviewId, $validated['questionnaire_template_id']);

    return $apiSuccess([
        'review_id' => $reviewId,
        'questionnaire_template_id' => $validated['questionnaire_template_id'],
        'created_items' => $created,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskApplyQuestionnaireTemplate',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Apply one questionnaire template to a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Template applied',
        ],
        '404' => [
            'description' => 'Vendor or review not found in current context',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [
        'questionnaire_template_id' => ['required', 'string', 'max:120'],
    ],
    'lookup_fields' => [
        'questionnaire_template_id' => '/api/v1/lookups/vendor-questionnaire-templates/options',
    ],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

Route::post('/vendors/{vendorId}/reviews/{reviewId}/transitions/{transitionKey}', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $transitionKey,
    ThirdPartyRiskRepository $vendors,
    WorkflowServiceInterface $workflows,
    TenancyServiceInterface $tenancy,
) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
    $vendor = $vendors->find($vendorId);
    $review = $vendors->findReview($reviewId);
    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    $context = $resolveTenancy($request, $tenancy, $principalId);
    $organizationId = $context->organization?->id;
    abort_unless(is_string($organizationId) && $organizationId === $vendor['organization_id'], 404);

    $workflows->transition(
        workflowKey: 'plugin.third-party-risk.review-lifecycle',
        subjectType: 'vendor-review',
        subjectId: $reviewId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'api'),
            memberships: $context->memberships,
            organizationId: $organizationId,
            scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
            membershipId: $context->membershipIds()[0] ?? null,
        ),
    );

    $vendors->syncVendorStatusForReview($reviewId, $transitionKey);

    $updatedVendor = $vendors->find($vendorId);
    $updatedReview = $vendors->findReview($reviewId);

    return $apiSuccess([
        'vendor' => $updatedVendor,
        'review' => $updatedReview,
        'transition' => $transitionKey,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'thirdPartyRiskTransitionReview',
    'tags' => ['vendors'],
    'tag_descriptions' => [
        'vendors' => 'Third-party risk and vendor review API surface.',
    ],
    'summary' => 'Apply one workflow transition to a vendor review',
    'responses' => [
        '200' => [
            'description' => 'Transition applied',
        ],
        '404' => [
            'description' => 'Vendor or review not found in current context',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
    ],
    'request_rules' => [],
])->middleware('core.permission:plugin.third-party-risk.vendors.manage');
