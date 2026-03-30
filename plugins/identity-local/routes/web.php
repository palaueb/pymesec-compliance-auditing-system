<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Plugins\IdentityLdap\IdentityLdapService;
use PymeSec\Plugins\IdentityLocal\IdentityLocalAuthService;
use PymeSec\Plugins\IdentityLocal\IdentityLocalRepository;
use PymeSec\Plugins\IdentityLocal\IdentityUserImportService;

Route::get('/setup', function (IdentityLocalAuthService $auth) {
    if (! $auth->requiresBootstrap()) {
        return redirect()->route('plugin.identity-local.auth.login');
    }

    return view()->file(base_path('../plugins/identity-local/resources/views/setup.blade.php'), [
        'locale' => app()->getLocale(),
        'requiresOrganizationSetup' => ! app(\PymeSec\Plugins\IdentityLocal\IdentityLocalRepository::class)->firstOrganizationId(),
    ]);
})->name('plugin.identity-local.setup');

Route::post('/setup', function (Request $request, IdentityLocalAuthService $auth) {
    if (! $auth->requiresBootstrap()) {
        return redirect()->route('plugin.identity-local.auth.login');
    }

    $requiresOrganizationSetup = ! app(\PymeSec\Plugins\IdentityLocal\IdentityLocalRepository::class)->firstOrganizationId();

    $validated = $request->validate(array_filter([
        'display_name' => ['required', 'string', 'max:120'],
        'username' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
        'email' => ['required', 'email:rfc', 'max:190'],
        'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        'organization_name' => $requiresOrganizationSetup ? ['required', 'string', 'max:160'] : null,
        'organization_slug' => $requiresOrganizationSetup ? ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'] : null,
        'default_locale' => $requiresOrganizationSetup ? ['required', 'string', 'in:en,es,fr,de'] : null,
        'default_timezone' => $requiresOrganizationSetup ? ['required', 'string', 'max:64'] : null,
    ]));

    $auth->bootstrapSuperAdmin($validated);

    return redirect()->route('plugin.identity-local.auth.login')
        ->with('status', 'The first administrator account is ready. Sign in to continue.');
})->name('plugin.identity-local.setup.store');

Route::get('/login', function (IdentityLocalAuthService $auth) {
    if ($auth->requiresBootstrap()) {
        return redirect()->route('plugin.identity-local.setup');
    }

    if (is_string(session('auth.principal_id')) && session('auth.principal_id') !== '') {
        return redirect()->route('core.shell.index');
    }

    return view()->file(base_path('../plugins/identity-local/resources/views/login.blade.php'), [
        'locale' => app()->getLocale(),
    ]);
})->name('plugin.identity-local.auth.login');

Route::post('/login', function (Request $request, IdentityLocalAuthService $auth) {
    if ($auth->requiresBootstrap()) {
        return redirect()->route('plugin.identity-local.setup');
    }

    $validated = $request->validate([
        'login' => ['nullable', 'string', 'max:190'],
        'email' => ['nullable', 'email:rfc', 'max:190'],
        'password' => ['nullable', 'string', 'max:190'],
    ]);

    $login = (string) ($validated['login'] ?? $validated['email'] ?? '');

    if ($request->boolean('use_email_link')) {
        $issued = app()->bound(IdentityLdapService::class)
            ? app(IdentityLdapService::class)->issueMagicLink($login, $request)
            : null;

        $issued ??= $auth->issueMagicLink($login, $request);

        return redirect()->route('plugin.identity-local.auth.login')
            ->with('status', 'If the identity is active, a secure sign-in link has been sent.');
    }

    $challenge = app()->bound(IdentityLdapService::class)
        ? app(IdentityLdapService::class)->beginPasswordLogin($login, (string) ($validated['password'] ?? ''), $request)
        : null;
    $provider = $challenge !== null ? 'identity-ldap' : 'identity-local';
    $challenge ??= $auth->beginPasswordLogin($login, (string) ($validated['password'] ?? ''), $request);

    if ($challenge === null) {
        return redirect()->route('plugin.identity-local.auth.login')
            ->with('error', 'The sign-in details are not valid for password access.');
    }

    session()->put('auth.pending_principal_id', $challenge['user']['principal_id']);
    session()->put('auth.pending_provider', $provider);
    session()->put('auth.pending_method', 'password-2fa');

    return redirect()->route('plugin.identity-local.auth.verify')
        ->with('status', 'We sent a verification code to your email.');
})->name('plugin.identity-local.auth.request');

