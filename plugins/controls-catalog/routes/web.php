<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Plugins\ControlsCatalog\ControlsCatalogRepository;

Route::get('/plugins/controls', function (Request $request, ControlsCatalogRepository $repository) {
    return response()->json([
        'plugin' => 'controls-catalog',
        'controls' => $repository->all(
            (string) $request->query('organization_id', 'org-a'),
            $request->query('scope_id'),
        ),
    ]);
})->name('plugin.controls-catalog.index');

Route::get('/plugins/controls/reviews', function (Request $request, ControlsCatalogRepository $repository) {
    return response()->json([
        'plugin' => 'controls-catalog',
        'review_controls' => $repository->all(
            (string) $request->query('organization_id', 'org-a'),
            $request->query('scope_id'),
        ),
    ]);
})->name('plugin.controls-catalog.reviews');

Route::post('/plugins/controls', function (
    Request $request,
    ControlsCatalogRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:120'],
        'framework' => ['required', 'string', 'max:80'],
        'domain' => ['required', 'string', 'max:80'],
        'evidence' => ['required', 'string', 'max:500'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $control = $repository->create($validated);
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'control',
            domainObjectId: $control['id'],
            assignmentType: 'owner',
            organizationId: $control['organization_id'],
            scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
            metadata: ['source' => 'controls-catalog'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.controls-catalog.root',
        'principal_id' => $principalId,
        'organization_id' => $control['organization_id'],
        'scope_id' => $control['scope_id'] !== '' ? $control['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.store');

Route::post('/plugins/controls/{controlId}', function (
    Request $request,
    string $controlId,
    ControlsCatalogRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:120'],
        'framework' => ['required', 'string', 'max:80'],
        'domain' => ['required', 'string', 'max:80'],
        'evidence' => ['required', 'string', 'max:500'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $control = $repository->update($controlId, [
        ...$validated,
        'organization_id' => (string) $request->input('organization_id', 'org-a'),
    ]);

    abort_if($control === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'control',
            domainObjectId: $control['id'],
            assignmentType: 'owner',
            organizationId: $control['organization_id'],
            scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
            metadata: ['source' => 'controls-catalog'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.controls-catalog.root',
        'principal_id' => $principalId,
        'organization_id' => $control['organization_id'],
        'scope_id' => $control['scope_id'] !== '' ? $control['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.update');

Route::post('/plugins/controls/{controlId}/artifacts', function (
    Request $request,
    string $controlId,
    ControlsCatalogRepository $repository,
    ArtifactServiceInterface $artifacts
) {
    $control = $repository->find($controlId);

    abort_if($control === null, 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $locale = $request->input('locale', 'en');

    $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'controls-catalog',
        subjectType: 'control',
        subjectId: $controlId,
        artifactType: (string) ($validated['artifact_type'] ?? 'evidence'),
        label: (string) ($validated['label'] ?? 'Evidence attachment'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $control['organization_id'],
        scopeId: $control['scope_id'] ?? null,
        metadata: [
            'plugin' => 'controls-catalog',
            'framework' => $control['framework'],
            'control_name' => $control['name'],
        ],
    ));

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.controls-catalog.root',
        'principal_id' => $principalId,
        'organization_id' => $control['organization_id'],
        'scope_id' => $control['scope_id'] ?? null,
        'locale' => $locale,
    ]));
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.artifacts.store');

Route::post('/plugins/controls/{controlId}/transitions/{transitionKey}', function (
    Request $request,
    string $controlId,
    string $transitionKey,
    WorkflowServiceInterface $workflows
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    $workflows->transition(
        workflowKey: 'plugin.controls-catalog.control-lifecycle',
        subjectType: 'control',
        subjectId: $controlId,
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
        'menu' => 'plugin.controls-catalog.root',
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
    ]));
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.transition');
