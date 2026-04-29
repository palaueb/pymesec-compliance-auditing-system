<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Plugins\IdentityLdap\IdentityLdapService;
use PymeSec\Plugins\IdentityLocal\IdentityLocalAuthService;
use PymeSec\Plugins\IdentityLocal\IdentityLocalRepository;
use PymeSec\Plugins\IdentityLocal\IdentityUserImportService;

$apiContext = require base_path('routes/api_context.php');
extract($apiContext, EXTR_SKIP);

$identityUserApiPayload = static function (array $user): array {
    unset($user['password_hash']);

    return $user;
};

Route::get('/identity-local/users', function (
    Request $request,
    IdentityLocalRepository $identity,
) use ($apiSuccess, $identityUserApiPayload) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);

    $query = trim((string) $request->query('query', ''));
    $users = array_values(array_filter(
        $identity->usersForOrganization($organizationId),
        static function (array $user) use ($query): bool {
            if (! (bool) ($user['is_active'] ?? false)) {
                return false;
            }

            if ($query === '') {
                return true;
            }

            $haystack = implode(' ', [
                (string) ($user['principal_id'] ?? ''),
                (string) ($user['username'] ?? ''),
                (string) ($user['display_name'] ?? ''),
                (string) ($user['email'] ?? ''),
            ]);

            return str_contains(strtolower($haystack), strtolower($query));
        },
    ));

    return $apiSuccess(array_map($identityUserApiPayload, $users), [
        'organization_id' => $organizationId,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'identityLocalListUsers',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'List local directory users visible in current organization',
    'responses' => [
        '200' => [
            'description' => 'User list',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '422' => [
            'description' => 'Organization context required',
        ],
    ],
])->middleware('core.permission:plugin.identity-local.users.view');

Route::post('/identity-local/users', function (
    Request $request,
    IdentityLocalRepository $identity,
    FunctionalActorServiceInterface $actors,
) use ($apiPrincipalId, $apiSuccess, $identityUserApiPayload) {
    $validated = $request->validate([
        'display_name' => ['required', 'string', 'max:120'],
        'username' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('identity_local_users', 'username')],
        'email' => ['required', 'email:rfc', 'max:190', Rule::unique('identity_local_users', 'email')],
        'job_title' => ['nullable', 'string', 'max:120'],
        'organization_id' => ['required', 'string', 'max:64'],
        'actor_id' => ['nullable', 'string', 'max:120'],
        'password' => ['nullable', 'string', 'min:8', 'confirmed'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $passwordEnabled = $request->boolean('password_enabled');

    if ($passwordEnabled && ! is_string($validated['password'] ?? null)) {
        throw ValidationException::withMessages([
            'password' => 'A password is required when password sign-in is enabled.',
        ]);
    }

    $user = $identity->createUser([
        ...$validated,
        'password_enabled' => $passwordEnabled,
        'magic_link_enabled' => $request->boolean('magic_link_enabled') || ! $passwordEnabled,
        'is_active' => true,
    ], $principalId);

    if (is_string($validated['actor_id'] ?? null) && $validated['actor_id'] !== '') {
        $actors->linkPrincipal(
            principalId: (string) $user['principal_id'],
            actorId: $validated['actor_id'],
            organizationId: (string) $validated['organization_id'],
            linkedByPrincipalId: $principalId,
        );
    }

    return $apiSuccess($identityUserApiPayload($user));
})->defaults('_openapi', [
    'operation_id' => 'identityLocalCreateUser',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Create one local directory user',
    'responses' => [
        '200' => [
            'description' => 'User created',
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
        'display_name' => ['required', 'string', 'max:120'],
        'username' => ['required', 'string', 'max:120'],
        'email' => ['required', 'string', 'max:190'],
        'job_title' => ['nullable', 'string', 'max:120'],
        'organization_id' => ['required', 'string', 'max:64'],
        'actor_id' => ['nullable', 'string', 'max:120'],
        'password' => ['nullable', 'string', 'min:8'],
    ],
    'lookup_fields' => [
        'actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.identity-local.users.manage');

Route::patch('/identity-local/users/{userId}', function (
    Request $request,
    string $userId,
    IdentityLocalRepository $identity,
    FunctionalActorServiceInterface $actors,
) use ($apiPrincipalId, $apiSuccess, $identityUserApiPayload) {
    $existing = $identity->findUser($userId);
    abort_if($existing === null, 404);

    $validated = $request->validate([
        'display_name' => ['required', 'string', 'max:120'],
        'username' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('identity_local_users', 'username')->ignore($userId, 'id')],
        'email' => ['required', 'email:rfc', 'max:190', Rule::unique('identity_local_users', 'email')->ignore($userId, 'id')],
        'job_title' => ['nullable', 'string', 'max:120'],
        'organization_id' => ['required', 'string', 'max:64'],
        'actor_id' => ['nullable', 'string', 'max:120'],
        'is_active' => ['nullable', 'string', 'in:1'],
        'password' => ['nullable', 'string', 'min:8', 'confirmed'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $passwordEnabled = $request->boolean('password_enabled');

    if ($passwordEnabled
        && ! is_string($validated['password'] ?? null)
        && ! is_string($existing['password_hash'] ?? null)) {
        throw ValidationException::withMessages([
            'password' => 'A password is required before password sign-in can be enabled.',
        ]);
    }

    $user = $identity->updateUser($userId, [
        ...$validated,
        'password_enabled' => $passwordEnabled,
        'magic_link_enabled' => $request->boolean('magic_link_enabled') || ! $passwordEnabled,
        'is_active' => $request->boolean('is_active'),
    ], $principalId);
    abort_if($user === null, 404);

    if (is_string($validated['actor_id'] ?? null) && $validated['actor_id'] !== '') {
        $actors->linkPrincipal(
            principalId: (string) $user['principal_id'],
            actorId: $validated['actor_id'],
            organizationId: (string) $validated['organization_id'],
            linkedByPrincipalId: $principalId,
        );
    }

    return $apiSuccess($identityUserApiPayload($user));
})->defaults('_openapi', [
    'operation_id' => 'identityLocalUpdateUser',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Update one local directory user',
    'responses' => [
        '200' => [
            'description' => 'User updated',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'User not found',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_rules' => [
        'display_name' => ['required', 'string', 'max:120'],
        'username' => ['required', 'string', 'max:120'],
        'email' => ['required', 'string', 'max:190'],
        'job_title' => ['nullable', 'string', 'max:120'],
        'organization_id' => ['required', 'string', 'max:64'],
        'actor_id' => ['nullable', 'string', 'max:120'],
        'is_active' => ['nullable', 'string', 'in:1'],
        'password' => ['nullable', 'string', 'min:8'],
    ],
    'lookup_fields' => [
        'actor_id' => '/api/v1/lookups/actors/options',
    ],
])->middleware('core.permission:plugin.identity-local.users.manage');

Route::post('/identity-local/users/{userId}/delete', function (
    Request $request,
    string $userId,
    IdentityLocalRepository $identity,
) use ($apiPrincipalId, $apiSuccess) {
    $user = $identity->findUser($userId);
    abort_if($user === null, 404);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    if (($user['auth_provider'] ?? 'local') !== 'local') {
        throw ValidationException::withMessages([
            'user_id' => 'Directory-backed people are removed through LDAP sync, not from the local people screen.',
        ]);
    }

    if (($user['principal_id'] ?? null) === $principalId) {
        throw ValidationException::withMessages([
            'user_id' => 'You cannot delete the account that is currently using the workspace.',
        ]);
    }

    abort_unless($identity->deleteUser($userId, $principalId), 404);

    return $apiSuccess([
        'user_id' => $userId,
        'deleted' => true,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'identityLocalDeleteUser',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Delete one local directory user',
    'responses' => [
        '200' => [
            'description' => 'User deleted',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'User not found',
        ],
        '422' => [
            'description' => 'Deletion blocked by policy constraints',
        ],
    ],
    'request_rules' => [
        'organization_id' => ['nullable', 'string', 'max:64'],
    ],
])->middleware('core.permission:plugin.identity-local.users.manage');

Route::post('/identity-local/memberships', function (
    Request $request,
    IdentityLocalRepository $identity,
) use ($apiPrincipalId, $apiSuccess) {
    $validated = $request->validate([
        'subject_principal_id' => ['required', 'string', 'max:64'],
        'organization_id' => ['required', 'string', 'max:64'],
        'role_keys' => ['nullable', 'array'],
        'role_keys.*' => ['string', 'max:120'],
        'scope_ids' => ['nullable', 'array'],
        'scope_ids.*' => ['string', 'max:64'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $membership = $identity->createMembership([
        'principal_id' => $validated['subject_principal_id'],
        'organization_id' => $validated['organization_id'],
        'role_keys' => $validated['role_keys'] ?? [],
        'scope_ids' => $validated['scope_ids'] ?? [],
        'is_active' => true,
    ], $principalId);

    return $apiSuccess($membership);
})->defaults('_openapi', [
    'operation_id' => 'identityLocalCreateMembership',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Create one membership assignment',
    'responses' => [
        '200' => [
            'description' => 'Membership created',
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
        'subject_principal_id' => ['required', 'string', 'max:64'],
        'organization_id' => ['required', 'string', 'max:64'],
        'role_keys' => ['nullable', 'array'],
        'role_keys.*' => ['string', 'max:120'],
        'scope_ids' => ['nullable', 'array'],
        'scope_ids.*' => ['string', 'max:64'],
    ],
    'lookup_fields' => [
        'subject_principal_id' => '/api/v1/lookups/principals/options',
        'scope_ids' => '/api/v1/lookups/scopes/options',
    ],
])->middleware('core.permission:plugin.identity-local.memberships.manage');

Route::patch('/identity-local/memberships/{membershipId}', function (
    Request $request,
    string $membershipId,
    IdentityLocalRepository $identity,
) use ($apiPrincipalId, $apiSuccess) {
    $validated = $request->validate([
        'subject_principal_id' => ['required', 'string', 'max:64'],
        'organization_id' => ['required', 'string', 'max:64'],
        'role_keys' => ['nullable', 'array'],
        'role_keys.*' => ['string', 'max:120'],
        'scope_ids' => ['nullable', 'array'],
        'scope_ids.*' => ['string', 'max:64'],
        'is_active' => ['nullable', 'string', 'in:1'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);

    $membership = $identity->updateMembership($membershipId, [
        'principal_id' => $validated['subject_principal_id'],
        'organization_id' => $validated['organization_id'],
        'role_keys' => $validated['role_keys'] ?? [],
        'scope_ids' => $validated['scope_ids'] ?? [],
        'is_active' => $request->boolean('is_active'),
    ], $principalId);
    abort_if($membership === null, 404);

    return $apiSuccess($membership);
})->defaults('_openapi', [
    'operation_id' => 'identityLocalUpdateMembership',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Update one membership assignment',
    'responses' => [
        '200' => [
            'description' => 'Membership updated',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Membership not found',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_rules' => [
        'subject_principal_id' => ['required', 'string', 'max:64'],
        'organization_id' => ['required', 'string', 'max:64'],
        'role_keys' => ['nullable', 'array'],
        'role_keys.*' => ['string', 'max:120'],
        'scope_ids' => ['nullable', 'array'],
        'scope_ids.*' => ['string', 'max:64'],
        'is_active' => ['nullable', 'string', 'in:1'],
    ],
    'lookup_fields' => [
        'subject_principal_id' => '/api/v1/lookups/principals/options',
        'scope_ids' => '/api/v1/lookups/scopes/options',
    ],
])->middleware('core.permission:plugin.identity-local.memberships.manage');

Route::post('/identity-local/setup', function (
    Request $request,
    IdentityLocalAuthService $auth,
    IdentityLocalRepository $identity,
) use ($apiSuccess) {
    if (! $auth->requiresBootstrap()) {
        throw ValidationException::withMessages([
            'setup' => 'Bootstrap is already completed.',
        ]);
    }

    $requiresOrganizationSetup = ! $identity->firstOrganizationId();
    $validated = $request->validate(array_filter([
        'display_name' => ['required', 'string', 'max:120'],
        'username' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
        'email' => ['required', 'email:rfc', 'max:190'],
        'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        'organization_name' => $requiresOrganizationSetup ? ['required', 'string', 'max:160'] : null,
        'organization_slug' => $requiresOrganizationSetup ? ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'] : null,
        'default_locale' => $requiresOrganizationSetup ? ['required', 'string', 'in:en,es,fr,de'] : null,
        'default_timezone' => $requiresOrganizationSetup ? ['required', 'string', 'max:64', Rule::in(timezone_identifiers_list())] : null,
    ]));

    return $apiSuccess($auth->bootstrapSuperAdmin($validated));
})->defaults('_openapi', [
    'operation_id' => 'identityLocalBootstrapSetup',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Bootstrap the first local administrator and organization settings',
    'responses' => [
        '200' => [
            'description' => 'Bootstrap completed',
        ],
        '422' => [
            'description' => 'Validation failed or bootstrap already completed',
        ],
    ],
    'request_rules' => [
        'display_name' => ['required', 'string', 'max:120'],
        'username' => ['required', 'string', 'max:120'],
        'email' => ['required', 'string', 'max:190'],
        'password' => ['nullable', 'string', 'min:8'],
        'organization_name' => ['nullable', 'string', 'max:160'],
        'organization_slug' => ['nullable', 'string', 'max:160'],
        'default_locale' => ['nullable', 'string', 'in:en,es,fr,de'],
        'default_timezone' => ['nullable', 'string', 'max:64'],
    ],
]);

Route::post('/identity-local/auth/login', function (
    Request $request,
    IdentityLocalAuthService $auth,
) use ($apiSuccess) {
    if ($auth->requiresBootstrap()) {
        throw ValidationException::withMessages([
            'setup' => 'Bootstrap is required before sign-in.',
        ]);
    }

    $validated = $request->validate([
        'login' => ['nullable', 'string', 'max:190'],
        'email' => ['nullable', 'email:rfc', 'max:190'],
        'password' => ['nullable', 'string', 'max:190'],
        'use_email_link' => ['nullable', 'boolean'],
    ]);

    $login = (string) ($validated['login'] ?? $validated['email'] ?? '');
    abort_if(trim($login) === '', 422, 'Login or email is required.');

    if ($request->boolean('use_email_link')) {
        $issued = app()->bound(IdentityLdapService::class)
            ? app(IdentityLdapService::class)->issueMagicLink($login, $request)
            : null;
        $issued ??= $auth->issueMagicLink($login, $request);

        return $apiSuccess([
            'issued' => $issued !== null,
            'provider' => $issued !== null ? (($issued['user']['auth_provider'] ?? 'local') === 'ldap' ? 'identity-ldap' : 'identity-local') : null,
            'principal_id' => $issued['user']['principal_id'] ?? null,
            'delivery' => 'email-link',
        ]);
    }

    $challenge = app()->bound(IdentityLdapService::class)
        ? app(IdentityLdapService::class)->beginPasswordLogin($login, (string) ($validated['password'] ?? ''), $request)
        : null;
    $provider = $challenge !== null ? 'identity-ldap' : 'identity-local';
    $challenge ??= $auth->beginPasswordLogin($login, (string) ($validated['password'] ?? ''), $request);

    if ($challenge === null) {
        throw ValidationException::withMessages([
            'login' => 'The sign-in details are not valid for password access.',
        ]);
    }

    return $apiSuccess([
        'challenge' => 'password-2fa',
        'provider' => $provider,
        'principal_id' => $challenge['user']['principal_id'] ?? null,
        'organization_id' => $challenge['user']['organization_id'] ?? null,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'identityLocalAuthRequest',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Start a local or LDAP sign-in challenge',
    'responses' => [
        '200' => [
            'description' => 'Sign-in challenge started',
        ],
        '422' => [
            'description' => 'Validation failed or credentials rejected',
        ],
    ],
    'request_rules' => [
        'login' => ['nullable', 'string', 'max:190'],
        'email' => ['nullable', 'string', 'max:190'],
        'password' => ['nullable', 'string', 'max:190'],
        'use_email_link' => ['nullable', 'boolean'],
    ],
]);

Route::post('/identity-local/auth/verify', function (
    Request $request,
    IdentityLocalAuthService $auth,
) use ($apiSuccess) {
    $validated = $request->validate([
        'principal_id' => ['required', 'string', 'max:120'],
        'code' => ['required', 'digits:6'],
    ]);

    $user = $auth->consumeEmailCode((string) $validated['principal_id'], (string) $validated['code']);

    if ($user === null) {
        throw ValidationException::withMessages([
            'code' => 'This verification code is no longer valid.',
        ]);
    }

    return $apiSuccess([
        'principal_id' => $user['principal_id'] ?? null,
        'organization_id' => $user['organization_id'] ?? null,
        'provider' => ($user['auth_provider'] ?? 'local') === 'ldap' ? 'identity-ldap' : 'identity-local',
        'authenticated' => true,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'identityLocalAuthVerifyCode',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Complete sign-in by consuming one verification code',
    'responses' => [
        '200' => [
            'description' => 'Sign-in verified',
        ],
        '422' => [
            'description' => 'Verification code is invalid or expired',
        ],
    ],
    'request_rules' => [
        'principal_id' => ['required', 'string', 'max:120'],
        'code' => ['required', 'digits:6'],
    ],
]);

Route::post('/identity-local/auth/logout', function () use ($apiSuccess) {
    return $apiSuccess([
        'signed_out' => true,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'identityLocalAuthLogout',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'End local authenticated session state',
    'responses' => [
        '200' => [
            'description' => 'Session signed out',
        ],
    ],
    'request_rules' => [],
]);

Route::post('/identity-local/users/import/upload', function (
    Request $request,
    IdentityUserImportService $imports,
) use ($apiPrincipalId, $apiSuccess) {
    $validated = $request->validate([
        'import_file' => ['required', 'file', 'max:1024'],
        'organization_id' => ['required', 'string', 'max:64'],
    ]);

    $principalId = $apiPrincipalId($request);

    $upload = $imports->beginImport(
        file: $validated['import_file'],
        organizationId: (string) $validated['organization_id'],
        managedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
    );

    return $apiSuccess([
        'upload_state' => [
            ...$upload,
            'organization_id' => (string) $validated['organization_id'],
        ],
    ]);
})->defaults('_openapi', [
    'operation_id' => 'identityLocalUploadUsersImport',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Upload one CSV/TSV file for user import staging',
    'responses' => [
        '200' => [
            'description' => 'Import upload parsed and staged',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_body' => [
        'required' => true,
        'content' => [
            'multipart/form-data' => [
                'schema' => [
                    'type' => 'object',
                    'required' => ['import_file', 'organization_id'],
                    'properties' => [
                        'import_file' => ['type' => 'string', 'format' => 'binary'],
                        'organization_id' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
])->middleware('core.permission:plugin.identity-local.users.manage');

Route::post('/identity-local/users/import/reset', function (
    Request $request,
) use ($apiSuccess) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
    ]);

    return $apiSuccess([
        'reset' => true,
        'organization_id' => (string) $validated['organization_id'],
    ]);
})->defaults('_openapi', [
    'operation_id' => 'identityLocalResetUsersImport',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Reset user import wizard state on API clients',
    'responses' => [
        '200' => [
            'description' => 'Import wizard state reset',
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
    ],
])->middleware('core.permission:plugin.identity-local.users.manage');

Route::post('/identity-local/users/import/review', function (
    Request $request,
    IdentityUserImportService $imports,
) use ($apiPrincipalId, $apiSuccess) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'upload_state' => ['required', 'array'],
        'mapping' => ['required', 'array'],
        'mapping.display_name' => ['required', 'string', 'max:190'],
        'mapping.email' => ['required', 'string', 'max:190'],
        'mapping.username' => ['nullable', 'string', 'max:190'],
        'mapping.job_title' => ['nullable', 'string', 'max:190'],
    ]);

    $principalId = $apiPrincipalId($request);
    $review = $imports->reviewImport(
        uploadState: [
            ...(is_array($validated['upload_state']) ? $validated['upload_state'] : []),
            'organization_id' => (string) $validated['organization_id'],
        ],
        mapping: (array) $validated['mapping'],
        organizationId: (string) $validated['organization_id'],
        managedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
    );

    return $apiSuccess([
        'review_state' => [
            ...$review,
            'organization_id' => (string) $validated['organization_id'],
        ],
    ]);
})->defaults('_openapi', [
    'operation_id' => 'identityLocalReviewUsersImport',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Validate staged user import rows before commit',
    'responses' => [
        '200' => [
            'description' => 'Import review completed',
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
        'upload_state' => ['required', 'array'],
        'mapping' => ['required', 'array'],
        'mapping.display_name' => ['required', 'string', 'max:190'],
        'mapping.email' => ['required', 'string', 'max:190'],
        'mapping.username' => ['nullable', 'string', 'max:190'],
        'mapping.job_title' => ['nullable', 'string', 'max:190'],
    ],
])->middleware('core.permission:plugin.identity-local.users.manage');

Route::post('/identity-local/users/import/commit', function (
    Request $request,
    IdentityUserImportService $imports,
) use ($apiPrincipalId, $apiSuccess) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'review_state' => ['required', 'array'],
    ]);

    $principalId = $apiPrincipalId($request);
    $result = $imports->importUsers(
        reviewState: [
            ...(is_array($validated['review_state']) ? $validated['review_state'] : []),
            'organization_id' => (string) $validated['organization_id'],
        ],
        organizationId: (string) $validated['organization_id'],
        managedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
    );

    return $apiSuccess($result);
})->defaults('_openapi', [
    'operation_id' => 'identityLocalCommitUsersImport',
    'tags' => ['identity'],
    'tag_descriptions' => [
        'identity' => 'Local and LDAP identity administration endpoints.',
    ],
    'summary' => 'Commit validated user import rows',
    'responses' => [
        '200' => [
            'description' => 'Import committed',
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
        'review_state' => ['required', 'array'],
    ],
])->middleware('core.permission:plugin.identity-local.users.manage');
