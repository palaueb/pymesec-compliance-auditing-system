<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Plugins\AssessmentsAudits\AssessmentsAuditsRepository;
use PymeSec\Plugins\FindingsRemediation\FindingsRemediationRepository;

Route::get('/plugins/assessments', function (Request $request, AssessmentsAuditsRepository $repository) {
    return response()->json([
        'plugin' => 'assessments-audits',
        'assessments' => $repository->all(
            (string) $request->query('organization_id', 'org-a'),
            $request->query('scope_id'),
        ),
    ]);
})->middleware('core.permission:plugin.assessments-audits.assessments.view')->name('plugin.assessments-audits.index');

Route::get('/plugins/assessments/{assessmentId}/report', function (
    Request $request,
    string $assessmentId,
    AssessmentsAuditsRepository $repository
) {
    $report = $repository->report($assessmentId);

    abort_if($report === null, 404);

    $assessment = $report['assessment'];
    $summary = $report['summary'];
    $reviews = $report['reviews'];
    $lines = [
        '# '.$assessment['title'],
        '',
        $assessment['summary'],
        '',
        'Organization: '.$assessment['organization_id'],
        'Scope: '.($assessment['scope_id'] !== '' ? $assessment['scope_id'] : 'organization-wide'),
        'Framework: '.($assessment['framework_id'] !== '' ? $assessment['framework_id'] : 'any'),
        'Status: '.$assessment['status'],
        'Dates: '.$assessment['starts_on'].' -> '.$assessment['ends_on'],
        '',
        'Summary',
        '- Pass: '.$summary['pass'],
        '- Partial: '.$summary['partial'],
        '- Fail: '.$summary['fail'],
        '- Not tested: '.$summary['not-tested'],
        '- Not applicable: '.$summary['not-applicable'],
        '- Linked findings: '.$summary['linked_findings'],
        '- Workpapers: '.$summary['artifacts'],
        '',
        'Checklist',
    ];

    foreach ($reviews as $review) {
        $lines[] = sprintf(
            '- %s [%s]',
            $review['control_name'],
            strtoupper((string) $review['result']),
        );

        if ($review['conclusion'] !== '') {
            $lines[] = '  Conclusion: '.$review['conclusion'];
        }

        if ($review['test_notes'] !== '') {
            $lines[] = '  Test notes: '.$review['test_notes'];
        }

        if (is_array($review['linked_finding'] ?? null)) {
            $lines[] = '  Finding: '.$review['linked_finding']['title'].' ('.$review['linked_finding']['severity'].')';
        }

        if (($review['artifacts'] ?? []) !== []) {
            $lines[] = '  Workpapers: '.implode(', ', array_map(
                static fn (array $artifact): string => $artifact['original_filename'],
                $review['artifacts'],
            ));
        }
    }

    return response(
        implode("\n", $lines),
        200,
        [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s-summary.md"', $assessmentId),
        ],
    );
})->middleware('core.permission:plugin.assessments-audits.assessments.view')->name('plugin.assessments-audits.report');

Route::post('/plugins/assessments', function (Request $request, AssessmentsAuditsRepository $repository) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'framework_id' => ['nullable', 'string', 'max:64'],
        'title' => ['required', 'string', 'max:160'],
        'summary' => ['required', 'string', 'max:500'],
        'starts_on' => ['required', 'date'],
        'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        'status' => ['nullable', 'in:draft,active,closed'],
        'control_ids' => ['nullable', 'array'],
        'control_ids.*' => ['string', 'max:64'],
    ]);

    $assessment = $repository->create($validated);
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.assessments-audits.root',
        'principal_id' => $principalId,
        'organization_id' => $assessment['organization_id'],
        'assessment_id' => $assessment['id'],
        'scope_id' => $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.assessments-audits.assessments.manage')->name('plugin.assessments-audits.store');

