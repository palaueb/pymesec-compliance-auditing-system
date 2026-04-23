<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PymeSec\Plugins\EvidenceManagement\EvidenceManagementRepository;

Route::get('/plugins/evidence', function (Request $request, EvidenceManagementRepository $repository) {
    return response()->json([
        'plugin' => 'evidence-management',
        'evidence' => $repository->all(
            (string) $request->query('organization_id', 'org-a'),
            is_string($request->query('scope_id')) ? $request->query('scope_id') : null,
        ),
    ]);
})->middleware('core.permission:plugin.evidence-management.evidence.view')->name('plugin.evidence-management.index');

Route::post('/plugins/evidence', function (Request $request, EvidenceManagementRepository $repository) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'title' => ['required', 'string', 'max:160'],
        'summary' => ['nullable', 'string', 'max:5000'],
        'evidence_kind' => ['required', 'in:document,workpaper,snapshot,report,ticket,log-export,statement,other'],
        'status' => ['required', 'in:draft,active,approved,expired,superseded'],
        'valid_from' => ['nullable', 'date'],
        'valid_until' => ['nullable', 'date'],
        'review_due_on' => ['nullable', 'date'],
        'validated_at' => ['nullable', 'date'],
        'validated_by_principal_id' => ['nullable', 'string', 'max:64'],
        'validation_notes' => ['nullable', 'string', 'max:5000'],
        'existing_artifact_id' => ['nullable', 'string', 'max:64'],
        'artifact' => ['nullable', 'file', 'max:10240'],
        'link_targets' => ['nullable', 'array'],
        'link_targets.*' => ['string', 'max:160'],
    ]);

    if (! $request->hasFile('artifact') && ! (is_string($validated['existing_artifact_id'] ?? null) && $validated['existing_artifact_id'] !== '')) {
        throw ValidationException::withMessages([
            'artifact' => __('Provide a file or select an existing artifact.'),
        ]);
    }

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $evidence = $repository->create(
        $validated,
        $request->file('artifact'),
        $principalId,
        is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
    );

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.evidence-management.root',
        'principal_id' => $principalId,
        'organization_id' => (string) $validated['organization_id'],
        'scope_id' => is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
        'evidence_id' => $evidence['id'] ?? null,
    ]))->with('status', __('Evidence saved.'));
})->middleware('core.permission:plugin.evidence-management.evidence.manage')->name('plugin.evidence-management.store');

Route::post('/plugins/evidence/{evidenceId}', function (
    Request $request,
    string $evidenceId,
    EvidenceManagementRepository $repository
) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'title' => ['required', 'string', 'max:160'],
        'summary' => ['nullable', 'string', 'max:5000'],
        'evidence_kind' => ['required', 'in:document,workpaper,snapshot,report,ticket,log-export,statement,other'],
        'status' => ['required', 'in:draft,active,approved,expired,superseded'],
        'valid_from' => ['nullable', 'date'],
        'valid_until' => ['nullable', 'date'],
        'review_due_on' => ['nullable', 'date'],
        'validated_at' => ['nullable', 'date'],
        'validated_by_principal_id' => ['nullable', 'string', 'max:64'],
        'validation_notes' => ['nullable', 'string', 'max:5000'],
        'existing_artifact_id' => ['nullable', 'string', 'max:64'],
        'artifact' => ['nullable', 'file', 'max:10240'],
        'link_targets' => ['nullable', 'array'],
        'link_targets.*' => ['string', 'max:160'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $evidence = $repository->update(
        $evidenceId,
        $validated,
        $request->file('artifact'),
        $principalId,
        is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
    );

    abort_if($evidence === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.evidence-management.root',
        'principal_id' => $principalId,
        'organization_id' => (string) $validated['organization_id'],
        'scope_id' => is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
        'evidence_id' => $evidenceId,
    ]))->with('status', __('Evidence updated.'));
})->middleware('core.permission:plugin.evidence-management.evidence.manage')->name('plugin.evidence-management.update');

