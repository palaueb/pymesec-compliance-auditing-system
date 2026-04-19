<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PymeSec\Plugins\IdentityLdap\IdentityLdapRepository;
use PymeSec\Plugins\IdentityLdap\IdentityLdapService;

$apiContext = require base_path('routes/api_context.php');
extract($apiContext, EXTR_SKIP);

Route::post('/identity-ldap/connection', function (
    Request $request,
    IdentityLdapRepository $ldap,
) use ($apiPrincipalId, $apiSuccess) {
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

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $connection = $ldap->upsertConnection((string) $validated['organization_id'], [
        ...$validated,
        'fallback_email_enabled' => $request->boolean('fallback_email_enabled'),
        'is_enabled' => $request->boolean('is_enabled'),
    ], $principalId);

    return $apiSuccess($connection);
})->defaults('_openapi', [
    'operation_id' => 'identityLdapSaveConnection',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Create or update LDAP connection settings',
    'responses' => [
        '200' => [
            'description' => 'LDAP connection saved',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_rules' => [
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
    ],
])->middleware('core.permission:plugin.identity-ldap.directory.manage');

Route::post('/identity-ldap/mappings', function (
    Request $request,
    IdentityLdapRepository $ldap,
) use ($apiPrincipalId, $apiSuccess) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'ldap_group' => ['required', 'string', 'max:190'],
        'role_keys' => ['nullable', 'array'],
        'role_keys.*' => ['string', 'max:120'],
        'scope_ids' => ['nullable', 'array'],
        'scope_ids.*' => ['string', 'max:64'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $connection = $ldap->connectionForOrganization((string) $validated['organization_id']);
    abort_if($connection === null, 404);

    $mapping = $ldap->upsertMapping((string) $connection['id'], [
        'ldap_group' => $validated['ldap_group'],
        'role_keys' => $validated['role_keys'] ?? [],
        'scope_ids' => $validated['scope_ids'] ?? [],
        'is_active' => $request->boolean('is_active', true),
    ], $principalId);

    return $apiSuccess($mapping);
})->defaults('_openapi', [
    'operation_id' => 'identityLdapSaveGroupMapping',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Create or update one LDAP group mapping',
    'responses' => [
        '200' => [
            'description' => 'LDAP group mapping saved',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'LDAP connection not found for organization',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['required', 'string', 'max:64'],
        'ldap_group' => ['required', 'string', 'max:190'],
        'role_keys' => ['nullable', 'array'],
        'role_keys.*' => ['string', 'max:120'],
        'scope_ids' => ['nullable', 'array'],
        'scope_ids.*' => ['string', 'max:64'],
    ],
    'lookup_fields' => [
        'scope_ids' => '/api/v1/lookups/scopes/options',
    ],
])->middleware('core.permission:plugin.identity-ldap.directory.manage');

Route::post('/identity-ldap/sync', function (
    Request $request,
    IdentityLdapService $ldap,
) use ($apiPrincipalId, $apiSuccess) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    try {
        $result = $ldap->syncOrganization((string) $validated['organization_id'], $principalId);
    } catch (Throwable $exception) {
        throw ValidationException::withMessages([
            'organization_id' => $exception->getMessage(),
        ]);
    }

    return $apiSuccess($result);
})->defaults('_openapi', [
    'operation_id' => 'identityLdapRunSync',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Run one LDAP synchronization cycle',
    'responses' => [
        '200' => [
            'description' => 'LDAP synchronization completed',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '422' => [
            'description' => 'Validation failed or sync error',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['required', 'string', 'max:64'],
    ],
])->middleware('core.permission:plugin.identity-ldap.directory.manage');