Route::post('/plugins/assessments/{assessmentId}', function (
    Request $request,
    string $assessmentId,
    AssessmentsAuditsRepository $repository
) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'framework_id' => ['nullable', 'string', 'max:64'],
        'title' => ['required', 'string', 'max:160'],
        'summary' => ['required', 'string', 'max:500'],
        'starts_on' => ['required', 'date'],
        'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        'status' => ['required', 'in:draft,active,closed'],
        'control_ids' => ['nullable', 'array'],
        'control_ids.*' => ['string', 'max:64'],
    ]);

    $assessment = $repository->update($assessmentId, $validated);

    abort_if($assessment === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.assessments-audits.root',
        'principal_id' => $principalId,
        'organization_id' => $assessment['organization_id'],
        'assessment_id' => $assessment['id'],
        'scope_id' => $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.assessments-audits.assessments.manage')->name('plugin.assessments-audits.update');

Route::post('/plugins/assessments/{assessmentId}/reviews/{controlId}', function (
    Request $request,
    string $assessmentId,
    string $controlId,
    AssessmentsAuditsRepository $repository
) {
    $validated = $request->validate([
        'result' => ['required', 'in:not-tested,pass,partial,fail,not-applicable'],
        'test_notes' => ['nullable', 'string', 'max:5000'],
        'conclusion' => ['nullable', 'string', 'max:5000'],
        'reviewed_on' => ['nullable', 'date'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $assessment = $repository->upsertReview($assessmentId, $controlId, $validated, $principalId);

    abort_if($assessment === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.assessments-audits.root',
        'principal_id' => $principalId,
        'organization_id' => $request->input('organization_id', 'org-a'),
        'assessment_id' => $assessmentId,
        'scope_id' => $request->input('scope_id'),
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.assessments-audits.assessments.manage')->name('plugin.assessments-audits.reviews.update');

Route::post('/plugins/assessments/{assessmentId}/reviews/{controlId}/artifacts', function (
    Request $request,
    string $assessmentId,
    string $controlId,
    AssessmentsAuditsRepository $repository,
    ArtifactServiceInterface $artifacts
) {
    $review = $repository->review($assessmentId, $controlId);

    abort_if($review === null, 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'assessments-audits',
        subjectType: 'assessment-review',
        subjectId: $review['id'],
        artifactType: (string) ($validated['artifact_type'] ?? 'workpaper'),
        label: (string) ($validated['label'] ?? 'Assessment workpaper'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: (string) $request->input('organization_id', 'org-a'),
        scopeId: ($request->input('scope_id') ?: null),
        metadata: [
            'plugin' => 'assessments-audits',
            'assessment_id' => $assessmentId,
            'control_id' => $controlId,
            'result' => $review['result'],
        ],
    ));

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.assessments-audits.root',
        'principal_id' => $principalId,
        'organization_id' => $request->input('organization_id', 'org-a'),
        'assessment_id' => $assessmentId,
        'scope_id' => $request->input('scope_id'),
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.assessments-audits.assessments.manage')->name('plugin.assessments-audits.reviews.artifacts.store');

Route::post('/plugins/assessments/{assessmentId}/reviews/{controlId}/findings', function (
    Request $request,
    string $assessmentId,
    string $controlId,
    AssessmentsAuditsRepository $repository,
    FindingsRemediationRepository $findings
) {
    $review = $repository->review($assessmentId, $controlId);

    abort_if($review === null, 404);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'severity' => ['required', 'in:low,medium,high,critical'],
        'description' => ['required', 'string', 'max:5000'],
        'due_on' => ['nullable', 'date'],
    ]);

    $finding = $findings->createFinding([
        'organization_id' => (string) $request->input('organization_id', 'org-a'),
        'scope_id' => is_string($request->input('scope_id')) && $request->input('scope_id') !== '' ? (string) $request->input('scope_id') : null,
        'title' => (string) $validated['title'],
        'severity' => (string) $validated['severity'],
        'description' => (string) $validated['description'],
        'linked_control_id' => $controlId,
        'linked_risk_id' => null,
        'due_on' => is_string($validated['due_on'] ?? null) ? $validated['due_on'] : null,
    ]);

    $repository->linkFinding($assessmentId, $controlId, $finding['id']);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.assessments-audits.root',
        'principal_id' => $principalId,
        'organization_id' => $request->input('organization_id', 'org-a'),
        'assessment_id' => $assessmentId,
        'scope_id' => $request->input('scope_id'),
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.assessments-audits.assessments.manage')->name('plugin.assessments-audits.reviews.findings.store');