Route::get('/login/verify', function (IdentityLocalAuthService $auth) {
    if ($auth->requiresBootstrap()) {
        return redirect()->route('plugin.identity-local.setup');
    }

    $principalId = session('auth.pending_principal_id');

    if (! is_string($principalId) || $principalId === '') {
        return redirect()->route('plugin.identity-local.auth.login');
    }

    $user = $auth->currentUser($principalId);

    if ($user === null) {
        return redirect()->route('plugin.identity-local.auth.login');
    }

    return view()->file(base_path('../plugins/identity-local/resources/views/verify.blade.php'), [
        'locale' => app()->getLocale(),
        'email' => $user['email'],
    ]);
})->name('plugin.identity-local.auth.verify');

Route::post('/login/verify', function (Request $request, IdentityLocalAuthService $auth) {
    $principalId = session('auth.pending_principal_id');

    if (! is_string($principalId) || $principalId === '') {
        return redirect()->route('plugin.identity-local.auth.login');
    }

    $validated = $request->validate([
        'code' => ['required', 'digits:6'],
    ]);

    $user = $auth->consumeEmailCode($principalId, (string) $validated['code']);

    if ($user === null) {
        return redirect()->route('plugin.identity-local.auth.verify')
            ->with('error', 'This verification code is no longer valid.');
    }

    $provider = session('auth.pending_provider');

    session()->forget(['auth.pending_principal_id', 'auth.pending_provider', 'auth.pending_method']);
    session()->put('auth.principal_id', $user['principal_id']);
    session()->put('auth.provider', is_string($provider) && $provider !== '' ? $provider : 'identity-local');

    return redirect()->route('core.shell.index', array_filter([
        'organization_id' => $user['organization_id'] !== '' ? $user['organization_id'] : null,
    ]));
})->name('plugin.identity-local.auth.verify.consume');

Route::get('/login/magic/{token}', function (string $token, IdentityLocalAuthService $auth) {
    $user = $auth->consumeMagicLink($token);

    if ($user === null) {
        return redirect()->route('plugin.identity-local.auth.login')
            ->with('error', 'This sign-in link is no longer valid.');
    }

    session()->forget(['auth.pending_principal_id', 'auth.pending_provider', 'auth.pending_method']);
    session()->put('auth.principal_id', $user['principal_id']);
    session()->put('auth.provider', ($user['auth_provider'] ?? 'local') === 'ldap' ? 'identity-ldap' : 'identity-local');

    return redirect()->route('core.shell.index', array_filter([
        'organization_id' => $user['organization_id'] !== '' ? $user['organization_id'] : null,
    ]));
})->name('plugin.identity-local.auth.consume');

Route::post('/logout', function () {
    session()->forget(['auth.principal_id', 'auth.provider', 'auth.pending_principal_id', 'auth.pending_provider', 'auth.pending_method']);
    session()->invalidate();
    session()->regenerateToken();

    return redirect()->route('plugin.identity-local.auth.login');
})->name('plugin.identity-local.auth.logout');

Route::get('/plugins/identity/users', function (Request $request, IdentityLocalRepository $repository) {
    return response()->json([
        'plugin' => 'identity-local',
        'users' => $repository->usersForOrganization((string) $request->query('organization_id', 'org-a')),
    ]);
})->middleware('core.permission:plugin.identity-local.users.view')->name('plugin.identity-local.users.index');

Route::post('/plugins/identity/users/import/upload', function (
    Request $request,
    IdentityUserImportService $imports,
) {
    $validated = $request->validate([
        'import_file' => ['required', 'file', 'max:1024'],
        'organization_id' => ['required', 'string', 'max:64'],
    ]);

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $requesterMembershipId = $request->input('membership_id');
    $upload = $imports->beginImport($validated['import_file'], (string) $validated['organization_id'], $requesterPrincipalId);

    session()->put('identity_local_user_import_upload', [
        ...$upload,
        'organization_id' => (string) $validated['organization_id'],
    ]);
    session()->forget('identity_local_user_import_review');

    return redirect()->route('core.admin.index', array_filter([
        'menu' => 'plugin.identity-local.users',
        'principal_id' => $requesterPrincipalId,
        'organization_id' => (string) $validated['organization_id'],
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
    ]))->with('status', 'Map the uploaded columns before validating the import.');
})->middleware('core.permission:plugin.identity-local.users.manage')->name('plugin.identity-local.users.import.upload');

