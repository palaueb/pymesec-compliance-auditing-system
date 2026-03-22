<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Plugins\PolicyExceptions\PolicyExceptionsRepository;

Route::get('/plugins/policies', function (Request $request, PolicyExceptionsRepository $repository, ObjectAccessService $objectAccess) {
    $organizationId = (string) $request->query('organization_id', 'org-a');

    return response()->json([
        'plugin' => 'policy-exceptions',
        'policies' => $objectAccess->filterRecords(
            $repository->allPolicies($organizationId, $request->query('scope_id')),
            'id',
            is_string($request->query('principal_id')) ? (string) $request->query('principal_id') : null,
            $organizationId,
            is_string($request->query('scope_id')) ? (string) $request->query('scope_id') : null,
            'policy',
        ),
    ]);
})->middleware('core.permission:plugin.policy-exceptions.policies.view')->name('plugin.policy-exceptions.index');

Route::get('/plugins/policies/exceptions', function (Request $request, PolicyExceptionsRepository $repository, ObjectAccessService $objectAccess) {
    $organizationId = (string) $request->query('organization_id', 'org-a');

    return response()->json([
        'plugin' => 'policy-exceptions',
        'exceptions' => $objectAccess->filterRecords(
            $repository->exceptions($organizationId, $request->query('scope_id')),
            'id',
            is_string($request->query('principal_id')) ? (string) $request->query('principal_id') : null,
            $organizationId,
            is_string($request->query('scope_id')) ? (string) $request->query('scope_id') : null,
            'policy-exception',
        ),
    ]);
})->middleware('core.permission:plugin.policy-exceptions.policies.view')->name('plugin.policy-exceptions.exceptions');

