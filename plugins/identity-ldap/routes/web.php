<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PymeSec\Plugins\IdentityLdap\IdentityLdapRepository;
use PymeSec\Plugins\IdentityLdap\IdentityLdapService;

Route::get('/plugins/identity/ldap', function (Request $request, IdentityLdapRepository $repository) {
    $organizationId = (string) $request->query('organization_id', 'org-a');
    $connection = $repository->connectionForOrganization($organizationId);

    return response()->json([
        'plugin' => 'identity-ldap',
        'connection' => $connection,
        'mappings' => $connection !== null ? $repository->mappingsForConnection((string) $connection['id']) : [],
        'cached_users' => $repository->cachedUsersForOrganization($organizationId),
    ]);
})->middleware('core.permission:plugin.identity-ldap.directory.view')->name('plugin.identity-ldap.directory.index');

Route::post('/plugins/identity/ldap/connection', function (Request $request, IdentityLdapRepository $repository) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'name' => ['required', 'string', 'max:120'],
        'host' => ['required', 'string', 'max:190'],
        'port' => ['required', 'integer', 'min:1', 'max:65535'],
        'base_dn' => ['required', 'string', 'max:190'],
        'bind_dn' => ['nullable', 'string', 'max:190'],
        'bind_password' => ['nullable', 'string', 'max:255'],
        'user_dn_attribute' => ['nullable', 'string', 'max:64'],
        'mail_attribute' => ['nullable', 'string', 'max:64'],
        'display_name_attribute' => ['nullable', 'string', 'max:64'],
        'job_title_attribute' => ['nullable', 'string', 'max:64'],
        'group_attribute' => ['nullable', 'string', 'max:64'],
        'login_mode' => ['required', 'string', 'in:username,email'],
        'sync_interval_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
        'user_filter' => ['nullable', 'string', 'max:500'],
    ]);

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipIds = $request->input('membership_ids', []);

    $repository->upsertConnection((string) $validated['organization_id'], [
        ...$validated,
        'fallback_email_enabled' => $request->boolean('fallback_email_enabled'),
        'is_enabled' => $request->boolean('is_enabled'),
    ], $requesterPrincipalId);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.identity-ldap.directory',
        'principal_id' => $requesterPrincipalId,
        'organization_id' => $validated['organization_id'],
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_array($membershipIds) && $membershipIds !== [] ? $membershipIds : null,
    ]))->with('status', 'LDAP connector settings updated.');
})->middleware('core.permission:plugin.identity-ldap.directory.manage')->name('plugin.identity-ldap.connection.store');

Route::post('/plugins/identity/ldap/mappings', function (Request $request, IdentityLdapRepository $repository) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'ldap_group' => ['required', 'string', 'max:190'],
        'role_keys' => ['nullable', 'array'],
        'role_keys.*' => ['string', 'max:120'],
        'scope_ids' => ['nullable', 'array'],
        'scope_ids.*' => ['string', 'max:64'],
    ]);

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipIds = $request->input('membership_ids', []);
    $connection = $repository->connectionForOrganization((string) $validated['organization_id']);

    abort_if($connection === null, 404);

    $repository->upsertMapping((string) $connection['id'], [
        'ldap_group' => $validated['ldap_group'],
        'role_keys' => $validated['role_keys'] ?? [],
        'scope_ids' => $validated['scope_ids'] ?? [],
        'is_active' => $request->boolean('is_active', true),
    ], $requesterPrincipalId);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.identity-ldap.directory',
        'principal_id' => $requesterPrincipalId,
        'organization_id' => $validated['organization_id'],
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_array($membershipIds) && $membershipIds !== [] ? $membershipIds : null,
    ]))->with('status', 'LDAP group mapping saved.');
})->middleware('core.permission:plugin.identity-ldap.directory.manage')->name('plugin.identity-ldap.mappings.store');

Route::post('/plugins/identity/ldap/sync', function (Request $request, IdentityLdapService $service) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
    ]);

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipIds = $request->input('membership_ids', []);

    try {
        $result = $service->syncOrganization((string) $validated['organization_id'], $requesterPrincipalId);

        return redirect()->route('core.shell.index', array_filter([
            'menu' => 'plugin.identity-ldap.directory',
            'principal_id' => $requesterPrincipalId,
            'organization_id' => $validated['organization_id'],
            'locale' => $request->input('locale', 'en'),
            'membership_ids' => is_array($membershipIds) && $membershipIds !== [] ? $membershipIds : null,
        ]))->with('status', (string) ($result['message'] ?? 'LDAP directory synchronized.'));
    } catch (Throwable $exception) {
        return redirect()->route('core.shell.index', array_filter([
            'menu' => 'plugin.identity-ldap.directory',
            'principal_id' => $requesterPrincipalId,
            'organization_id' => $validated['organization_id'],
            'locale' => $request->input('locale', 'en'),
            'membership_ids' => is_array($membershipIds) && $membershipIds !== [] ? $membershipIds : null,
        ]))->with('error', $exception->getMessage());
    }
})->middleware('core.permission:plugin.identity-ldap.directory.manage')->name('plugin.identity-ldap.sync.store');
