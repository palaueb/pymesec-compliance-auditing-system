<?php

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Route;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Plugins\FindingsRemediation\FindingsReferenceData;
use PymeSec\Plugins\FindingsRemediation\FindingsRemediationRepository;

Route::get('/plugins/findings', function (Request $request, FindingsRemediationRepository $repository, ObjectAccessService $objectAccess) {
    $organizationId = (string) $request->query('organization_id', 'org-a');
    $scopeId = $request->query('scope_id');

    return response()->json([
        'plugin' => 'findings-remediation',
        'findings' => $objectAccess->filterRecords(
            records: $repository->allFindings($organizationId, $scopeId),
            idKey: 'id',
            principalId: is_string($request->query('principal_id')) ? $request->query('principal_id') : null,
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'finding',
        ),
    ]);
})->name('plugin.findings-remediation.index');

Route::get('/plugins/findings/board', function (Request $request, FindingsRemediationRepository $repository, ObjectAccessService $objectAccess) {
    $organizationId = (string) $request->query('organization_id', 'org-a');
    $scopeId = is_string($request->query('scope_id')) ? $request->query('scope_id') : null;
    $principalId = is_string($request->query('principal_id')) ? $request->query('principal_id') : null;
    $visibleFindingIds = $objectAccess->visibleObjectIds($principalId, $organizationId, $scopeId, 'finding');
    $visibleActionIds = $objectAccess->visibleObjectIds($principalId, $organizationId, $scopeId, 'remediation-action');
    $isFindingScoped = is_array($visibleFindingIds);
    $isActionScoped = is_array($visibleActionIds);
    $actions = [];

    foreach ($repository->actions($organizationId, $scopeId) as $action) {
        if ($isFindingScoped || $isActionScoped) {
            $matchesFinding = $isFindingScoped && in_array($action['finding_id'], $visibleFindingIds, true);
            $matchesAction = $isActionScoped && in_array($action['id'], $visibleActionIds, true);

            if (! $matchesFinding && ! $matchesAction) {
                continue;
            }
        }

        $actions[] = $action;
    }

    return response()->json([
        'plugin' => 'findings-remediation',
        'actions' => $actions,
    ]);
})->name('plugin.findings-remediation.board');