Route::post('/plugins/policies', function (
    Request $request,
    PolicyExceptionsRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'area' => ['required', 'string', 'max:80'],
        'version_label' => ['required', 'string', 'max:40'],
        'statement' => ['required', 'string', 'max:2000'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'review_due_on' => ['nullable', 'date'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $policy = $repository->createPolicy($validated);
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'policy',
            domainObjectId: $policy['id'],
            assignmentType: 'owner',
            organizationId: $policy['organization_id'],
            scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
            metadata: ['source' => 'policy-exceptions'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.policy-exceptions.root',
        'policy_id' => $policy['id'],
        'principal_id' => $principalId,
        'organization_id' => $policy['organization_id'],
        'scope_id' => $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.policy-exceptions.policies.manage')->name('plugin.policy-exceptions.store');

Route::post('/plugins/policies/{policyId}', function (
    Request $request,
    string $policyId,
    PolicyExceptionsRepository $repository,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'area' => ['required', 'string', 'max:80'],
        'version_label' => ['required', 'string', 'max:40'],
        'statement' => ['required', 'string', 'max:2000'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'review_due_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $organizationId = (string) $request->input('organization_id', 'org-a');
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $organizationId,
        scopeId: is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
        domainObjectType: 'policy',
        domainObjectId: $policyId,
    ), 403);

    $policy = $repository->updatePolicy($policyId, [
        ...$validated,
        'organization_id' => $organizationId,
    ]);

    abort_if($policy === null, 404);

    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'policy',
            domainObjectId: $policy['id'],
            assignmentType: 'owner',
            organizationId: $policy['organization_id'],
            scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
            metadata: ['source' => 'policy-exceptions'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.policy-exceptions.root',
        'policy_id' => $policy['id'],
        'principal_id' => $principalId,
        'organization_id' => $policy['organization_id'],
        'scope_id' => $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.policy-exceptions.policies.manage')->name('plugin.policy-exceptions.update');

Route::post('/plugins/policies/{policyId}/exceptions', function (
    Request $request,
    string $policyId,
    PolicyExceptionsRepository $repository,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) {
    $policy = $repository->findPolicy($policyId);

    abort_if($policy === null, 404);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'rationale' => ['required', 'string', 'max:2000'],
        'compensating_control' => ['nullable', 'string', 'max:1000'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'expires_on' => ['nullable', 'date'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $policy['organization_id'],
        scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        domainObjectType: 'policy',
        domainObjectId: $policyId,
    ), 403);

    $exception = $repository->createException($policyId, [
        ...$validated,
        'organization_id' => $policy['organization_id'],
        'scope_id' => $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
    ]);

    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'policy-exception',
            domainObjectId: $exception['id'],
            assignmentType: 'owner',
            organizationId: $exception['organization_id'],
            scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
            metadata: ['source' => 'policy-exceptions'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.policy-exceptions.root',
        'policy_id' => $policy['id'],
        'principal_id' => $principalId,
        'organization_id' => $policy['organization_id'],
        'scope_id' => $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.policy-exceptions.policies.manage')->name('plugin.policy-exceptions.exceptions.store');

Route::post('/plugins/policies/exceptions/{exceptionId}', function (
    Request $request,
    string $exceptionId,
    PolicyExceptionsRepository $repository,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'rationale' => ['required', 'string', 'max:2000'],
        'compensating_control' => ['nullable', 'string', 'max:1000'],
        'linked_finding_id' => ['nullable', 'string', 'max:120'],
        'expires_on' => ['nullable', 'date'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $exception = $repository->updateException($exceptionId, $validated);

    abort_if($exception === null, 404);
    $policy = $repository->findPolicy((string) $exception['policy_id']);

    abort_if($policy === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $exception['organization_id'],
        scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
        domainObjectType: 'policy-exception',
        domainObjectId: $exceptionId,
    ), 403);
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'policy-exception',
            domainObjectId: $exception['id'],
            assignmentType: 'owner',
            organizationId: $exception['organization_id'],
            scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
            metadata: ['source' => 'policy-exceptions'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.policy-exceptions.exceptions',
        'exception_id' => $exception['id'],
        'principal_id' => $principalId,
        'organization_id' => $exception['organization_id'],
        'scope_id' => $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.policy-exceptions.policies.manage')->name('plugin.policy-exceptions.exceptions.update');

Route::post('/plugins/policies/{policyId}/artifacts', function (
    Request $request,
    string $policyId,
    PolicyExceptionsRepository $repository,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
) {
    $policy = $repository->findPolicy($policyId);

    abort_if($policy === null, 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $policy['organization_id'],
        scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        domainObjectType: 'policy',
        domainObjectId: $policyId,
    ), 403);

    $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'policy-exceptions',
        subjectType: 'policy',
        subjectId: $policyId,
        artifactType: (string) ($validated['artifact_type'] ?? 'document'),
        label: (string) ($validated['label'] ?? 'Policy document'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $policy['organization_id'],
        scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        metadata: [
            'plugin' => 'policy-exceptions',
            'area' => $policy['area'],
            'version_label' => $policy['version_label'],
        ],
    ));

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.policy-exceptions.root',
        'policy_id' => $policy['id'],
        'principal_id' => $principalId,
        'organization_id' => $policy['organization_id'],
        'scope_id' => $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.policy-exceptions.policies.manage')->name('plugin.policy-exceptions.artifacts.store');

Route::post('/plugins/policies/exceptions/{exceptionId}/artifacts', function (
    Request $request,
    string $exceptionId,
    PolicyExceptionsRepository $repository,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
) {
    $exception = $repository->findException($exceptionId);

    abort_if($exception === null, 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $exception['organization_id'],
        scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
        domainObjectType: 'policy-exception',
        domainObjectId: $exceptionId,
    ), 403);

    $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'policy-exceptions',
        subjectType: 'policy-exception',
        subjectId: $exceptionId,
        artifactType: (string) ($validated['artifact_type'] ?? 'evidence'),
        label: (string) ($validated['label'] ?? 'Exception evidence'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $exception['organization_id'],
        scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
        metadata: [
            'plugin' => 'policy-exceptions',
            'policy_id' => $exception['policy_id'],
            'linked_finding_id' => $exception['linked_finding_id'],
        ],
    ));

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.policy-exceptions.exceptions',
        'exception_id' => $exception['id'],
        'principal_id' => $principalId,
        'organization_id' => $exception['organization_id'],
        'scope_id' => $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.policy-exceptions.policies.manage')->name('plugin.policy-exceptions.exceptions.artifacts.store');

Route::post('/plugins/policies/{policyId}/transitions/{transitionKey}', function (
    Request $request,
    string $policyId,
    string $transitionKey,
    WorkflowServiceInterface $workflows,
    ObjectAccessService $objectAccess,
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $organizationId,
        scopeId: is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        domainObjectType: 'policy',
        domainObjectId: $policyId,
    ), 403);

    $workflows->transition(
        workflowKey: 'plugin.policy-exceptions.policy-lifecycle',
        subjectType: 'policy',
        subjectId: $policyId,
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
        'menu' => 'plugin.policy-exceptions.root',
        'policy_id' => $policyId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.policy-exceptions.policies.manage')->name('plugin.policy-exceptions.transition');

Route::post('/plugins/policies/exceptions/{exceptionId}/transitions/{transitionKey}', function (
    Request $request,
    string $exceptionId,
    string $transitionKey,
    WorkflowServiceInterface $workflows,
    ObjectAccessService $objectAccess,
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $organizationId,
        scopeId: is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        domainObjectType: 'policy-exception',
        domainObjectId: $exceptionId,
    ), 403);

    $workflows->transition(
        workflowKey: 'plugin.policy-exceptions.exception-lifecycle',
        subjectType: 'policy-exception',
        subjectId: $exceptionId,
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
        'menu' => 'plugin.policy-exceptions.exceptions',
        'exception_id' => $exceptionId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.policy-exceptions.policies.manage')->name('plugin.policy-exceptions.exceptions.transition');