Route::post('/plugins/identity/users/import/reset', function (Request $request) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
    ]);

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $requesterMembershipId = $request->input('membership_id');

    session()->forget(['identity_local_user_import_upload', 'identity_local_user_import_review']);

    return redirect()->route('core.admin.index', array_filter([
        'menu' => 'plugin.identity-local.users',
        'principal_id' => $requesterPrincipalId,
        'organization_id' => (string) $validated['organization_id'],
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
    ]))->with('status', 'The user import wizard has been reset.');
})->middleware('core.permission:plugin.identity-local.users.manage')->name('plugin.identity-local.users.import.reset');

Route::post('/plugins/identity/users/import/review', function (
    Request $request,
    IdentityUserImportService $imports,
) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'mapping' => ['required', 'array'],
        'mapping.display_name' => ['required', 'string', 'max:190'],
        'mapping.email' => ['required', 'string', 'max:190'],
        'mapping.username' => ['nullable', 'string', 'max:190'],
        'mapping.job_title' => ['nullable', 'string', 'max:190'],
    ]);

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $requesterMembershipId = $request->input('membership_id');
    $uploadState = session('identity_local_user_import_upload');

    if (! is_array($uploadState) || (($uploadState['organization_id'] ?? null) !== (string) $validated['organization_id'])) {
        return redirect()->route('core.admin.index', array_filter([
            'menu' => 'plugin.identity-local.users',
            'principal_id' => $requesterPrincipalId,
            'organization_id' => (string) $validated['organization_id'],
            'locale' => $request->input('locale', 'en'),
            'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
        ]))->with('error', 'Upload the CSV or TSV file again before validating the import.');
    }

    $review = $imports->reviewImport($uploadState, $validated['mapping'], (string) $validated['organization_id'], $requesterPrincipalId);

    session()->put('identity_local_user_import_review', [
        ...$review,
        'organization_id' => (string) $validated['organization_id'],
    ]);

    return redirect()->route('core.admin.index', array_filter([
        'menu' => 'plugin.identity-local.users',
        'principal_id' => $requesterPrincipalId,
        'organization_id' => (string) $validated['organization_id'],
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
    ]))->with(
        ($review['summary']['invalid_count'] ?? 0) > 0 ? 'error' : 'status',
        ($review['summary']['invalid_count'] ?? 0) > 0
            ? 'The import is blocked until every invalid row is fixed.'
            : sprintf('Validated %d people. Confirm to create them.', (int) ($review['summary']['valid_count'] ?? 0)),
    );
})->middleware('core.permission:plugin.identity-local.users.manage')->name('plugin.identity-local.users.import.review');

Route::post('/plugins/identity/users/import/commit', function (
    Request $request,
    IdentityUserImportService $imports,
) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
    ]);

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $requesterMembershipId = $request->input('membership_id');
    $reviewState = session('identity_local_user_import_review');

    if (! is_array($reviewState) || (($reviewState['organization_id'] ?? null) !== (string) $validated['organization_id'])) {
        return redirect()->route('core.admin.index', array_filter([
            'menu' => 'plugin.identity-local.users',
            'principal_id' => $requesterPrincipalId,
            'organization_id' => (string) $validated['organization_id'],
            'locale' => $request->input('locale', 'en'),
            'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
        ]))->with('error', 'Validate the import again before confirming it.');
    }

    $result = $imports->importUsers($reviewState, (string) $validated['organization_id'], $requesterPrincipalId);

    session()->forget(['identity_local_user_import_upload', 'identity_local_user_import_review']);

    return redirect()->route('core.admin.index', array_filter([
        'menu' => 'plugin.identity-local.users',
        'principal_id' => $requesterPrincipalId,
        'organization_id' => (string) $validated['organization_id'],
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
    ]))->with('status', sprintf('Imported %d people into the local directory.', (int) ($result['created_count'] ?? 0)));
})->middleware('core.permission:plugin.identity-local.users.manage')->name('plugin.identity-local.users.import.commit');