Route::post('/plugins/findings', function (
    Request $request,
    FindingsRemediationRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'severity' => ['required', 'string', Rule::in(FindingsReferenceData::severityKeys())],
        'description' => ['required', 'string', 'max:1000'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'due_on' => ['nullable', 'date'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $finding = $repository->createFinding($validated);
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
            assignmentType: 'owner',
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            metadata: ['source' => 'findings-remediation'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.findings-remediation.root',
        'finding_id' => $finding['id'],
        'principal_id' => $principalId,
        'organization_id' => $finding['organization_id'],
        'scope_id' => $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.findings-remediation.findings.manage')->name('plugin.findings-remediation.store');

Route::post('/plugins/findings/{findingId}', function (
    Request $request,
    string $findingId,
    FindingsRemediationRepository $repository,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) {
    $existingFinding = $repository->findFinding($findingId);

    abort_if($existingFinding === null, 404);
    abort_unless($objectAccess->canAccessObject(
        principalId: (string) $request->input('principal_id', 'principal-org-a'),
        organizationId: $existingFinding['organization_id'],
        scopeId: $existingFinding['scope_id'] !== '' ? $existingFinding['scope_id'] : null,
        domainObjectType: 'finding',
        domainObjectId: $existingFinding['id'],
    ), 403);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'severity' => ['required', 'string', Rule::in(FindingsReferenceData::severityKeys())],
        'description' => ['required', 'string', 'max:1000'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'due_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $finding = $repository->updateFinding($findingId, [
        ...$validated,
        'organization_id' => (string) $request->input('organization_id', 'org-a'),
    ]);

    abort_if($finding === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
            assignmentType: 'owner',
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            metadata: ['source' => 'findings-remediation'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.findings-remediation.root',
        'finding_id' => $finding['id'],
        'principal_id' => $principalId,
        'organization_id' => $finding['organization_id'],
        'scope_id' => $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.findings-remediation.findings.manage')->name('plugin.findings-remediation.update');

Route::post('/plugins/findings/{findingId}/actions', function (
    Request $request,
    string $findingId,
    FindingsRemediationRepository $repository,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) {
    $finding = $repository->findFinding($findingId);

    abort_if($finding === null, 404);
    abort_unless($objectAccess->canAccessObject(
        principalId: (string) $request->input('principal_id', 'principal-org-a'),
        organizationId: $finding['organization_id'],
        scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        domainObjectType: 'finding',
        domainObjectId: $finding['id'],
    ), 403);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'status' => ['required', 'string', Rule::in(FindingsReferenceData::remediationStatusKeys())],
        'notes' => ['nullable', 'string', 'max:1000'],
        'due_on' => ['nullable', 'date'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $action = $repository->createAction($findingId, [
        ...$validated,
        'organization_id' => $finding['organization_id'],
        'scope_id' => $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'remediation-action',
            domainObjectId: $action['id'],
            assignmentType: 'owner',
            organizationId: $action['organization_id'],
            scopeId: $action['scope_id'] !== '' ? $action['scope_id'] : null,
            metadata: ['source' => 'findings-remediation'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.findings-remediation.root',
        'finding_id' => $finding['id'],
        'principal_id' => $principalId,
        'organization_id' => $finding['organization_id'],
        'scope_id' => $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.findings-remediation.findings.manage')->name('plugin.findings-remediation.actions.store');

Route::post('/plugins/findings/actions/{actionId}', function (
    Request $request,
    string $actionId,
    FindingsRemediationRepository $repository,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) {
    $existingAction = $repository->findAction($actionId);

    abort_if($existingAction === null, 404);
    $existingFinding = $repository->findFinding((string) $existingAction['finding_id']);

    abort_if($existingFinding === null, 404);
    abort_unless(
        $objectAccess->canAccessObject(
            principalId: (string) $request->input('principal_id', 'principal-org-a'),
            organizationId: $existingFinding['organization_id'],
            scopeId: $existingFinding['scope_id'] !== '' ? $existingFinding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $existingFinding['id'],
        ) || $objectAccess->canAccessObject(
            principalId: (string) $request->input('principal_id', 'principal-org-a'),
            organizationId: $existingAction['organization_id'],
            scopeId: $existingAction['scope_id'] !== '' ? $existingAction['scope_id'] : null,
            domainObjectType: 'remediation-action',
            domainObjectId: $existingAction['id'],
        ),
        403
    );

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'status' => ['required', 'string', Rule::in(FindingsReferenceData::remediationStatusKeys())],
        'notes' => ['nullable', 'string', 'max:1000'],
        'due_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $action = $repository->updateAction($actionId, $validated);

    abort_if($action === null, 404);
    $finding = $repository->findFinding((string) $action['finding_id']);

    abort_if($finding === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'remediation-action',
            domainObjectId: $action['id'],
            assignmentType: 'owner',
            organizationId: $action['organization_id'],
            scopeId: $action['scope_id'] !== '' ? $action['scope_id'] : null,
            metadata: ['source' => 'findings-remediation'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.findings-remediation.root',
        'finding_id' => $finding['id'],
        'principal_id' => $principalId,
        'organization_id' => $finding['organization_id'],
        'scope_id' => $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.findings-remediation.findings.manage')->name('plugin.findings-remediation.actions.update');

Route::post('/plugins/findings/{findingId}/artifacts', function (
    Request $request,
    string $findingId,
    FindingsRemediationRepository $repository,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
) {
    $finding = $repository->findFinding($findingId);

    abort_if($finding === null, 404);
    abort_unless($objectAccess->canAccessObject(
        principalId: (string) $request->input('principal_id', 'principal-org-a'),
        organizationId: $finding['organization_id'],
        scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        domainObjectType: 'finding',
        domainObjectId: $finding['id'],
    ), 403);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'findings-remediation',
        subjectType: 'finding',
        subjectId: $findingId,
        artifactType: (string) ($validated['artifact_type'] ?? 'evidence'),
        label: (string) ($validated['label'] ?? 'Finding evidence'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $finding['organization_id'],
        scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        metadata: [
            'plugin' => 'findings-remediation',
            'severity' => $finding['severity'],
            'linked_control_id' => $finding['linked_control_id'],
            'linked_risk_id' => $finding['linked_risk_id'],
        ],
    ));

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.findings-remediation.root',
        'finding_id' => $findingId,
        'principal_id' => $principalId,
        'organization_id' => $finding['organization_id'],
        'scope_id' => $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.findings-remediation.findings.manage')->name('plugin.findings-remediation.artifacts.store');

Route::post('/plugins/findings/{findingId}/transitions/{transitionKey}', function (
    Request $request,
    string $findingId,
    string $transitionKey,
    WorkflowServiceInterface $workflows,
    FindingsRemediationRepository $repository,
    ObjectAccessService $objectAccess,
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');
    $finding = $repository->findFinding($findingId);

    abort_if($finding === null, 404);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $finding['organization_id'],
        scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        domainObjectType: 'finding',
        domainObjectId: $finding['id'],
    ), 403);

    $workflows->transition(
        workflowKey: 'plugin.findings-remediation.finding-lifecycle',
        subjectType: 'finding',
        subjectId: $findingId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'request'),
            memberships: is_string($membershipId) && $membershipId !== ''
                ? [
                    new MembershipReference(
                        id: $membershipId,
                        principalId: $principalId,
                        organizationId: $organizationId,
                    ),
                ]
                : [],
            organizationId: $organizationId,
            scopeId: is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
            membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        ),
    );

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.findings-remediation.root',
        'finding_id' => $findingId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.findings-remediation.findings.manage')->name('plugin.findings-remediation.transition');
