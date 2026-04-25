<?php

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Plugins\ControlsCatalog\ControlsCatalogRepository;
use PymeSec\Plugins\ControlsCatalog\FrameworkOnboardingService;

Route::get('/plugins/controls', function (Request $request, ControlsCatalogRepository $repository, ObjectAccessService $objectAccess) {
    $organizationId = (string) $request->query('organization_id', 'org-a');

    return response()->json([
        'plugin' => 'controls-catalog',
        'controls' => $objectAccess->filterRecords(
            $repository->all($organizationId, $request->query('scope_id')),
            'id',
            is_string($request->query('principal_id')) ? (string) $request->query('principal_id') : null,
            $organizationId,
            is_string($request->query('scope_id')) ? (string) $request->query('scope_id') : null,
            'control',
        ),
    ]);
})->middleware('core.permission:plugin.controls-catalog.controls.view')->name('plugin.controls-catalog.index');

Route::get('/plugins/controls/reviews', function (Request $request, ControlsCatalogRepository $repository, ObjectAccessService $objectAccess) {
    $organizationId = (string) $request->query('organization_id', 'org-a');

    return response()->json([
        'plugin' => 'controls-catalog',
        'review_controls' => $objectAccess->filterRecords(
            $repository->all($organizationId, $request->query('scope_id')),
            'id',
            is_string($request->query('principal_id')) ? (string) $request->query('principal_id') : null,
            $organizationId,
            is_string($request->query('scope_id')) ? (string) $request->query('scope_id') : null,
            'control',
        ),
    ]);
})->middleware('core.permission:plugin.controls-catalog.controls.view')->name('plugin.controls-catalog.reviews');

Route::get('/plugins/controls/framework-adoption', function (Request $request, ControlsCatalogRepository $repository) {
    $organizationId = (string) $request->query('organization_id', 'org-a');
    $scopeId = is_string($request->query('scope_id')) ? (string) $request->query('scope_id') : null;

    return response()->json([
        'plugin' => 'controls-catalog',
        'frameworks' => $repository->frameworks($organizationId),
        'adoptions' => array_values($repository->frameworkAdoptionMap($organizationId, $scopeId)),
        'requirements' => $repository->requirements($organizationId),
    ]);
})->middleware('core.permission:plugin.controls-catalog.controls.view')->name('plugin.controls-catalog.framework-adoption');

