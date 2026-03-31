<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Plugins\ThirdPartyRisk\ExternalReviewInvitationDeliveryService;
use PymeSec\Plugins\ThirdPartyRisk\ThirdPartyRiskRepository;

Route::get('/plugins/vendors', function (Request $request, ThirdPartyRiskRepository $repository) {
    $organizationId = (string) $request->query('organization_id', 'org-a');
    $scopeId = $request->query('scope_id');

    return response()->json([
        'plugin' => 'third-party-risk',
        'vendors' => $repository->all($organizationId, is_string($scopeId) ? $scopeId : null),
    ]);
})->middleware('core.permission:plugin.third-party-risk.vendors.view')->name('plugin.third-party-risk.index');

Route::post('/plugins/vendors', function (
    Request $request,
    ThirdPartyRiskRepository $repository,
    FunctionalActorServiceInterface $actors,
) {
    $validated = $request->validate([
        'legal_name' => ['required', 'string', 'max:140'],
        'service_summary' => ['required', 'string', 'max:255'],
        'tier' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
        'website' => ['nullable', 'url', 'max:255'],
        'primary_contact_name' => ['nullable', 'string', 'max:120'],
        'primary_contact_email' => ['nullable', 'email', 'max:160'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'review_profile_id' => ['nullable', 'string', 'max:120'],
        'questionnaire_template_id' => ['nullable', 'string', 'max:120'],
        'review_title' => ['required', 'string', 'max:140'],
        'inherent_risk' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
        'review_summary' => ['required', 'string', 'max:2000'],
        'decision_notes' => ['nullable', 'string', 'max:2000'],
        'linked_asset_id' => ['nullable', 'string', 'max:120', 'exists:assets,id'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120', 'exists:risks,id'],
        'linked_finding_id' => ['nullable', 'string', 'max:120', 'exists:findings,id'],
        'next_review_due_on' => ['nullable', 'date'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    [$vendor, $review] = $repository->createVendorWithReview([
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
            metadata: ['source' => 'third-party-risk'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.store');

Route::post('/plugins/vendors/{vendorId}', function (
    Request $request,
    string $vendorId,
    ThirdPartyRiskRepository $repository,
    FunctionalActorServiceInterface $actors,
) {
    $vendor = $repository->find($vendorId);
    abort_if($vendor === null, 404);

    $validated = $request->validate([
        'legal_name' => ['required', 'string', 'max:140'],
        'service_summary' => ['required', 'string', 'max:255'],
        'tier' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
        'website' => ['nullable', 'url', 'max:255'],
        'primary_contact_name' => ['nullable', 'string', 'max:120'],
        'primary_contact_email' => ['nullable', 'email', 'max:160'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'review_id' => ['required', 'string', 'max:120'],
        'review_profile_id' => ['nullable', 'string', 'max:120'],
        'questionnaire_template_id' => ['nullable', 'string', 'max:120'],
        'review_title' => ['required', 'string', 'max:140'],
        'inherent_risk' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
        'review_summary' => ['required', 'string', 'max:2000'],
        'decision_notes' => ['nullable', 'string', 'max:2000'],
        'linked_asset_id' => ['nullable', 'string', 'max:120', 'exists:assets,id'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120', 'exists:risks,id'],
        'linked_finding_id' => ['nullable', 'string', 'max:120', 'exists:findings,id'],
        'next_review_due_on' => ['nullable', 'date'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    [$vendor, $review] = $repository->updateVendorWithReview($vendorId, [
        ...$validated,
        'organization_id' => (string) $request->input('organization_id', 'org-a'),
    ]);

    abort_if($vendor === null || $review === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'vendor-review',
            domainObjectId: $review['id'],
            assignmentType: 'owner',
            organizationId: $vendor['organization_id'],
            scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
            metadata: ['source' => 'third-party-risk'],
            assignedByPrincipalId: $principalId,
        );
    }

    DB::table('functional_assignments')
        ->where('domain_object_type', 'vendor-review')
        ->where('domain_object_id', $review['id'])
        ->where('organization_id', $vendor['organization_id'])
        ->where('is_active', true)
        ->update([
            'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
            'updated_at' => now(),
        ]);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.update');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/external-links', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $repository,
    ExternalReviewInvitationDeliveryService $delivery,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'contact_name' => ['nullable', 'string', 'max:120'],
        'contact_email' => ['required', 'email', 'max:160'],
        'expires_at' => ['nullable', 'date', 'after:now'],
        'can_answer_questionnaire' => ['nullable', 'in:1'],
        'can_upload_artifacts' => ['nullable', 'in:1'],
        'send_email_invitation' => ['nullable', 'in:1'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    [$link, $token] = $repository->issueExternalLinkForReview($reviewId, [
        ...$validated,
        'issued_by_principal_id' => $principalId,
        'can_answer_questionnaire' => ($validated['can_answer_questionnaire'] ?? null) === '1',
        'can_upload_artifacts' => ($validated['can_upload_artifacts'] ?? null) === '1',
    ]);

    $portalUrl = route('plugin.third-party-risk.external.portal.show', ['token' => $token]);
    $status = 'External collaboration link issued.';

    if (($validated['send_email_invitation'] ?? null) === '1') {
        $result = $delivery->send($vendor, $review, $link, $portalUrl, $principalId);
        $repository->recordExternalLinkDelivery($link['id'], $result['status'], $result['error']);

        $status = match ($result['status']) {
            'sent' => 'External collaboration link issued and email invitation sent.',
            'not-configured' => 'External collaboration link issued. Outbound email is not configured for this organization.',
            default => 'External collaboration link issued, but email delivery failed.',
        };
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))
        ->with('status', $status)
        ->with('third_party_risk_external_portal_url', $portalUrl)
        ->with('third_party_risk_external_portal_email', $link['contact_email']);
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.external.links.issue');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/external-links/{linkId}/revoke', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $linkId,
    ThirdPartyRiskRepository $repository,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    abort_if($repository->revokeExternalLink($reviewId, $linkId, $principalId) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'External collaboration link revoked.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.external.links.revoke');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $assignmentId,
    ThirdPartyRiskRepository $repository,
    FunctionalActorServiceInterface $actors,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $assignment = collect($actors->assignmentsFor(
        domainObjectType: 'vendor-review',
        domainObjectId: $reviewId,
        organizationId: $vendor['organization_id'],
        scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
    ))->first(fn ($candidate) => $candidate->id === $assignmentId && $candidate->assignmentType === 'owner');

    abort_if($assignment === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $actors->deactivateAssignment(
        assignmentId: $assignmentId,
        deactivatedByPrincipalId: $principalId,
    );

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Owner removed.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.owners.destroy');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/artifacts', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $repository,
    ArtifactServiceInterface $artifacts,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $artifacts->store(new ArtifactUploadData(
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

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Evidence attached.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.artifacts.store');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/questionnaire-items', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $repository,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'prompt' => ['required', 'string', 'max:255'],
        'response_type' => ['required', 'string', Rule::in(['yes-no', 'long-text', 'date', 'evidence-list'])],
        'response_status' => ['nullable', 'string', Rule::in(['draft', 'sent', 'submitted', 'under-review', 'accepted', 'needs-follow-up'])],
        'answer_text' => ['nullable', 'string', 'max:4000'],
        'follow_up_notes' => ['nullable', 'string', 'max:1000'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $repository->addQuestionnaireItem($reviewId, $validated);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Questionnaire item saved.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.questionnaire-items.store');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/questionnaire-items/{itemId}', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $itemId,
    ThirdPartyRiskRepository $repository,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);
    $item = $repository->findQuestionnaireItem($itemId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId || $item === null || $item['review_id'] !== $reviewId, 404);

    $validated = $request->validate([
        'prompt' => ['required', 'string', 'max:255'],
        'response_type' => ['required', 'string', Rule::in(['yes-no', 'long-text', 'date', 'evidence-list'])],
        'response_status' => ['required', 'string', Rule::in(['draft', 'sent', 'submitted', 'under-review', 'accepted', 'needs-follow-up'])],
        'answer_text' => ['nullable', 'string', 'max:4000'],
        'follow_up_notes' => ['nullable', 'string', 'max:1000'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $repository->updateQuestionnaireItem($reviewId, $itemId, $validated);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Questionnaire item updated.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.questionnaire-items.update');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/questionnaire-template/apply', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $repository,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'questionnaire_template_id' => ['required', 'string', 'max:120'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $created = $repository->applyQuestionnaireTemplateToReview($reviewId, $validated['questionnaire_template_id']);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', $created > 0 ? 'Questionnaire template applied.' : 'Questionnaire template was already applied.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.questionnaire-items.apply-template');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/transitions/{transitionKey}', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $transitionKey,
    ThirdPartyRiskRepository $repository,
    WorkflowServiceInterface $workflows,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $workflows->transition(
        workflowKey: 'plugin.third-party-risk.review-lifecycle',
        subjectType: 'vendor-review',
        subjectId: $reviewId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'request'),
            memberships: is_string($membershipId) && $membershipId !== ''
                ? [
                    new MembershipReference(
                        id: $membershipId,
                        principalId: $principalId,
                        organizationId: $vendor['organization_id'],
                    ),
                ]
                : [],
            organizationId: $vendor['organization_id'],
            scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
            membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        ),
    );

    $repository->syncVendorStatusForReview($reviewId, $transitionKey);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Transition applied.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.transition');
