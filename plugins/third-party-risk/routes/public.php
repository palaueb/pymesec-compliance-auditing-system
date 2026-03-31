<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Plugins\ThirdPartyRisk\ThirdPartyRiskRepository;

Route::get('/external/vendor-review/{token}', function (string $token, ThirdPartyRiskRepository $repository) {
    $link = $repository->resolveExternalLinkByToken($token);
    abort_if($link === null, 404);

    $review = $repository->findReview($link['review_id']);
    abort_if($review === null, 404);

    $vendor = $repository->find($review['vendor_id']);
    abort_if($vendor === null, 404);

    $repository->touchExternalLinkAccess($link['id']);

    return response(view()->file(base_path('../plugins/third-party-risk/resources/views/external-review.blade.php'), [
        'token' => $token,
        'link' => $link,
        'vendor' => $vendor,
        'review' => $review,
        'questionnaire_items' => $repository->questionnaireItemsForReview($review['id']),
    ]));
})->name('plugin.third-party-risk.external.portal.show');

Route::post('/external/vendor-review/{token}/questionnaire-items/{itemId}', function (
    Request $request,
    string $token,
    string $itemId,
    ThirdPartyRiskRepository $repository,
    AuditTrailInterface $audit,
) {
    $link = $repository->resolveExternalLinkByToken($token);
    abort_if($link === null || $link['can_answer_questionnaire'] !== '1', 404);

    $review = $repository->findReview($link['review_id']);
    $item = $repository->findQuestionnaireItem($itemId);

    abort_if($review === null || $item === null || $item['review_id'] !== $review['id'], 404);

    $rule = match ($item['response_type']) {
        'yes-no' => ['required', 'string', Rule::in(['yes', 'no', 'not-applicable'])],
        'date' => ['required', 'date'],
        default => ['required', 'string', 'max:4000'],
    };

    $validated = $request->validate([
        'answer_text' => $rule,
    ]);

    $updated = $repository->submitExternalQuestionnaireAnswer($review['id'], $itemId, (string) $validated['answer_text']);
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

    return redirect()
        ->route('plugin.third-party-risk.external.portal.show', ['token' => $token])
        ->with('status', 'Answer submitted.');
})->name('plugin.third-party-risk.external.questionnaire-items.update');

Route::post('/external/vendor-review/{token}/artifacts', function (
    Request $request,
    string $token,
    ThirdPartyRiskRepository $repository,
    ArtifactServiceInterface $artifacts,
    AuditTrailInterface $audit,
) {
    $link = $repository->resolveExternalLinkByToken($token);
    abort_if($link === null || $link['can_upload_artifacts'] !== '1', 404);

    $review = $repository->findReview($link['review_id']);
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

    return redirect()
        ->route('plugin.third-party-risk.external.portal.show', ['token' => $token])
        ->with('status', 'Evidence uploaded.');
})->name('plugin.third-party-risk.external.artifacts.store');
