<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;

Route::get('/plugins/assets', function (Request $request) {
    return redirect()->route('core.shell.index', [
        'menu' => 'plugin.asset-catalog.root',
        'principal_id' => $request->query('principal_id', 'principal-org-a'),
        'organization_id' => $request->query('organization_id', 'org-a'),
        'locale' => $request->query('locale', app()->getLocale()),
        'membership_ids' => $request->query('membership_ids', ['membership-org-a-hello']),
        'scope_id' => $request->query('scope_id'),
    ]);
})->name('plugin.asset-catalog.index');

Route::get('/plugins/assets/lifecycle', function (Request $request) {
    return redirect()->route('core.shell.index', [
        'menu' => 'plugin.asset-catalog.lifecycle',
        'principal_id' => $request->query('principal_id', 'principal-org-a'),
        'organization_id' => $request->query('organization_id', 'org-a'),
        'locale' => $request->query('locale', app()->getLocale()),
        'membership_ids' => $request->query('membership_ids', ['membership-org-a-hello']),
        'scope_id' => $request->query('scope_id'),
    ]);
})->name('plugin.asset-catalog.lifecycle');

Route::post('/plugins/assets/{assetId}/transitions/{transitionKey}', function (
    string $assetId,
    string $transitionKey,
    Request $request,
    WorkflowServiceInterface $workflows
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');
    $membershipId = $request->input('membership_id', 'membership-org-a-hello');
    $menu = (string) $request->input('menu', 'plugin.asset-catalog.root');
    $locale = (string) $request->input('locale', app()->getLocale());

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
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'locale' => $locale,
        'membership_ids' => [is_string($membershipId) ? $membershipId : 'membership-org-a-hello'],
        'scope_id' => $scopeId,
    ]);
})->middleware('core.permission:plugin.asset-catalog.assets.manage')->name('plugin.asset-catalog.transition');