Route::post('/plugins/evidence/promote/{artifactId}', function (
    Request $request,
    string $artifactId,
    EvidenceManagementRepository $repository
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = is_string($request->input('scope_id')) && $request->input('scope_id') !== ''
        ? (string) $request->input('scope_id')
        : null;

    $promotion = $repository->promoteArtifact(
        $artifactId,
        $organizationId,
        $scopeId,
        $principalId,
        is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
    );

    abort_if($promotion === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.evidence-management.root',
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => $scopeId,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
        'evidence_id' => $promotion['record']['id'] ?? null,
    ]))->with('status', $promotion['created'] ? __('Evidence created from artifact.') : __('Artifact already promoted to evidence.'));
})->middleware('core.permission:plugin.evidence-management.evidence.manage')->name('plugin.evidence-management.promote');

Route::get('/plugins/evidence/{evidenceId}/download', function (
    Request $request,
    string $evidenceId,
    EvidenceManagementRepository $repository
) {
    $evidence = $repository->find($evidenceId);
    abort_if($evidence === null, 404);
    abort_if(($evidence['organization_id'] ?? null) !== (string) $request->query('organization_id', ''), 404);

    $artifact = $evidence['artifact'] ?? null;
    abort_if(! is_array($artifact), 404);

    $disk = (string) ($artifact['disk'] ?? '');
    $storagePath = (string) ($artifact['storage_path'] ?? '');
    abort_if($disk === '' || $storagePath === '' || ! Storage::disk($disk)->exists($storagePath), 404);

    return Storage::disk($disk)->download($storagePath, (string) ($artifact['original_filename'] ?? 'evidence.bin'));
})->middleware('core.permission:plugin.evidence-management.evidence.view')->name('plugin.evidence-management.download');

Route::get('/plugins/evidence/{evidenceId}/preview', function (
    Request $request,
    string $evidenceId,
    EvidenceManagementRepository $repository
) {
    $evidence = $repository->find($evidenceId);
    abort_if($evidence === null, 404);
    abort_if(($evidence['organization_id'] ?? null) !== (string) $request->query('organization_id', ''), 404);

    $artifact = $evidence['artifact'] ?? null;
    abort_if(! is_array($artifact), 404);

    $disk = (string) ($artifact['disk'] ?? '');
    $storagePath = (string) ($artifact['storage_path'] ?? '');
    $mediaType = (string) ($artifact['media_type'] ?? 'application/octet-stream');
    $previewable = str_starts_with($mediaType, 'text/')
        || str_starts_with($mediaType, 'image/')
        || $mediaType === 'application/pdf'
        || $mediaType === 'application/json';

    abort_if($disk === '' || $storagePath === '' || ! $previewable || ! Storage::disk($disk)->exists($storagePath), 404);

    $contents = Storage::disk($disk)->get($storagePath);

    return response($contents, 200, [
        'Content-Type' => $mediaType,
        'Content-Disposition' => 'inline; filename="'.addslashes((string) ($artifact['original_filename'] ?? 'preview')).'"',
    ]);
})->middleware('core.permission:plugin.evidence-management.evidence.view')->name('plugin.evidence-management.preview');

Route::post('/plugins/evidence/{evidenceId}/reminders/{type}', function (
    Request $request,
    string $evidenceId,
    string $type,
    EvidenceManagementRepository $repository
) {
    abort_unless(in_array($type, ['review-due', 'expiry-soon'], true), 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $requestedOrganizationId = (string) $request->input('organization_id', 'org-a');
    $evidence = $repository->find($evidenceId);
    abort_if($evidence === null || ($evidence['organization_id'] ?? null) !== $requestedOrganizationId, 404);
    $queued = $repository->queueReminder(
        $evidenceId,
        $type,
        $principalId,
        is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
    );

    abort_if(! $queued, 422);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.evidence-management.root',
        'principal_id' => $principalId,
        'organization_id' => $requestedOrganizationId,
        'scope_id' => $request->input('scope_id'),
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
        'evidence_id' => $evidenceId,
    ]))->with('status', __('Reminder queued.'));
})->middleware('core.permission:plugin.evidence-management.evidence.manage')->name('plugin.evidence-management.reminders.queue');