Route::post('/plugins/controls', function (
    Request $request,
    ControlsCatalogRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:120'],
        'framework_id' => ['nullable', 'string', 'max:64'],
        'framework' => ['nullable', 'string', 'max:80'],
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
        $actors->assignActor(
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
        'menu' => 'plugin.controls-catalog.catalog',
        'principal_id' => $principalId,
        'organization_id' => $control['organization_id'],
        'control_id' => $control['id'],
        'scope_id' => $control['scope_id'] !== '' ? $control['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.store');

Route::post('/plugins/controls/frameworks', function (
    Request $request,
    ControlsCatalogRepository $repository
) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'code' => ['required', 'string', 'max:40'],
        'name' => ['required', 'string', 'max:120'],
        'description' => ['nullable', 'string', 'max:500'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $scopeId = $request->input('scope_id');
    $menu = is_string($request->input('menu')) && $request->input('menu') !== ''
        ? (string) $request->input('menu')
        : 'plugin.controls-catalog.catalog';

    $repository->createFramework($validated);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => $menu,
        'principal_id' => $principalId,
        'organization_id' => $validated['organization_id'],
        'control_id' => is_string($request->input('control_id')) && $request->input('control_id') !== '' ? (string) $request->input('control_id') : null,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.frameworks.store');

Route::post('/plugins/controls/frameworks/{frameworkId}/adoption', function (
    Request $request,
    string $frameworkId,
    ControlsCatalogRepository $repository,
    ArtifactServiceInterface $artifacts,
) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'status' => ['required', 'in:active,in-progress,inactive'],
        'target_level' => ['nullable', 'in:basic,medium,high'],
        'adopted_at' => ['nullable', 'date'],
        'change_reason' => ['required', 'string', 'max:1000'],
        'mandate_document' => ['nullable', 'file', 'max:10240'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $menu = is_string($request->input('menu')) && $request->input('menu') !== ''
        ? (string) $request->input('menu')
        : 'plugin.controls-catalog.framework-adoption';
    $scopeId = is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== ''
        ? (string) $validated['scope_id']
        : null;
    $existingAdoption = $repository->findFrameworkAdoption(
        organizationId: (string) $validated['organization_id'],
        frameworkId: $frameworkId,
        scopeId: $scopeId,
    );
    $hasExistingMandate = false;

    if ($existingAdoption !== null) {
        $hasExistingMandate = $artifacts->latest(1, array_filter([
            'subject_type' => 'framework-adoption',
            'subject_id' => $existingAdoption['id'],
            'artifact_type' => 'mandate-document',
            'organization_id' => (string) $validated['organization_id'],
            'scope_id' => $scopeId,
        ], static fn (mixed $value): bool => is_string($value) && $value !== '')) !== [];
    }

    if (($validated['status'] ?? null) === 'active' && ! $request->hasFile('mandate_document') && ! $hasExistingMandate) {
        throw ValidationException::withMessages([
            'mandate_document' => 'Upload the signed mandate document before activating framework adoption.',
        ]);
    }

    $existingStatus = $existingAdoption['status'] ?? null;

    $adoption = $repository->upsertFrameworkAdoption(
        organizationId: (string) $validated['organization_id'],
        frameworkId: $frameworkId,
        data: [
            ...$validated,
            'requested_by_principal_id' => ($existingAdoption['requested_by_principal_id'] ?? '') !== ''
                ? (string) $existingAdoption['requested_by_principal_id']
                : $principalId,
            'approved_by_principal_id' => in_array($validated['status'], ['active', 'inactive'], true) && $existingStatus !== $validated['status']
                ? $principalId
                : ($existingAdoption['approved_by_principal_id'] ?? null),
            'approved_at' => in_array($validated['status'], ['active', 'inactive'], true) && $existingStatus !== $validated['status']
                ? now()->toDateTimeString()
                : ($existingAdoption['approved_at'] ?? null),
            'retired_at' => $validated['status'] === 'inactive'
                ? now()->toDateTimeString()
                : null,
        ],
    );

    if ($request->hasFile('mandate_document') && $adoption !== null) {
        $framework = $repository->findFramework((string) $validated['organization_id'], $frameworkId);

        $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'controls-catalog',
            subjectType: 'framework-adoption',
            subjectId: $adoption['id'],
            artifactType: 'mandate-document',
            label: 'Signed mandate document',
            file: $validated['mandate_document'],
            principalId: $principalId,
            membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
            organizationId: (string) $validated['organization_id'],
            scopeId: $scopeId,
            metadata: [
                'plugin' => 'controls-catalog',
                'framework_id' => $frameworkId,
                'framework_name' => $framework['name'] ?? $frameworkId,
                'adoption_status' => $validated['status'],
            ],
        ));
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => $menu,
        'principal_id' => $principalId,
        'organization_id' => $validated['organization_id'],
        'scope_id' => $scopeId,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Framework adoption updated.');
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.frameworks.adoption.upsert');

Route::post('/plugins/controls/frameworks/{frameworkId}/onboarding/apply', function (
    Request $request,
    string $frameworkId,
    ControlsCatalogRepository $repository,
    FrameworkOnboardingService $onboarding,
    \PymeSec\Plugins\FrameworkPlatform\Contracts\FrameworkPlatformRegistryInterface $platforms,
) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $scopeId = is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== ''
        ? (string) $validated['scope_id']
        : null;
    $adoption = $repository->findFrameworkAdoption((string) $validated['organization_id'], $frameworkId, $scopeId);

    abort_if($adoption === null, 404, 'Adopt the framework before applying its onboarding kit.');
    abort_unless(in_array($adoption['status'] ?? '', ['active', 'in-progress'], true), 422, 'Only active or in-progress adoptions can apply onboarding kits.');

    $platform = $platforms->definition($frameworkId);
    abort_if(! is_array($platform), 404, 'No onboarding kit is published for this framework.');

    $result = $onboarding->apply(
        frameworkId: $frameworkId,
        organizationId: (string) $validated['organization_id'],
        scopeId: $scopeId,
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        platform: $platform,
    );

    $repository->markFrameworkStarterPackApplied(
        organizationId: (string) $validated['organization_id'],
        frameworkId: $frameworkId,
        scopeId: $scopeId,
        starterPackVersion: (string) ($result['onboarding_version'] ?? '1'),
        principalId: $principalId,
    );

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.controls-catalog.framework-adoption',
        'principal_id' => $principalId,
        'organization_id' => $validated['organization_id'],
        'scope_id' => $scopeId,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', sprintf(
        'Starter pack applied: %d controls, %d policies, %d mappings.',
        count($result['created_controls'] ?? []),
        count($result['created_policies'] ?? []),
        (int) ($result['attached_mapping_count'] ?? 0),
    ));
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.frameworks.onboarding.apply');

Route::post('/plugins/controls/requirements', function (
    Request $request,
    ControlsCatalogRepository $repository
) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'framework_id' => ['required', 'string', 'max:64'],
        'code' => ['required', 'string', 'max:60'],
        'title' => ['required', 'string', 'max:160'],
        'description' => ['nullable', 'string', 'max:500'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $scopeId = $request->input('scope_id');
    $menu = is_string($request->input('menu')) && $request->input('menu') !== ''
        ? (string) $request->input('menu')
        : 'plugin.controls-catalog.catalog';

    $repository->createRequirement($validated);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => $menu,
        'principal_id' => $principalId,
        'organization_id' => $validated['organization_id'],
        'control_id' => is_string($request->input('control_id')) && $request->input('control_id') !== '' ? (string) $request->input('control_id') : null,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.requirements.store');

Route::post('/plugins/controls/{controlId}/requirements', function (
    Request $request,
    string $controlId,
    ControlsCatalogRepository $repository,
    ObjectAccessService $objectAccess,
) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'requirement_id' => ['required', 'string', 'max:64'],
        'coverage' => ['nullable', 'in:supports,partial,full'],
        'notes' => ['nullable', 'string', 'max:500'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $scopeId = $request->input('scope_id');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: (string) $validated['organization_id'],
        scopeId: is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        domainObjectType: 'control',
        domainObjectId: $controlId,
    ), 403);

    $repository->attachRequirement(
        controlId: $controlId,
        requirementId: (string) $validated['requirement_id'],
        organizationId: (string) $validated['organization_id'],
        coverage: (string) ($validated['coverage'] ?? 'supports'),
        notes: $validated['notes'] ?? null,
    );

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.controls-catalog.catalog',
        'principal_id' => $principalId,
        'organization_id' => $validated['organization_id'],
        'control_id' => $controlId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.requirements.attach');

Route::post('/plugins/controls/{controlId}', function (
    Request $request,
    string $controlId,
    ControlsCatalogRepository $repository,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) {
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:120'],
        'framework_id' => ['nullable', 'string', 'max:64'],
        'framework' => ['nullable', 'string', 'max:80'],
        'domain' => ['required', 'string', 'max:80'],
        'evidence' => ['required', 'string', 'max:500'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $organizationId = (string) $request->input('organization_id', 'org-a');
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $organizationId,
        scopeId: is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
        domainObjectType: 'control',
        domainObjectId: $controlId,
    ), 403);

    $control = $repository->update($controlId, [
        ...$validated,
        'organization_id' => $organizationId,
    ]);

    abort_if($control === null, 404);

    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->assignActor(
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

    DB::table('functional_assignments')
        ->where('domain_object_type', 'control')
        ->where('domain_object_id', $control['id'])
        ->where('organization_id', $control['organization_id'])
        ->where('is_active', true)
        ->update([
            'scope_id' => $control['scope_id'] !== '' ? $control['scope_id'] : null,
            'updated_at' => now(),
        ]);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.controls-catalog.catalog',
        'principal_id' => $principalId,
        'organization_id' => $control['organization_id'],
        'control_id' => $control['id'],
        'scope_id' => $control['scope_id'] !== '' ? $control['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.update');

Route::post('/plugins/controls/{controlId}/owners/{assignmentId}/remove', function (
    Request $request,
    string $controlId,
    string $assignmentId,
    ControlsCatalogRepository $repository,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) {
    $control = $repository->find($controlId);

    abort_if($control === null, 404);
    abort_unless($objectAccess->canAccessObject(
        principalId: (string) $request->input('principal_id', 'principal-org-a'),
        organizationId: $control['organization_id'],
        scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
        domainObjectType: 'control',
        domainObjectId: $control['id'],
    ), 403);

    $assignment = collect($actors->assignmentsFor(
        domainObjectType: 'control',
        domainObjectId: $control['id'],
        organizationId: $control['organization_id'],
        scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
    ))->first(fn ($candidate) => $candidate->id === $assignmentId && $candidate->assignmentType === 'owner');

    abort_if($assignment === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $actors->deactivateAssignment(
        assignmentId: $assignmentId,
        deactivatedByPrincipalId: $principalId,
    );

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.controls-catalog.catalog',
        'principal_id' => $principalId,
        'organization_id' => $control['organization_id'],
        'control_id' => $control['id'],
        'scope_id' => $control['scope_id'] !== '' ? $control['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Owner removed.');
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.owners.destroy');

Route::post('/plugins/controls/{controlId}/artifacts', function (
    Request $request,
    string $controlId,
    ControlsCatalogRepository $repository,
    ArtifactServiceInterface $artifacts,
    ObjectAccessService $objectAccess,
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
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $control['organization_id'],
        scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
        domainObjectType: 'control',
        domainObjectId: $controlId,
    ), 403);

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
        'menu' => 'plugin.controls-catalog.catalog',
        'principal_id' => $principalId,
        'organization_id' => $control['organization_id'],
        'control_id' => $controlId,
        'scope_id' => $control['scope_id'] ?? null,
        'locale' => $locale,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.artifacts.store');

Route::post('/plugins/controls/{controlId}/transitions/{transitionKey}', function (
    Request $request,
    string $controlId,
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
        domainObjectType: 'control',
        domainObjectId: $controlId,
    ), 403);

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
        'menu' => 'plugin.controls-catalog.catalog',
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'control_id' => $controlId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.controls-catalog.controls.manage')->name('plugin.controls-catalog.transition');