Route::get('/plugins/identity/memberships', function (Request $request, IdentityLocalRepository $repository) {
    return response()->json([
        'plugin' => 'identity-local',
        'memberships' => $repository->membershipsForOrganization((string) $request->query('organization_id', 'org-a')),
    ]);
})->middleware('core.permission:plugin.identity-local.memberships.view')->name('plugin.identity-local.memberships.index');

Route::post('/plugins/identity/users', function (
    Request $request,
    IdentityLocalRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $validated = $request->validate([
        'display_name' => ['required', 'string', 'max:120'],
        'username' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('identity_local_users', 'username')],
        'email' => ['required', 'email:rfc', 'max:190', Rule::unique('identity_local_users', 'email')],
        'job_title' => ['nullable', 'string', 'max:120'],
        'organization_id' => ['required', 'string', 'max:64'],
        'actor_id' => ['nullable', 'string', 'max:120'],
        'password' => ['nullable', 'string', 'min:8', 'confirmed'],
    ]);

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $requesterMembershipId = $request->input('membership_id');
    $passwordEnabled = $request->boolean('password_enabled');

    if ($passwordEnabled && ! is_string($validated['password'] ?? null)) {
        throw ValidationException::withMessages([
            'password' => 'A password is required when password sign-in is enabled.',
        ]);
    }

    $user = $repository->createUser([
        ...$validated,
        'password_enabled' => $passwordEnabled,
        'magic_link_enabled' => $request->boolean('magic_link_enabled') || ! $passwordEnabled,
        'is_active' => true,
    ], $requesterPrincipalId);

    if (is_string($validated['actor_id'] ?? null) && $validated['actor_id'] !== '') {
        $actors->linkPrincipal(
            principalId: (string) $user['principal_id'],
            actorId: $validated['actor_id'],
            organizationId: (string) $validated['organization_id'],
            linkedByPrincipalId: $requesterPrincipalId,
        );
    }

    return redirect()->route('core.admin.index', array_filter([
        'menu' => 'plugin.identity-local.users',
        'user_id' => $user['id'],
        'principal_id' => $requesterPrincipalId,
        'organization_id' => $validated['organization_id'],
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
    ]));
})->middleware('core.permission:plugin.identity-local.users.manage')->name('plugin.identity-local.users.store');

Route::post('/plugins/identity/users/{userId}', function (
    Request $request,
    string $userId,
    IdentityLocalRepository $repository,
    FunctionalActorServiceInterface $actors
) {
    $user = $repository->findUser($userId);

    abort_if($user === null, 404);

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

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $requesterMembershipId = $request->input('membership_id');
    $passwordEnabled = $request->boolean('password_enabled');

    if ($passwordEnabled
        && ! is_string($validated['password'] ?? null)
        && ! is_string($user['password_hash'] ?? null)) {
        throw ValidationException::withMessages([
            'password' => 'A password is required before password sign-in can be enabled.',
        ]);
    }

    $user = $repository->updateUser($userId, [
        ...$validated,
        'password_enabled' => $passwordEnabled,
        'magic_link_enabled' => $request->boolean('magic_link_enabled') || ! $passwordEnabled,
        'is_active' => $request->boolean('is_active'),
    ], $requesterPrincipalId);

    abort_if($user === null, 404);

    if (is_string($validated['actor_id'] ?? null) && $validated['actor_id'] !== '') {
        $actors->linkPrincipal(
            principalId: (string) $user['principal_id'],
            actorId: $validated['actor_id'],
            organizationId: (string) $validated['organization_id'],
            linkedByPrincipalId: $requesterPrincipalId,
        );
    }

    return redirect()->route('core.admin.index', array_filter([
        'menu' => 'plugin.identity-local.users',
        'user_id' => $user['id'],
        'principal_id' => $requesterPrincipalId,
        'organization_id' => $validated['organization_id'],
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
    ]));
})->middleware('core.permission:plugin.identity-local.users.manage')->name('plugin.identity-local.users.update');

