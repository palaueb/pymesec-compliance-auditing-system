<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Plugins\DataFlowsPrivacy\DataFlowsPrivacyRepository;

Route::get('/plugins/privacy/data-flows', function (Request $request, DataFlowsPrivacyRepository $repository) {
    return response()->json([
        'plugin' => 'data-flows-privacy',
        'data_flows' => $repository->allDataFlows(
            (string) $request->query('organization_id', 'org-a'),
            $request->query('scope_id'),
        ),
    ]);
})->name('plugin.data-flows-privacy.index');

Route::get('/plugins/privacy/activities', function (Request $request, DataFlowsPrivacyRepository $repository) {
    return response()->json([
        'plugin' => 'data-flows-privacy',
        'activities' => $repository->allProcessingActivities(
            (string) $request->query('organization_id', 'org-a'),
            $request->query('scope_id'),
        ),
    ]);
})->name('plugin.data-flows-privacy.activities');

Route::post('/plugins/privacy/data-flows', function (
    Request $request,
    DataFlowsPrivacyRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'source' => ['required', 'string', 'max:160'],
        'destination' => ['required', 'string', 'max:160'],
        'data_category_summary' => ['required', 'string', 'max:200'],
        'transfer_type' => ['required', 'string', 'max:80'],
        'review_due_on' => ['nullable', 'date'],
        'linked_asset_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $flow = $repository->createDataFlow($validated);
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'privacy-data-flow',
            domainObjectId: $flow['id'],
            assignmentType: 'owner',
            organizationId: $flow['organization_id'],
            scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
            metadata: ['source' => 'data-flows-privacy'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.data-flows-privacy.root',
        'principal_id' => $principalId,
        'organization_id' => $flow['organization_id'],
        'scope_id' => $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.data-flows-privacy.records.manage')->name('plugin.data-flows-privacy.store');

Route::post('/plugins/privacy/data-flows/{flowId}', function (
    Request $request,
    string $flowId,
    DataFlowsPrivacyRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'source' => ['required', 'string', 'max:160'],
        'destination' => ['required', 'string', 'max:160'],
        'data_category_summary' => ['required', 'string', 'max:200'],
        'transfer_type' => ['required', 'string', 'max:80'],
        'review_due_on' => ['nullable', 'date'],
        'linked_asset_id' => ['nullable', 'string', 'max:120'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $flow = $repository->updateDataFlow($flowId, [
        ...$validated,
        'organization_id' => (string) $request->input('organization_id', 'org-a'),
    ]);

    abort_if($flow === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'privacy-data-flow',
            domainObjectId: $flow['id'],
            assignmentType: 'owner',
            organizationId: $flow['organization_id'],
            scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
            metadata: ['source' => 'data-flows-privacy'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.data-flows-privacy.root',
        'principal_id' => $principalId,
        'organization_id' => $flow['organization_id'],
        'scope_id' => $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.data-flows-privacy.records.manage')->name('plugin.data-flows-privacy.update');

Route::post('/plugins/privacy/data-flows/{flowId}/artifacts', function (
    Request $request,
    string $flowId,
    DataFlowsPrivacyRepository $repository,
    ArtifactServiceInterface $artifacts
) {
    $flow = $repository->findDataFlow($flowId);

    abort_if($flow === null, 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'data-flows-privacy',
        subjectType: 'privacy-data-flow',
        subjectId: $flowId,
        artifactType: (string) ($validated['artifact_type'] ?? 'record'),
        label: (string) ($validated['label'] ?? 'Privacy record'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $flow['organization_id'],
        scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
        metadata: [
            'plugin' => 'data-flows-privacy',
            'transfer_type' => $flow['transfer_type'],
            'linked_asset_id' => $flow['linked_asset_id'],
            'linked_risk_id' => $flow['linked_risk_id'],
        ],
    ));

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.data-flows-privacy.root',
        'principal_id' => $principalId,
        'organization_id' => $flow['organization_id'],
        'scope_id' => $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
    ]));
})->middleware('core.permission:plugin.data-flows-privacy.records.manage')->name('plugin.data-flows-privacy.artifacts.store');

Route::post('/plugins/privacy/data-flows/{flowId}/transitions/{transitionKey}', function (
    Request $request,
    string $flowId,
    string $transitionKey,
    WorkflowServiceInterface $workflows
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    $workflows->transition(
        workflowKey: 'plugin.data-flows-privacy.data-flow-lifecycle',
        subjectType: 'privacy-data-flow',
        subjectId: $flowId,
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
        'menu' => 'plugin.data-flows-privacy.root',
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
    ]));
})->middleware('core.permission:plugin.data-flows-privacy.records.manage')->name('plugin.data-flows-privacy.transition');

Route::post('/plugins/privacy/activities', function (
    Request $request,
    DataFlowsPrivacyRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'purpose' => ['required', 'string', 'max:200'],
        'lawful_basis' => ['required', 'string', 'max:120'],
        'linked_data_flow_ids' => ['nullable', 'string', 'max:255'],
        'linked_risk_ids' => ['nullable', 'string', 'max:255'],
        'linked_policy_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'review_due_on' => ['nullable', 'date'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $activity = $repository->createProcessingActivity($validated);
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'privacy-processing-activity',
            domainObjectId: $activity['id'],
            assignmentType: 'owner',
            organizationId: $activity['organization_id'],
            scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
            metadata: ['source' => 'data-flows-privacy'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.data-flows-privacy.activities',
        'principal_id' => $principalId,
        'organization_id' => $activity['organization_id'],
        'scope_id' => $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.data-flows-privacy.records.manage')->name('plugin.data-flows-privacy.activities.store');

Route::post('/plugins/privacy/activities/{activityId}', function (
    Request $request,
    string $activityId,
    DataFlowsPrivacyRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'purpose' => ['required', 'string', 'max:200'],
        'lawful_basis' => ['required', 'string', 'max:120'],
        'linked_data_flow_ids' => ['nullable', 'string', 'max:255'],
        'linked_risk_ids' => ['nullable', 'string', 'max:255'],
        'linked_policy_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'review_due_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $activity = $repository->updateProcessingActivity($activityId, [
        ...$validated,
        'organization_id' => (string) $request->input('organization_id', 'org-a'),
    ]);

    abort_if($activity === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'privacy-processing-activity',
            domainObjectId: $activity['id'],
            assignmentType: 'owner',
            organizationId: $activity['organization_id'],
            scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
            metadata: ['source' => 'data-flows-privacy'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.data-flows-privacy.activities',
        'principal_id' => $principalId,
        'organization_id' => $activity['organization_id'],
        'scope_id' => $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.data-flows-privacy.records.manage')->name('plugin.data-flows-privacy.activities.update');

Route::post('/plugins/privacy/activities/{activityId}/artifacts', function (
    Request $request,
    string $activityId,
    DataFlowsPrivacyRepository $repository,
    ArtifactServiceInterface $artifacts
) {
    $activity = $repository->findProcessingActivity($activityId);

    abort_if($activity === null, 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'data-flows-privacy',
        subjectType: 'privacy-processing-activity',
        subjectId: $activityId,
        artifactType: (string) ($validated['artifact_type'] ?? 'record'),
        label: (string) ($validated['label'] ?? 'Processing record'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $activity['organization_id'],
        scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
        metadata: [
            'plugin' => 'data-flows-privacy',
            'lawful_basis' => $activity['lawful_basis'],
            'linked_policy_id' => $activity['linked_policy_id'],
            'linked_finding_id' => $activity['linked_finding_id'],
        ],
    ));

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.data-flows-privacy.activities',
        'principal_id' => $principalId,
        'organization_id' => $activity['organization_id'],
        'scope_id' => $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
    ]));
})->middleware('core.permission:plugin.data-flows-privacy.records.manage')->name('plugin.data-flows-privacy.activities.artifacts.store');

Route::post('/plugins/privacy/activities/{activityId}/transitions/{transitionKey}', function (
    Request $request,
    string $activityId,
    string $transitionKey,
    WorkflowServiceInterface $workflows
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    $workflows->transition(
        workflowKey: 'plugin.data-flows-privacy.processing-activity-lifecycle',
        subjectType: 'privacy-processing-activity',
        subjectId: $activityId,
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
        'menu' => 'plugin.data-flows-privacy.activities',
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
    ]));
})->middleware('core.permission:plugin.data-flows-privacy.records.manage')->name('plugin.data-flows-privacy.activities.transition');
