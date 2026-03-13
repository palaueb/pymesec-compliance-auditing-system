<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Plugins\IdentityLocal\IdentityLocalAuthService;
use PymeSec\Plugins\IdentityLocal\IdentityLocalRepository;

Route::get('/login', function () {
    if (is_string(session('auth.principal_id')) && session('auth.principal_id') !== '') {
        return redirect()->route('core.shell.index');
    }

    return response()->view(base_path('../plugins/identity-local/resources/views/login.blade.php'), [
        'locale' => app()->getLocale(),
    ]);
})->name('plugin.identity-local.auth.login');

Route::post('/login', function (Request $request, IdentityLocalAuthService $auth) {
    $validated = $request->validate([
        'email' => ['required', 'email:rfc', 'max:190'],
    ]);

    $auth->issueMagicLink((string) $validated['email'], $request);

    return redirect()->route('plugin.identity-local.auth.login')
        ->with('status', 'If the address is active, a secure sign-in link has been sent.');
})->name('plugin.identity-local.auth.request');

Route::get('/login/magic/{token}', function (string $token, IdentityLocalAuthService $auth) {
    $user = $auth->consumeMagicLink($token);

    if ($user === null) {
        return redirect()->route('plugin.identity-local.auth.login')
            ->with('error', 'This sign-in link is no longer valid.');
    }

    session()->put('auth.principal_id', $user['principal_id']);
    session()->put('auth.provider', 'identity-local');

    return redirect()->route('core.shell.index', array_filter([
        'organization_id' => $user['organization_id'] !== '' ? $user['organization_id'] : null,
    ]));
})->name('plugin.identity-local.auth.consume');

Route::post('/logout', function () {
    session()->forget(['auth.principal_id', 'auth.provider']);
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
        'email' => ['required', 'email:rfc', 'max:190'],
        'job_title' => ['nullable', 'string', 'max:120'],
        'organization_id' => ['required', 'string', 'max:64'],
        'actor_id' => ['nullable', 'string', 'max:120'],
    ]);

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $requesterMembershipId = $request->input('membership_id');

    $user = $repository->createUser([
        ...$validated,
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

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.identity-local.users',
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
    $validated = $request->validate([
        'display_name' => ['required', 'string', 'max:120'],
        'email' => ['required', 'email:rfc', 'max:190'],
        'job_title' => ['nullable', 'string', 'max:120'],
        'organization_id' => ['required', 'string', 'max:64'],
        'actor_id' => ['nullable', 'string', 'max:120'],
        'is_active' => ['nullable', 'string', 'in:1'],
    ]);

    $requesterPrincipalId = (string) $request->input('principal_id', 'principal-org-a');
    $requesterMembershipId = $request->input('membership_id');

    $user = $repository->updateUser($userId, [
        ...$validated,
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

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.identity-local.users',
        'principal_id' => $requesterPrincipalId,
        'organization_id' => $validated['organization_id'],
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
    ]));
})->middleware('core.permission:plugin.identity-local.users.manage')->name('plugin.identity-local.users.update');

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

    $repository->createMembership([
        'principal_id' => $validated['subject_principal_id'],
        'organization_id' => $validated['organization_id'],
        'role_keys' => $validated['role_keys'] ?? [],
        'scope_ids' => $validated['scope_ids'] ?? [],
        'is_active' => true,
    ], $requesterPrincipalId);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.identity-local.memberships',
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
        'principal_id' => $requesterPrincipalId,
        'organization_id' => $validated['organization_id'],
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($requesterMembershipId) && $requesterMembershipId !== '' ? [$requesterMembershipId] : null,
    ]));
})->middleware('core.permission:plugin.identity-local.memberships.manage')->name('plugin.identity-local.memberships.update');
