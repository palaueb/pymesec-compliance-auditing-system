<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Plugins\AssetCatalog\AssetCatalogRepository;
use PymeSec\Plugins\AssetCatalog\AssetReferenceData;

Route::get('/plugins/assets', function (Request $request) {
    return redirect()->route('core.shell.index', [
        'menu' => 'plugin.asset-catalog.root',
        'principal_id' => $request->query('principal_id', 'principal-org-a'),
        'organization_id' => $request->query('organization_id', 'org-a'),
        'locale' => $request->query('locale', app()->getLocale()),
        'membership_ids' => $request->query('membership_ids', ['membership-org-a-hello']),
        'scope_id' => $request->query('scope_id'),
    ])->with('status', 'Saved.');
})->middleware('core.permission:plugin.asset-catalog.assets.view')->name('plugin.asset-catalog.index');

Route::get('/plugins/assets/lifecycle', function (Request $request) {
    return redirect()->route('core.shell.index', [
        'menu' => 'plugin.asset-catalog.lifecycle',
        'principal_id' => $request->query('principal_id', 'principal-org-a'),
        'organization_id' => $request->query('organization_id', 'org-a'),
        'locale' => $request->query('locale', app()->getLocale()),
        'membership_ids' => $request->query('membership_ids', ['membership-org-a-hello']),
        'scope_id' => $request->query('scope_id'),
    ])->with('status', 'Saved.');
})->middleware('core.permission:plugin.asset-catalog.assets.view')->name('plugin.asset-catalog.lifecycle');

Route::post('/plugins/assets', function (
    Request $request,
    AssetCatalogRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:160'],
        'type' => ['required', 'string', Rule::in(AssetReferenceData::typeKeys())],
        'criticality' => ['required', 'string', Rule::in(AssetReferenceData::criticalityKeys())],
        'classification' => ['required', 'string', Rule::in(AssetReferenceData::classificationKeys())],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $asset = $repository->create($validated);
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'asset',
            domainObjectId: $asset['id'],
            assignmentType: 'owner',
            organizationId: $asset['organization_id'],
            scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
            metadata: ['source' => 'asset-catalog'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.asset-catalog.root',
        'asset_id' => $asset['id'],
        'principal_id' => $principalId,
        'organization_id' => $asset['organization_id'],
        'scope_id' => $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
        'locale' => $request->input('locale', app()->getLocale()),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.asset-catalog.assets.manage')->name('plugin.asset-catalog.store');

Route::post('/plugins/assets/{assetId}', function (
    Request $request,
    string $assetId,
    AssetCatalogRepository $repository,
    FunctionalActorServiceInterface $actors,
    ObjectAccessService $objectAccess,
) {
    $existingAsset = $repository->find($assetId);

    abort_if($existingAsset === null, 404);
    abort_unless($objectAccess->canAccessObject(
        principalId: (string) $request->input('principal_id', 'principal-org-a'),
        organizationId: $existingAsset['organization_id'],
        scopeId: $existingAsset['scope_id'] !== '' ? $existingAsset['scope_id'] : null,
        domainObjectType: 'asset',
        domainObjectId: $existingAsset['id'],
    ), 403);

    $validated = $request->validate([
        'name' => ['required', 'string', 'max:160'],
        'type' => ['required', 'string', Rule::in(AssetReferenceData::typeKeys())],
        'criticality' => ['required', 'string', Rule::in(AssetReferenceData::criticalityKeys())],
        'classification' => ['required', 'string', Rule::in(AssetReferenceData::classificationKeys())],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $asset = $repository->update($assetId, [
        ...$validated,
        'organization_id' => (string) $request->input('organization_id', 'org-a'),
    ]);

    abort_if($asset === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'asset',
            domainObjectId: $asset['id'],
            assignmentType: 'owner',
            organizationId: $asset['organization_id'],
            scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
            metadata: ['source' => 'asset-catalog'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.asset-catalog.root',
        'asset_id' => $asset['id'],
        'principal_id' => $principalId,
        'organization_id' => $asset['organization_id'],
        'scope_id' => $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
        'locale' => $request->input('locale', app()->getLocale()),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Saved.');
})->middleware('core.permission:plugin.asset-catalog.assets.manage')->name('plugin.asset-catalog.update');

Route::post('/plugins/assets/{assetId}/transitions/{transitionKey}', function (
    string $assetId,
    string $transitionKey,
    Request $request,
    WorkflowServiceInterface $workflows,
    AssetCatalogRepository $repository,
    ObjectAccessService $objectAccess,
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');
    $membershipId = $request->input('membership_id', 'membership-org-a-hello');
    $menu = (string) $request->input('menu', 'plugin.asset-catalog.root');
    $locale = (string) $request->input('locale', app()->getLocale());
    $asset = $repository->find($assetId);

    abort_if($asset === null, 404);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: $asset['organization_id'],
        scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
        domainObjectType: 'asset',
        domainObjectId: $asset['id'],
    ), 403);

    $workflows->transition(
        workflowKey: 'plugin.asset-catalog.asset-lifecycle',
        subjectType: 'asset',
        subjectId: $assetId,
        transitionKey: $transitionKey,
        context: new WorkflowExecutionContext(
            principal: new PrincipalReference(
                id: $principalId,
                provider: 'demo',
            ),
            memberships: [
                new MembershipReference(
                    id: is_string($membershipId) ? $membershipId : 'membership-org-a-hello',
                    principalId: $principalId,
                    organizationId: $organizationId,
                ),
            ],
            organizationId: $organizationId,
            scopeId: is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
            membershipId: is_string($membershipId) ? $membershipId : 'membership-org-a-hello',
        ),
    );

    return redirect()->route('core.shell.index', [
        'menu' => $menu,
        'asset_id' => $assetId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'locale' => $locale,
        'membership_ids' => [is_string($membershipId) ? $membershipId : 'membership-org-a-hello'],
        'scope_id' => $scopeId,
    ])->with('status', 'Saved.');
})->middleware('core.permission:plugin.asset-catalog.assets.manage')->name('plugin.asset-catalog.transition');
