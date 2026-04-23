<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Plugins\AssessmentsAudits\AssessmentReferenceData;
use PymeSec\Plugins\AssessmentsAudits\AssessmentsAuditsRepository;
use PymeSec\Plugins\FindingsRemediation\FindingsRemediationRepository;

Route::get('/plugins/assessments', function (Request $request, AssessmentsAuditsRepository $repository, ObjectAccessService $objectAccess) {
    $organizationId = (string) $request->query('organization_id', 'org-a');

    return response()->json([
        'plugin' => 'assessments-audits',
        'assessments' => $objectAccess->filterRecords(
            $repository->all($organizationId, $request->query('scope_id')),
            'id',
            is_string($request->query('principal_id')) ? (string) $request->query('principal_id') : null,
            $organizationId,
            is_string($request->query('scope_id')) ? (string) $request->query('scope_id') : null,
            'assessment',
        ),
    ]);
})->middleware('core.permission:plugin.assessments-audits.assessments.view')->name('plugin.assessments-audits.index');

Route::get('/plugins/assessments/{assessmentId}/report', function (
    Request $request,
    string $assessmentId,
    AssessmentsAuditsRepository $repository,
    ObjectAccessService $objectAccess,
) {
    abort_unless($objectAccess->canAccessObject(
        principalId: is_string($request->query('principal_id')) ? (string) $request->query('principal_id') : null,
        organizationId: (string) $request->query('organization_id', 'org-a'),
        scopeId: is_string($request->query('scope_id')) ? (string) $request->query('scope_id') : null,
        domainObjectType: 'assessment',
        domainObjectId: $assessmentId,
    ), 403);

    $report = $repository->report($assessmentId);

    abort_if($report === null, 404);

    $assessment = $report['assessment'];
    $summary = $report['summary'];
    $reviews = $report['reviews'];
    $frameworkBreakdown = $report['framework_breakdown'] ?? [];
    $format = (string) $request->query('format', 'md');

    if ($format === 'json') {
        return response()->json($report, 200, [
            'Content-Disposition' => sprintf('attachment; filename="%s-bundle.json"', $assessmentId),
        ]);
    }

    if ($format === 'csv') {
        $rows = [[
            'assessment_id',
            'assessment_title',
            'status',
            'scope',
            'framework',
            'control_id',
            'control_name',
            'result',
            'reviewed_on',
            'reviewer_principal_id',
            'linked_finding_id',
            'artifact_count',
            'conclusion',
        ]];

        foreach ($reviews as $review) {
            $rows[] = [
                $assessment['id'],
                $assessment['title'],
                $assessment['status'],
                $assessment['scope_id'] !== '' ? $assessment['scope_id'] : 'organization-wide',
                $assessment['framework_id'] !== '' ? $assessment['framework_id'] : 'any',
                $review['control_id'],
                $review['control_name'],
                $review['result'],
                $review['reviewed_on'],
                $review['reviewer_principal_id'],
                $review['linked_finding_id'],
                (string) count($review['artifacts']),
                preg_replace('/\s+/', ' ', $review['conclusion']),
            ];
        }

        $stream = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv ?: '', 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s-summary.csv"', $assessmentId),
        ]);
    }

    $lines = [
        '# '.$assessment['title'],
        '',
        $assessment['summary'],
        '',
        __('Organization').': '.$assessment['organization_id'],
        __('Scope').': '.($assessment['scope_id'] !== '' ? $assessment['scope_id'] : __('Organization-wide')),
        __('Framework').': '.($assessment['framework_id'] !== '' ? $assessment['framework_id'] : __('Any framework')),
        __('Status').': '.$assessment['status'],
        __('Dates').': '.$assessment['starts_on'].' -> '.$assessment['ends_on'],
        __('Signed off on').': '.($assessment['signed_off_on'] !== '' ? $assessment['signed_off_on'] : __('Not signed off yet')),
        __('Signed off by').': '.($assessment['signed_off_by_principal_id'] !== '' ? $assessment['signed_off_by_principal_id'] : __('n/a')),
        __('Closed on').': '.($assessment['closed_on'] !== '' ? $assessment['closed_on'] : __('Not closed yet')),
        __('Closed by').': '.($assessment['closed_by_principal_id'] !== '' ? $assessment['closed_by_principal_id'] : __('n/a')),
        '',
        __('Summary'),
        '- '.__('Pass').': '.$summary['pass'],
        '- '.__('Partial').': '.$summary['partial'],
        '- '.__('Fail').': '.$summary['fail'],
        '- '.__('Not tested').': '.$summary['not-tested'],
        '- '.__('Not applicable').': '.$summary['not-applicable'],
        '- '.__('Linked findings').': '.$summary['linked_findings'],
        '- '.__('Workpapers').': '.$summary['artifacts'],
        '- '.__('Sign-off notes').': '.($assessment['signoff_notes'] !== '' ? $assessment['signoff_notes'] : 'n/a'),
        '- '.__('Closure summary').': '.($assessment['closure_summary'] !== '' ? $assessment['closure_summary'] : 'n/a'),
        '',
        __('Framework coverage'),
    ];

    foreach ($frameworkBreakdown as $framework) {
        $lines[] = sprintf(
            '- %s · %s %s · %s %s · %s %s / %s %s / %s %s / %s %s',
            trim(sprintf('%s %s', $framework['framework_code'], $framework['framework_name'])),
            $framework['requirement_count'],
            __('Mapped requirements'),
            $framework['control_count'],
            __('Linked controls'),
            $framework['result_summary']['pass'],
            __('Pass'),
            $framework['result_summary']['partial'],
            __('Partial'),
            $framework['result_summary']['fail'],
            __('Fail'),
            $framework['result_summary']['not-tested'],
            __('Pending'),
        );
    }

    $lines = [
        ...$lines,
        '',
        __('Checklist'),
    ];

    foreach ($reviews as $review) {
        $lines[] = sprintf(
            '- %s [%s]',
            $review['control_name'],
            strtoupper((string) $review['result']),
        );

        if ($review['conclusion'] !== '') {
            $lines[] = '  '.__('Conclusion').': '.$review['conclusion'];
        }

        if ($review['test_notes'] !== '') {
            $lines[] = '  '.__('Test notes').': '.$review['test_notes'];
        }

        if (is_array($review['linked_finding'] ?? null)) {
            $lines[] = '  '.__('Finding').': '.$review['linked_finding']['title'].' ('.$review['linked_finding']['severity'].')';
        }

        if (($review['artifacts'] ?? []) !== []) {
            $lines[] = '  '.__('Workpapers').': '.implode(', ', array_map(
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

Route::post('/plugins/assessments', function (
    Request $request,
    AssessmentsAuditsRepository $repository,
    FunctionalActorServiceInterface $actors,
) {
    $frameworkIds = $repository->frameworkOptionIds(
        (string) $request->input('organization_id', 'org-a'),
        is_string($request->input('scope_id')) ? $request->input('scope_id') : null,
    );

    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'framework_id' => ['nullable', 'string', 'max:64', Rule::in($frameworkIds)],
        'title' => ['required', 'string', 'max:160'],
        'summary' => ['required', 'string', 'max:500'],
        'starts_on' => ['required', 'date'],
        'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        'status' => ['nullable', 'string', Rule::in(AssessmentReferenceData::statusKeys())],
        'control_ids' => ['nullable', 'array'],
        'control_ids.*' => ['string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $assessment = $repository->create($validated);
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'assessment',
            domainObjectId: $assessment['id'],
            assignmentType: 'owner',
            organizationId: $assessment['organization_id'],
            scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
            metadata: ['source' => 'assessments-audits'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.assessments-audits.root',
        'principal_id' => $principalId,
        'organization_id' => $assessment['organization_id'],
        'assessment_id' => $assessment['id'],
        'scope_id' => $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', __('Saved.'));
})->middleware('core.permission:plugin.assessments-audits.assessments.manage')->name('plugin.assessments-audits.store');

Route::post('/plugins/assessments/{assessmentId}', function (
    Request $request,
    string $assessmentId,
    AssessmentsAuditsRepository $repository,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) {
    $frameworkIds = $repository->frameworkOptionIds(
        (string) $request->input('organization_id', 'org-a'),
        is_string($request->input('scope_id')) ? $request->input('scope_id') : null,
    );

    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'framework_id' => ['nullable', 'string', 'max:64', Rule::in($frameworkIds)],
        'title' => ['required', 'string', 'max:160'],
        'summary' => ['required', 'string', 'max:500'],
        'starts_on' => ['required', 'date'],
        'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        'status' => ['required', 'string', Rule::in(AssessmentReferenceData::statusKeys())],
        'control_ids' => ['nullable', 'array'],
        'control_ids.*' => ['string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: (string) $validated['organization_id'],
        scopeId: is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
        domainObjectType: 'assessment',
        domainObjectId: $assessmentId,
    ), 403);

    $assessment = $repository->update($assessmentId, $validated);

    abort_if($assessment === null, 404);

    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'assessment',
            domainObjectId: $assessment['id'],
            assignmentType: 'owner',
            organizationId: $assessment['organization_id'],
            scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
            metadata: ['source' => 'assessments-audits'],
            assignedByPrincipalId: $principalId,
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

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.assessments-audits.root',
        'principal_id' => $principalId,
        'organization_id' => $assessment['organization_id'],
        'assessment_id' => $assessment['id'],
        'scope_id' => $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', __('Saved.'));
})->middleware('core.permission:plugin.assessments-audits.assessments.manage')->name('plugin.assessments-audits.update');

Route::post('/plugins/assessments/{assessmentId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $assessmentId,
    string $assignmentId,
    AssessmentsAuditsRepository $repository,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) {
    $assessment = $repository->find($assessmentId);

    abort_if($assessment === null, 404);
    abort_unless($objectAccess->canAccessObject(
        principalId: (string) $request->input('principal_id', 'principal-org-a'),
        organizationId: $assessment['organization_id'],
        scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
        domainObjectType: 'assessment',
        domainObjectId: $assessment['id'],
    ), 403);

    $assignment = collect($actors->assignmentsFor(
        domainObjectType: 'assessment',
        domainObjectId: $assessment['id'],
        organizationId: $assessment['organization_id'],
        scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
    ))->first(fn ($candidate) => $candidate->id === $assignmentId && $candidate->assignmentType === 'owner');

    abort_if($assignment === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $actors->deactivateAssignment(
        assignmentId: $assignmentId,
        deactivatedByPrincipalId: $principalId,
    );

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.assessments-audits.root',
        'principal_id' => $principalId,
        'organization_id' => $assessment['organization_id'],
        'assessment_id' => $assessment['id'],
        'scope_id' => $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', __('Owner removed.'));
})->middleware('core.permission:plugin.assessments-audits.assessments.manage')->name('plugin.assessments-audits.owners.destroy');

Route::post('/plugins/assessments/{assessmentId}/reviews/{controlId}', function (
    Request $request,
    string $assessmentId,
    string $controlId,
    AssessmentsAuditsRepository $repository,
    ObjectAccessService $objectAccess,
) {
    $validated = $request->validate([
        'result' => ['required', 'string', Rule::in(AssessmentReferenceData::reviewResultKeys())],
        'test_notes' => ['nullable', 'string', 'max:5000'],
        'conclusion' => ['nullable', 'string', 'max:5000'],
        'reviewed_on' => ['nullable', 'date'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: (string) $request->input('organization_id', 'org-a'),
        scopeId: is_string($request->input('scope_id')) && $request->input('scope_id') !== '' ? (string) $request->input('scope_id') : null,
        domainObjectType: 'assessment',
        domainObjectId: $assessmentId,
    ), 403);
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
    ]))->with('status', __('Saved.'));
})->middleware('core.permission:plugin.assessments-audits.assessments.manage')->name('plugin.assessments-audits.reviews.update');

Route::post('/plugins/assessments/{assessmentId}/reviews/{controlId}/artifacts', function (
    Request $request,
    string $assessmentId,
    string $controlId,
    AssessmentsAuditsRepository $repository,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: (string) $request->input('organization_id', 'org-a'),
        scopeId: is_string($request->input('scope_id')) && $request->input('scope_id') !== '' ? (string) $request->input('scope_id') : null,
        domainObjectType: 'assessment',
        domainObjectId: $assessmentId,
    ), 403);

    $review = $repository->review($assessmentId, $controlId);

    abort_if($review === null, 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
    ]);

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
    FindingsRemediationRepository $findings,
    ObjectAccessService $objectAccess,
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: (string) $request->input('organization_id', 'org-a'),
        scopeId: is_string($request->input('scope_id')) && $request->input('scope_id') !== '' ? (string) $request->input('scope_id') : null,
        domainObjectType: 'assessment',
        domainObjectId: $assessmentId,
    ), 403);

    $review = $repository->review($assessmentId, $controlId);

    abort_if($review === null, 404);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'severity' => ['required', 'in:low,medium,high,critical'],
        'description' => ['required', 'string', 'max:5000'],
        'due_on' => ['nullable', 'date'],
    ]);

    $control = DB::table('controls')
        ->where('id', $controlId)
        ->where('organization_id', (string) $request->input('organization_id', 'org-a'))
        ->first(['scope_id']);

    $findingScopeId = is_string($control?->scope_id) && $control->scope_id !== ''
        ? (string) $control->scope_id
        : (is_string($request->input('scope_id')) && $request->input('scope_id') !== '' ? (string) $request->input('scope_id') : null);

    $finding = $findings->createFinding([
        'organization_id' => (string) $request->input('organization_id', 'org-a'),
        'scope_id' => $findingScopeId,
        'title' => (string) $validated['title'],
        'severity' => (string) $validated['severity'],
        'description' => (string) $validated['description'],
        'linked_control_id' => $controlId,
        'linked_risk_id' => null,
        'due_on' => is_string($validated['due_on'] ?? null) ? $validated['due_on'] : null,
    ]);

    $repository->linkFinding($assessmentId, $controlId, $finding['id']);

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

