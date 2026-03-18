<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
            'artifact' => 'Provide a file or select an existing artifact.',
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
    ]))->with('status', 'Evidence saved.');
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
    ]))->with('status', 'Evidence updated.');
})->middleware('core.permission:plugin.evidence-management.evidence.manage')->name('plugin.evidence-management.update');
