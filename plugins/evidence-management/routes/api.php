<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PymeSec\Plugins\EvidenceManagement\EvidenceManagementRepository;

$apiContext = require base_path('routes/api_context.php');
extract($apiContext, EXTR_SKIP);

Route::get('/lookups/evidence-artifacts/options', function (
    Request $request,
    EvidenceManagementRepository $evidence,
) use ($apiSuccess) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);
    $scopeId = $request->input('scope_id');

    return $apiSuccess($evidence->artifactOptions(
        organizationId: $organizationId,
        scopeId: is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
    ));
})->defaults('_openapi', [
    'operation_id' => 'evidenceManagementListArtifactOptions',
    'tags' => ['lookups'],
    'tag_descriptions' => [
        'lookups' => 'Lookup feeds for relation selectors and governed options.',
    ],
    'summary' => 'List existing artifact options for evidence create and update forms',
    'responses' => [
        '200' => [
            'description' => 'Artifact options',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '422' => [
            'description' => 'Organization context is required',
        ],
    ],
])->middleware('core.permission:plugin.evidence-management.evidence.view');

Route::post('/evidence', function (
    Request $request,
    EvidenceManagementRepository $evidence,
) use ($apiPrincipalId, $apiSuccess) {
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
        'membership_id' => ['nullable', 'string', 'max:120'],
    ]);

    if (! $request->hasFile('artifact') && ! (is_string($validated['existing_artifact_id'] ?? null) && $validated['existing_artifact_id'] !== '')) {
        throw ValidationException::withMessages([
            'artifact' => __('Provide a file or select an existing artifact.'),
        ]);
    }

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    $membershipId = $validated['membership_id'] ?? $request->input('membership_id');

    $record = $evidence->create(
        data: $validated,
        artifactFile: $request->file('artifact'),
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
    );

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'evidenceManagementCreateEvidence',
    'tags' => ['evidence'],
    'tag_descriptions' => [
        'evidence' => 'Evidence register lifecycle and reminder API surface.',
    ],
    'summary' => 'Create one evidence record',
    'responses' => [
        '200' => [
            'description' => 'Evidence record created',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'title' => ['required', 'string', 'max:160'],
        'summary' => ['nullable', 'string', 'max:5000'],
        'evidence_kind' => ['required', 'string', 'in:document,workpaper,snapshot,report,ticket,log-export,statement,other'],
        'status' => ['required', 'string', 'in:draft,active,approved,expired,superseded'],
        'valid_from' => ['nullable', 'date'],
        'valid_until' => ['nullable', 'date'],
        'review_due_on' => ['nullable', 'date'],
        'validated_at' => ['nullable', 'date'],
        'validated_by_principal_id' => ['nullable', 'string', 'max:64'],
        'validation_notes' => ['nullable', 'string', 'max:5000'],
        'existing_artifact_id' => ['nullable', 'string', 'max:64'],
        'artifact' => ['nullable', 'string'],
        'membership_id' => ['nullable', 'string', 'max:120'],
        'link_targets' => ['nullable', 'array'],
        'link_targets.*' => ['string', 'max:160'],
    ],
    'lookup_fields' => [
        'validated_by_principal_id' => '/api/v1/lookups/principals/options',
        'existing_artifact_id' => '/api/v1/lookups/evidence-artifacts/options',
    ],
])->middleware('core.permission:plugin.evidence-management.evidence.manage');

