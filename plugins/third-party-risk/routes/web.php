<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Collaboration\Contracts\CollaborationEngineInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Questionnaires\Contracts\QuestionnaireEngineInterface;
use PymeSec\Core\Questionnaires\Contracts\QuestionnaireStoreInterface;
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

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/brokered-requests', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $repository,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'contact_name' => ['required', 'string', 'max:120'],
        'contact_email' => ['nullable', 'email', 'max:160'],
        'collection_channel' => ['required', Rule::in(['email', 'meeting', 'call', 'uploaded-docs', 'broker-note'])],
        'instructions' => ['nullable', 'string', 'max:2000'],
        'broker_principal_id' => ['nullable', 'string', 'max:64'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $repository->issueBrokeredRequestForReview($reviewId, [
        ...$validated,
        'issued_by_principal_id' => $principalId,
        'broker_principal_id' => is_string($validated['broker_principal_id'] ?? null) && $validated['broker_principal_id'] !== ''
            ? $validated['broker_principal_id']
            : $principalId,
    ]);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Brokered collection request created.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.brokered-requests.issue');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/collaboration/comments', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $repository,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'body' => ['required', 'string', 'max:4000'],
        'mentioned_actor_ids' => ['nullable', 'array'],
        'mentioned_actor_ids.*' => ['string', 'max:64', 'exists:functional_actors,id'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    abort_if($repository->addCommentToReview($reviewId, [
        'author_principal_id' => $principalId,
        'body' => $validated['body'],
        'mentioned_actor_ids' => $validated['mentioned_actor_ids'] ?? [],
    ]) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Comment added.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.collaboration.comments.store');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/collaboration/drafts', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $repository,
    CollaborationEngineInterface $collaboration,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

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

    if (($validated['draft_type'] ?? 'comment') === 'comment') {
        abort_if(trim((string) ($validated['body'] ?? '')) === '', 422, 'Comment drafts require body text.');
    } else {
        abort_if(trim((string) ($validated['title'] ?? '')) === '', 422, 'Follow-up drafts require a title.');
    }

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    abort_if($repository->createCollaborationDraftForReview($reviewId, [
        ...$validated,
        'edited_by_principal_id' => $principalId,
    ]) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Shared draft saved.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.collaboration.drafts.store');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/collaboration/drafts/{draftId}', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $draftId,
    ThirdPartyRiskRepository $repository,
    CollaborationEngineInterface $collaboration,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

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

    if (($validated['draft_type'] ?? 'comment') === 'comment') {
        abort_if(trim((string) ($validated['body'] ?? '')) === '', 422, 'Comment drafts require body text.');
    } else {
        abort_if(trim((string) ($validated['title'] ?? '')) === '', 422, 'Follow-up drafts require a title.');
    }

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    abort_if($repository->updateCollaborationDraftForReview(
        reviewId: $reviewId,
        draftId: $draftId,
        data: [
            ...$validated,
            'edited_by_principal_id' => $principalId,
        ],
        principalId: $principalId,
    ) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Shared draft updated.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.collaboration.drafts.update');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/collaboration/drafts/{draftId}/promote-comment', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $draftId,
    ThirdPartyRiskRepository $repository,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    abort_if($repository->promoteCollaborationDraftToComment($reviewId, $draftId, $principalId) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Shared draft promoted to comment.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.collaboration.drafts.promote-comment');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/collaboration/drafts/{draftId}/promote-request', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $draftId,
    ThirdPartyRiskRepository $repository,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    abort_if($repository->promoteCollaborationDraftToRequest($reviewId, $draftId, $principalId) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Shared draft promoted to follow-up request.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.collaboration.drafts.promote-request');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/collaboration/requests', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    ThirdPartyRiskRepository $repository,
    CollaborationEngineInterface $collaboration,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

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

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    abort_if($repository->createCollaborationRequestForReview($reviewId, [
        ...$validated,
        'requested_by_principal_id' => $principalId,
    ]) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Follow-up request created.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.collaboration.requests.store');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/collaboration/requests/{requestId}', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $requestId,
    ThirdPartyRiskRepository $repository,
    CollaborationEngineInterface $collaboration,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

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

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    abort_if($repository->updateCollaborationRequestForReview(
        reviewId: $reviewId,
        requestId: $requestId,
        data: $validated,
        principalId: $principalId,
    ) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Follow-up request updated.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.collaboration.requests.update');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/brokered-requests/{requestId}', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $requestId,
    ThirdPartyRiskRepository $repository,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'collection_status' => ['required', Rule::in(['queued', 'in-progress', 'submitted', 'completed', 'cancelled'])],
        'broker_notes' => ['nullable', 'string', 'max:2000'],
        'broker_principal_id' => ['nullable', 'string', 'max:64'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    abort_if($repository->updateBrokeredRequestForReview(
        reviewId: $reviewId,
        requestId: $requestId,
        data: [
            ...$validated,
            'broker_principal_id' => is_string($validated['broker_principal_id'] ?? null) && $validated['broker_principal_id'] !== ''
                ? $validated['broker_principal_id']
                : $principalId,
        ],
        principalId: $principalId,
    ) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Brokered collection request updated.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.brokered-requests.update');

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
    QuestionnaireEngineInterface $questionnaires,
    QuestionnaireStoreInterface $questionnaireStore,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

    $validated = $request->validate([
        'section_title' => ['nullable', 'string', 'max:120'],
        'prompt' => ['required', 'string', 'max:255'],
        'response_type' => ['required', 'string', Rule::in($questionnaires->responseTypeKeys())],
        'attachment_mode' => ['nullable', 'string', Rule::in($questionnaires->attachmentModeKeys())],
        'attachment_upload_profile' => ['nullable', 'string', Rule::in(['documents_only', 'documents_and_spreadsheets', 'images_only', 'review_artifacts'])],
        'promote_attachments_to_evidence' => ['nullable', 'in:1'],
        'response_status' => ['nullable', 'string', Rule::in($questionnaires->responseStatusKeys())],
        'answer_text' => ['nullable', 'string', 'max:4000'],
        'follow_up_notes' => ['nullable', 'string', 'max:1000'],
        'save_to_answer_library' => ['nullable', 'in:1'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $saved = $repository->addQuestionnaireItem($reviewId, $validated);

    if (($validated['save_to_answer_library'] ?? null) === '1' && ($saved['answer_text'] ?? '') !== '') {
        $questionnaireStore->saveAnswerLibraryEntry(
            organizationId: $vendor['organization_id'],
            scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            data: [
                'prompt' => $saved['prompt'],
                'response_type' => $saved['response_type'],
                'answer_text' => $saved['answer_text'],
                'notes' => $saved['follow_up_notes'],
            ],
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
    ]))->with('status', 'Questionnaire item saved.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.questionnaire-items.store');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/questionnaire-items/{itemId}', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $itemId,
    ThirdPartyRiskRepository $repository,
    QuestionnaireEngineInterface $questionnaires,
    QuestionnaireStoreInterface $questionnaireStore,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);
    $item = $repository->findQuestionnaireItem($itemId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId || $item === null || $item['review_id'] !== $reviewId, 404);

    $validated = $request->validate([
        'section_title' => ['nullable', 'string', 'max:120'],
        'prompt' => ['required', 'string', 'max:255'],
        'response_type' => ['required', 'string', Rule::in($questionnaires->responseTypeKeys())],
        'attachment_mode' => ['nullable', 'string', Rule::in($questionnaires->attachmentModeKeys())],
        'attachment_upload_profile' => ['nullable', 'string', Rule::in(['documents_only', 'documents_and_spreadsheets', 'images_only', 'review_artifacts'])],
        'promote_attachments_to_evidence' => ['nullable', 'in:1'],
        'response_status' => ['required', 'string', Rule::in($questionnaires->responseStatusKeys())],
        'answer_text' => ['nullable', 'string', 'max:4000'],
        'follow_up_notes' => ['nullable', 'string', 'max:1000'],
        'save_to_answer_library' => ['nullable', 'in:1'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $saved = $repository->updateQuestionnaireItem($reviewId, $itemId, $validated);

    if (($validated['save_to_answer_library'] ?? null) === '1' && is_array($saved) && ($saved['answer_text'] ?? '') !== '') {
        $questionnaireStore->saveAnswerLibraryEntry(
            organizationId: $vendor['organization_id'],
            scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            data: [
                'prompt' => $saved['prompt'],
                'response_type' => $saved['response_type'],
                'answer_text' => $saved['answer_text'],
                'notes' => $saved['follow_up_notes'],
            ],
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
    ]))->with('status', 'Questionnaire item updated.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.questionnaire-items.update');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/questionnaire-items/{itemId}/artifacts', function (
    Request $request,
    string $vendorId,
    string $reviewId,
    string $itemId,
    ThirdPartyRiskRepository $repository,
    ArtifactServiceInterface $artifacts,
    AuditTrailInterface $audit,
) {
    $vendor = $repository->find($vendorId);
    $review = $repository->findReview($reviewId);
    $item = $repository->findQuestionnaireItem($itemId);

    abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId || $item === null || $item['review_id'] !== $reviewId || ($item['attachment_mode'] ?? 'none') === 'none', 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

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

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Questionnaire attachment uploaded.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.questionnaire-items.artifacts.store');

Route::post('/plugins/vendors/{vendorId}/reviews/{reviewId}/questionnaire-items/{itemId}/review', function (
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
        'response_status' => ['required', 'string', Rule::in(['under-review', 'accepted', 'needs-follow-up'])],
        'review_notes' => ['nullable', 'string', 'max:2000'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $repository->reviewQuestionnaireItem(
        reviewId: $reviewId,
        itemId: $itemId,
        responseStatus: $validated['response_status'],
        reviewNotes: $validated['review_notes'] ?? null,
        reviewedByPrincipalId: $principalId !== '' ? $principalId : null,
    );

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.third-party-risk.root',
        'vendor_id' => $vendor['id'],
        'principal_id' => $principalId,
        'organization_id' => $vendor['organization_id'],
        'scope_id' => $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Questionnaire review updated.');
})->middleware('core.permission:plugin.third-party-risk.vendors.manage')->name('plugin.third-party-risk.questionnaire-items.review');

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