Route::post('/plugins/identity/users/{userId}/delete', function (
    Request $request,
    string $userId,
    IdentityLocalRepository $repository
) {
    $user = $repository->findUser($userId);

    abort_if($user === null, 404);

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $requesterMembershipId = $request->input('membership_id');

    if (($user['auth_provider'] ?? 'local') !== 'local') {
        return redirect()->route('core.admin.index', array_filter([
            'menu' => 'plugin.identity-local.users',
            'user_id' => $userId,
            'principal_id' => $requesterPrincipalId,
            'organization_id' => (string) ($user['organization_id'] ?? $request->input('organization_id', 'org-a')),
            'locale' => $request->input('locale', 'en'),
            'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
        ]))->with('error', 'Directory-backed people are removed through LDAP sync, not from the local people screen.');
    }

    if (($user['principal_id'] ?? null) === $requesterPrincipalId) {
        return redirect()->route('core.admin.index', array_filter([
            'menu' => 'plugin.identity-local.users',
            'user_id' => $userId,
            'principal_id' => $requesterPrincipalId,
            'organization_id' => (string) ($user['organization_id'] ?? $request->input('organization_id', 'org-a')),
            'locale' => $request->input('locale', 'en'),
            'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
        ]))->with('error', 'You cannot delete the account that is currently using the workspace.');
    }

    $repository->deleteUser($userId, $requesterPrincipalId);

    return redirect()->route('core.admin.index', array_filter([
        'menu' => 'plugin.identity-local.users',
        'principal_id' => $requesterPrincipalId,
        'organization_id' => (string) ($user['organization_id'] ?? $request->input('organization_id', 'org-a')),
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
    ]))->with('status', 'Person removed from the local workspace directory.');
})->middleware('core.permission:plugin.identity-local.users.manage')->name('plugin.identity-local.users.delete');

Route::post('/plugins/identity/memberships', function (Request $request, IdentityLocalRepository $repository) {
    $validated = $request->validate([
        'subject_principal_id' => ['required', 'string', 'max:64'],
        'organization_id' => ['required', 'string', 'max:64'],
        'role_keys' => ['nullable', 'array'],
        'role_keys.*' => ['string', 'max:120'],
        'scope_ids' => ['nullable', 'array'],
        'scope_ids.*' => ['string', 'max:64'],
    ]);

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $requesterMembershipId = $request->input('membership_id');

    $membership = $repository->createMembership([
        'principal_id' => $validated['subject_principal_id'],
        'organization_id' => $validated['organization_id'],
        'role_keys' => $validated['role_keys'] ?? [],
        'scope_ids' => $validated['scope_ids'] ?? [],
        'is_active' => true,
    ], $requesterPrincipalId);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.identity-local.memberships',
        'selected_membership_id' => $membership['id'],
        'principal_id' => $requesterPrincipalId,
        'organization_id' => $validated['organization_id'],
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
    ]));
})->middleware('core.permission:plugin.identity-local.memberships.manage')->name('plugin.identity-local.memberships.store');

Route::post('/plugins/identity/memberships/{membershipId}', function (
    Request $request,
    string $membershipId,
    IdentityLocalRepository $repository
) {
    $validated = $request->validate([
        'subject_principal_id' => ['required', 'string', 'max:64'],
        'organization_id' => ['required', 'string', 'max:64'],
        'role_keys' => ['nullable', 'array'],
        'role_keys.*' => ['string', 'max:120'],
        'scope_ids' => ['nullable', 'array'],
        'scope_ids.*' => ['string', 'max:64'],
        'is_active' => ['nullable', 'string', 'in:1'],
    ]);

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $requesterMembershipId = $request->input('membership_id');

    $membership = $repository->updateMembership($membershipId, [
        'principal_id' => $validated['subject_principal_id'],
        'organization_id' => $validated['organization_id'],
        'role_keys' => $validated['role_keys'] ?? [],
        'scope_ids' => $validated['scope_ids'] ?? [],
        'is_active' => $request->boolean('is_active'),
    ], $requesterPrincipalId);

    abort_if($membership === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.identity-local.memberships',
        'selected_membership_id' => $membership['id'],
        'principal_id' => $requesterPrincipalId,
        'organization_id' => $validated['organization_id'],
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
    ]))->with('status', 'Access updated.');
})->middleware('core.permission:plugin.identity-local.memberships.manage')->name('plugin.identity-local.memberships.update');