Route::post('/plugins/assessments/{assessmentId}/transitions/{transitionKey}', function (
    Request $request,
    string $assessmentId,
    string $transitionKey,
    AssessmentsAuditsRepository $repository,
    ObjectAccessService $objectAccess,
) {
    $validated = $request->validate([
        'signoff_notes' => ['nullable', 'string', 'max:5000'],
        'signed_off_on' => ['nullable', 'date'],
        'closure_summary' => ['nullable', 'string', 'max:5000'],
        'closed_on' => ['nullable', 'date'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: (string) $request->input('organization_id', 'org-a'),
        scopeId: is_string($request->input('scope_id')) && $request->input('scope_id') !== '' ? (string) $request->input('scope_id') : null,
        domainObjectType: 'assessment',
        domainObjectId: $assessmentId,
    ), 403);
    $membershipId = $request->input('membership_id');
    $assessment = $repository->find($assessmentId);

    abort_if($assessment === null, 404);

    $updated = match ($transitionKey) {
        'activate' => $repository->update($assessmentId, [...$assessment, 'status' => 'active']),
        'sign-off' => $repository->signOff(
            $assessmentId,
            $principalId,
            $validated['signoff_notes'] ?? null,
            is_string($validated['signed_off_on'] ?? null) ? $validated['signed_off_on'] : null,
        ),
        'close' => $repository->close(
            $assessmentId,
            $principalId,
            $validated['closure_summary'] ?? null,
            is_string($validated['closed_on'] ?? null) ? $validated['closed_on'] : null,
        ),
        'reopen' => $repository->reopen($assessmentId),
        default => null,
    };

    abort_if($updated === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.assessments-audits.root',
        'principal_id' => $principalId,
        'organization_id' => $updated['organization_id'],
        'assessment_id' => $updated['id'],
        'scope_id' => $updated['scope_id'] !== '' ? $updated['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', __('Assessment updated.'));
})->middleware('core.permission:plugin.assessments-audits.assessments.manage')->name('plugin.assessments-audits.transition');