Route::patch('/evidence/{evidenceId}', function (
    Request $request,
    string $evidenceId,
    EvidenceManagementRepository $evidence,
) use ($apiPrincipalId, $apiSuccess) {
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
        'membership_id' => ['nullable', 'string', 'max:120'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    $membershipId = $validated['membership_id'] ?? $request->input('membership_id');

    $record = $evidence->update(
        evidenceId: $evidenceId,
        data: $validated,
        artifactFile: $request->file('artifact'),
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
    );
    abort_if($record === null, 404);

    return $apiSuccess($record);
})->defaults('_openapi', [
    'operation_id' => 'evidenceManagementUpdateEvidence',
    'tags' => ['evidence'],
    'tag_descriptions' => [
        'evidence' => 'Evidence register lifecycle and reminder API surface.',
    ],
    'summary' => 'Update one evidence record',
    'responses' => [
        '200' => [
            'description' => 'Evidence record updated',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Evidence record not found',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'title' => ['required', 'string', 'max:160'],
        'summary' => ['nullable', 'string', 'max:5000'],
        'evidence_kind' => ['required', 'string', 'in:document,workpaper,snapshot,report,ticket,log-export,statement,other'],
        'status' => ['required', 'string', 'in:draft,active,approved,expired,superseded'],
        'valid_from' => ['nullable', 'date'],
        'valid_until' => ['nullable', 'date'],
        'review_due_on' => ['nullable', 'date'],
        'validated_at' => ['nullable', 'date'],
        'validated_by_principal_id' => ['nullable', 'string', 'max:64'],
        'validation_notes' => ['nullable', 'string', 'max:5000'],
        'existing_artifact_id' => ['nullable', 'string', 'max:64'],
        'artifact' => ['nullable', 'string'],
        'membership_id' => ['nullable', 'string', 'max:120'],
        'link_targets' => ['nullable', 'array'],
        'link_targets.*' => ['string', 'max:160'],
    ],
    'lookup_fields' => [
        'validated_by_principal_id' => '/api/v1/lookups/principals/options',
        'existing_artifact_id' => '/api/v1/lookups/evidence-artifacts/options',
    ],
])->middleware('core.permission:plugin.evidence-management.evidence.manage');

Route::post('/evidence/promote/{artifactId}', function (
    Request $request,
    string $artifactId,
    EvidenceManagementRepository $evidence,
) use ($apiPrincipalId, $apiSuccess) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);
    $scopeId = $request->input('scope_id');
    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    $membershipId = $request->input('membership_id');

    $promotion = $evidence->promoteArtifact(
        artifactId: $artifactId,
        organizationId: $organizationId,
        scopeId: is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
    );
    abort_if($promotion === null, 404);

    return $apiSuccess($promotion);
})->defaults('_openapi', [
    'operation_id' => 'evidenceManagementPromoteArtifact',
    'tags' => ['evidence'],
    'tag_descriptions' => [
        'evidence' => 'Evidence register lifecycle and reminder API surface.',
    ],
    'summary' => 'Promote one artifact into an evidence record',
    'responses' => [
        '200' => [
            'description' => 'Artifact promoted',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Artifact not found or not accessible',
        ],
        '422' => [
            'description' => 'Organization context is required',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'membership_id' => ['nullable', 'string', 'max:120'],
    ],
])->middleware('core.permission:plugin.evidence-management.evidence.manage');

Route::post('/evidence/{evidenceId}/reminders/{type}', function (
    Request $request,
    string $evidenceId,
    string $type,
    EvidenceManagementRepository $evidence,
) use ($apiPrincipalId, $apiSuccess) {
    abort_unless(in_array($type, ['review-due', 'expiry-soon'], true), 404);

    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'membership_id' => ['nullable', 'string', 'max:120'],
    ]);

    $record = $evidence->find($evidenceId);
    abort_if($record === null || ($record['organization_id'] ?? null) !== (string) $validated['organization_id'], 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $queued = $evidence->queueReminder(
        evidenceId: $evidenceId,
        type: $type,
        principalId: $principalId,
        membershipId: is_string($validated['membership_id'] ?? null) && $validated['membership_id'] !== ''
            ? $validated['membership_id']
            : null,
    );

    abort_if(! $queued, 422);

    return $apiSuccess([
        'evidence_id' => $evidenceId,
        'type' => $type,
        'queued' => true,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'evidenceManagementQueueReminder',
    'tags' => ['evidence'],
    'tag_descriptions' => [
        'evidence' => 'Evidence register lifecycle and reminder API surface.',
    ],
    'summary' => 'Queue one reminder for an evidence record',
    'responses' => [
        '200' => [
            'description' => 'Reminder queued',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Evidence record not found',
        ],
        '422' => [
            'description' => 'Reminder could not be queued',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'membership_id' => ['nullable', 'string', 'max:120'],
    ],
])->middleware('core.permission:plugin.evidence-management.evidence.manage');
