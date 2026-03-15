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
use PymeSec\Plugins\ContinuityBcm\ContinuityBcmRepository;

Route::get('/plugins/continuity/services', function (Request $request, ContinuityBcmRepository $repository) {
    return response()->json([
        'plugin' => 'continuity-bcm',
        'services' => $repository->allServices(
            (string) $request->query('organization_id', 'org-a'),
            $request->query('scope_id'),
        ),
    ]);
})->name('plugin.continuity-bcm.index');

Route::get('/plugins/continuity/plans', function (Request $request, ContinuityBcmRepository $repository) {
    return response()->json([
        'plugin' => 'continuity-bcm',
        'plans' => $repository->allPlans(
            (string) $request->query('organization_id', 'org-a'),
            $request->query('scope_id'),
        ),
    ]);
})->name('plugin.continuity-bcm.plans');

Route::post('/plugins/continuity/services', function (
    Request $request,
    ContinuityBcmRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'impact_tier' => ['required', 'string', 'max:80'],
        'recovery_time_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        'recovery_point_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        'linked_asset_id' => ['nullable', 'string', 'max:120', 'exists:assets,id'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $service = $repository->createService($validated);
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'continuity-service',
            domainObjectId: $service['id'],
            assignmentType: 'owner',
            organizationId: $service['organization_id'],
            scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
            metadata: ['source' => 'continuity-bcm'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.continuity-bcm.root',
        'service_id' => $service['id'],
        'principal_id' => $principalId,
        'organization_id' => $service['organization_id'],
        'scope_id' => $service['scope_id'] !== '' ? $service['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.continuity-bcm.plans.manage')->name('plugin.continuity-bcm.store');

Route::post('/plugins/continuity/services/{serviceId}/dependencies', function (
    Request $request,
    string $serviceId,
    ContinuityBcmRepository $repository
) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'depends_on_service_id' => ['required', 'string', 'max:120'],
        'dependency_kind' => ['required', 'string', 'max:80'],
        'recovery_notes' => ['nullable', 'string', 'max:255'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $scopeId = $request->input('scope_id');

    $repository->addServiceDependency($serviceId, $validated);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.continuity-bcm.root',
        'service_id' => $serviceId,
        'principal_id' => $principalId,
        'organization_id' => $validated['organization_id'],
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.continuity-bcm.plans.manage')->name('plugin.continuity-bcm.dependencies.store');

Route::post('/plugins/continuity/services/{serviceId}', function (
    Request $request,
    string $serviceId,
    ContinuityBcmRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'impact_tier' => ['required', 'string', 'max:80'],
        'recovery_time_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        'recovery_point_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        'linked_asset_id' => ['nullable', 'string', 'max:120', 'exists:assets,id'],
        'linked_risk_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $service = $repository->updateService($serviceId, [
        ...$validated,
        'organization_id' => (string) $request->input('organization_id', 'org-a'),
    ]);

    abort_if($service === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'continuity-service',
            domainObjectId: $service['id'],
            assignmentType: 'owner',
            organizationId: $service['organization_id'],
            scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
            metadata: ['source' => 'continuity-bcm'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.continuity-bcm.root',
        'service_id' => $service['id'],
        'principal_id' => $principalId,
        'organization_id' => $service['organization_id'],
        'scope_id' => $service['scope_id'] !== '' ? $service['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.continuity-bcm.plans.manage')->name('plugin.continuity-bcm.update');

Route::post('/plugins/continuity/services/{serviceId}/artifacts', function (
    Request $request,
    string $serviceId,
    ContinuityBcmRepository $repository,
    ArtifactServiceInterface $artifacts
) {
    $service = $repository->findService($serviceId);

    abort_if($service === null, 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'continuity-bcm',
        subjectType: 'continuity-service',
        subjectId: $serviceId,
        artifactType: (string) ($validated['artifact_type'] ?? 'continuity-record'),
        label: (string) ($validated['label'] ?? 'Continuity record'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $service['organization_id'],
        scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
        metadata: [
            'plugin' => 'continuity-bcm',
            'impact_tier' => $service['impact_tier'],
            'linked_asset_id' => $service['linked_asset_id'],
            'linked_risk_id' => $service['linked_risk_id'],
        ],
    ));

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.continuity-bcm.root',
        'service_id' => $serviceId,
        'principal_id' => $principalId,
        'organization_id' => $service['organization_id'],
        'scope_id' => $service['scope_id'] !== '' ? $service['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.continuity-bcm.plans.manage')->name('plugin.continuity-bcm.artifacts.store');

Route::post('/plugins/continuity/services/{serviceId}/transitions/{transitionKey}', function (
    Request $request,
    string $serviceId,
    string $transitionKey,
    WorkflowServiceInterface $workflows
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    $workflows->transition(
        workflowKey: 'plugin.continuity-bcm.service-lifecycle',
        subjectType: 'continuity-service',
        subjectId: $serviceId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'request'),
            memberships: is_string($membershipId) && $membershipId !== ''
                ? [new MembershipReference(id: $membershipId, principalId: $principalId, organizationId: $organizationId)]
                : [],
            organizationId: $organizationId,
            scopeId: is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
            membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        ),
    );

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.continuity-bcm.root',
        'service_id' => $serviceId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.continuity-bcm.plans.manage')->name('plugin.continuity-bcm.transition');

Route::post('/plugins/continuity/services/{serviceId}/plans', function (
    Request $request,
    string $serviceId,
    ContinuityBcmRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'strategy_summary' => ['required', 'string', 'max:255'],
        'test_due_on' => ['nullable', 'date'],
        'linked_policy_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $plan = $repository->createPlan($serviceId, $validated);
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'continuity-plan',
            domainObjectId: $plan['id'],
            assignmentType: 'owner',
            organizationId: $plan['organization_id'],
            scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
            metadata: ['source' => 'continuity-bcm'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.continuity-bcm.plans',
        'plan_id' => $plan['id'],
        'principal_id' => $principalId,
        'organization_id' => $plan['organization_id'],
        'scope_id' => $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.continuity-bcm.plans.manage')->name('plugin.continuity-bcm.plans.store');

Route::post('/plugins/continuity/plans/{planId}/exercises', function (
    Request $request,
    string $planId,
    ContinuityBcmRepository $repository
) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'exercise_date' => ['required', 'date'],
        'exercise_type' => ['required', 'string', 'max:80'],
        'scenario_summary' => ['required', 'string', 'max:255'],
        'outcome' => ['required', 'string', 'max:40'],
        'follow_up_summary' => ['nullable', 'string', 'max:255'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $scopeId = $request->input('scope_id');

    $repository->recordExercise($planId, $validated);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.continuity-bcm.plans',
        'plan_id' => $planId,
        'principal_id' => $principalId,
        'organization_id' => $validated['organization_id'],
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.continuity-bcm.plans.manage')->name('plugin.continuity-bcm.plans.exercises.store');

Route::post('/plugins/continuity/plans/{planId}/executions', function (
    Request $request,
    string $planId,
    ContinuityBcmRepository $repository
) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'executed_on' => ['required', 'date'],
        'execution_type' => ['required', 'string', 'max:80'],
        'status' => ['required', 'string', 'max:40'],
        'participants' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string', 'max:255'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $scopeId = $request->input('scope_id');

    $repository->recordTestExecution($planId, $validated);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.continuity-bcm.plans',
        'plan_id' => $planId,
        'principal_id' => $principalId,
        'organization_id' => $validated['organization_id'],
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.continuity-bcm.plans.manage')->name('plugin.continuity-bcm.plans.executions.store');

Route::post('/plugins/continuity/plans/{planId}', function (
    Request $request,
    string $planId,
    ContinuityBcmRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:160'],
        'strategy_summary' => ['required', 'string', 'max:255'],
        'test_due_on' => ['nullable', 'date'],
        'linked_policy_id' => ['nullable', 'string', 'max:120'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $plan = $repository->updatePlan($planId, [
        ...$validated,
        'organization_id' => (string) $request->input('organization_id', 'org-a'),
    ]);

    abort_if($plan === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'continuity-plan',
            domainObjectId: $plan['id'],
            assignmentType: 'owner',
            organizationId: $plan['organization_id'],
            scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
            metadata: ['source' => 'continuity-bcm'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.continuity-bcm.plans',
        'plan_id' => $plan['id'],
        'principal_id' => $principalId,
        'organization_id' => $plan['organization_id'],
        'scope_id' => $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.continuity-bcm.plans.manage')->name('plugin.continuity-bcm.plans.update');

Route::post('/plugins/continuity/plans/{planId}/artifacts', function (
    Request $request,
    string $planId,
    ContinuityBcmRepository $repository,
    ArtifactServiceInterface $artifacts
) {
    $plan = $repository->findPlan($planId);

    abort_if($plan === null, 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'continuity-bcm',
        subjectType: 'continuity-plan',
        subjectId: $planId,
        artifactType: (string) ($validated['artifact_type'] ?? 'recovery-plan'),
        label: (string) ($validated['label'] ?? 'Recovery plan'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $plan['organization_id'],
        scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
        metadata: [
            'plugin' => 'continuity-bcm',
            'linked_policy_id' => $plan['linked_policy_id'],
            'linked_finding_id' => $plan['linked_finding_id'],
        ],
    ));

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.continuity-bcm.plans',
        'plan_id' => $planId,
        'principal_id' => $principalId,
        'organization_id' => $plan['organization_id'],
        'scope_id' => $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
    ]));
})->middleware('core.permission:plugin.continuity-bcm.plans.manage')->name('plugin.continuity-bcm.plans.artifacts.store');

Route::post('/plugins/continuity/plans/{planId}/transitions/{transitionKey}', function (
    Request $request,
    string $planId,
    string $transitionKey,
    WorkflowServiceInterface $workflows
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    $workflows->transition(
        workflowKey: 'plugin.continuity-bcm.plan-lifecycle',
        subjectType: 'continuity-plan',
        subjectId: $planId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(id: $principalId, provider: 'request'),
            memberships: is_string($membershipId) && $membershipId !== ''
                ? [new MembershipReference(id: $membershipId, principalId: $principalId, organizationId: $organizationId)]
                : [],
            organizationId: $organizationId,
            scopeId: is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
            membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        ),
    );

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.continuity-bcm.plans',
        'plan_id' => $planId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
    ]));
})->middleware('core.permission:plugin.continuity-bcm.plans.manage')->name('plugin.continuity-bcm.plans.transition');
