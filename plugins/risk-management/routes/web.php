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
use PymeSec\Plugins\RiskManagement\RiskRepository;

Route::get('/plugins/risks', function (Request $request, RiskRepository $repository) {
    return response()->json([
        'plugin' => 'risk-management',
        'risks' => $repository->all(
            (string) $request->query('organization_id', 'org-a'),
            $request->query('scope_id'),
        ),
    ]);
})->name('plugin.risk-management.index');

Route::get('/plugins/risks/board', function (Request $request, RiskRepository $repository) {
    return response()->json([
        'plugin' => 'risk-management',
        'board' => $repository->all(
            (string) $request->query('organization_id', 'org-a'),
            $request->query('scope_id'),
        ),
    ]);
})->name('plugin.risk-management.board');

Route::post('/plugins/risks', function (
    Request $request,
    RiskRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'category' => ['required', 'string', 'max:80'],
        'inherent_score' => ['required', 'integer', 'min:0', 'max:100'],
        'residual_score' => ['required', 'integer', 'min:0', 'max:100'],
        'linked_asset_id' => ['nullable', 'string', 'max:120', 'exists:assets,id'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'treatment' => ['required', 'string', 'max:800'],
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $risk = $repository->create($validated);
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'risk',
            domainObjectId: $risk['id'],
            assignmentType: 'owner',
            organizationId: $risk['organization_id'],
            scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
            metadata: ['source' => 'risk-management'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.risk-management.root',
        'principal_id' => $principalId,
        'organization_id' => $risk['organization_id'],
        'scope_id' => $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.risk-management.risks.manage')->name('plugin.risk-management.store');

Route::post('/plugins/risks/{riskId}', function (
    Request $request,
    string $riskId,
    RiskRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:140'],
        'category' => ['required', 'string', 'max:80'],
        'inherent_score' => ['required', 'integer', 'min:0', 'max:100'],
        'residual_score' => ['required', 'integer', 'min:0', 'max:100'],
        'linked_asset_id' => ['nullable', 'string', 'max:120', 'exists:assets,id'],
        'linked_control_id' => ['nullable', 'string', 'max:120'],
        'treatment' => ['required', 'string', 'max:800'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'owner_actor_id' => ['nullable', 'string', 'max:64'],
    ]);

    $risk = $repository->update($riskId, [
        ...$validated,
        'organization_id' => (string) $request->input('organization_id', 'org-a'),
    ]);

    abort_if($risk === null, 404);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
        $actors->syncSingleAssignment(
            actorId: $validated['owner_actor_id'],
            domainObjectType: 'risk',
            domainObjectId: $risk['id'],
            assignmentType: 'owner',
            organizationId: $risk['organization_id'],
            scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
            metadata: ['source' => 'risk-management'],
            assignedByPrincipalId: $principalId,
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.risk-management.root',
        'principal_id' => $principalId,
        'organization_id' => $risk['organization_id'],
        'scope_id' => $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]));
})->middleware('core.permission:plugin.risk-management.risks.manage')->name('plugin.risk-management.update');

Route::post('/plugins/risks/{riskId}/artifacts', function (
    Request $request,
    string $riskId,
    RiskRepository $repository,
    ArtifactServiceInterface $artifacts
) {
    $risk = $repository->find($riskId);

    abort_if($risk === null, 404);

    $validated = $request->validate([
        'artifact' => ['required', 'file', 'max:10240'],
        'label' => ['nullable', 'string', 'max:120'],
        'artifact_type' => ['nullable', 'string', 'max:60'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');

    $artifacts->store(new ArtifactUploadData(
        ownerComponent: 'risk-management',
        subjectType: 'risk',
        subjectId: $riskId,
        artifactType: (string) ($validated['artifact_type'] ?? 'evidence'),
        label: (string) ($validated['label'] ?? 'Risk evidence'),
        file: $validated['artifact'],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $risk['organization_id'],
        scopeId: $risk['scope_id'] ?? null,
        metadata: [
            'plugin' => 'risk-management',
            'category' => $risk['category'],
            'linked_asset_id' => $risk['linked_asset_id'],
            'linked_control_id' => $risk['linked_control_id'],
        ],
    ));

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.risk-management.root',
        'principal_id' => $principalId,
        'organization_id' => $risk['organization_id'],
        'scope_id' => $risk['scope_id'] ?? null,
        'locale' => $request->input('locale', 'en'),
    ]));
})->middleware('core.permission:plugin.risk-management.risks.manage')->name('plugin.risk-management.artifacts.store');

Route::post('/plugins/risks/{riskId}/transitions/{transitionKey}', function (
    Request $request,
    string $riskId,
    string $transitionKey,
    WorkflowServiceInterface $workflows
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    $workflows->transition(
        workflowKey: 'plugin.risk-management.risk-lifecycle',
        subjectType: 'risk',
        subjectId: $riskId,
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
        'menu' => 'plugin.risk-management.root',
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
    ]));
})->middleware('core.permission:plugin.risk-management.risks.manage')->name('plugin.risk-management.transition');
