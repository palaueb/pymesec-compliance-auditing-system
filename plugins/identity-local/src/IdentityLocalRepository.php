<?php

namespace PymeSec\Plugins\IdentityLocal;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\Permissions\DatabaseAuthorizationStore;

class IdentityLocalRepository
{
    public function __construct(
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
        private readonly DatabaseAuthorizationStore $authorizationStore,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function usersForOrganization(string $organizationId): array
    {
        return DB::table('identity_local_users as users')
            ->leftJoin('memberships as memberships', function ($join) use ($organizationId): void {
                $join->on('memberships.principal_id', '=', 'users.principal_id')
                    ->where('memberships.organization_id', '=', $organizationId)
                    ->where('memberships.is_active', '=', true);
            })
            ->where(function ($query) use ($organizationId): void {
                $query->where('users.organization_id', $organizationId)
                    ->orWhereNotNull('memberships.id');
            })
            ->distinct()
            ->orderBy('users.display_name')
            ->get([
                'users.id',
                'users.principal_id',
                'users.auth_provider',
                'users.external_subject',
                'users.directory_source',
                'users.directory_groups',
                'users.directory_synced_at',
                'users.organization_id',
                'users.username',
                'users.display_name',
                'users.email',
                'users.password_hash',
                'users.password_enabled',
                'users.magic_link_enabled',
                'users.job_title',
                'users.is_active',
            ])->map(fn ($user): array => $this->mapUser($user))
            ->all();
    }

    public function anyUsers(): bool
    {
        return DB::table('identity_local_users')->exists();
    }

    public function firstOrganizationId(): ?string
    {
        $organizationId = DB::table('organizations')
            ->orderBy('id')
            ->value('id');

        return is_string($organizationId) && $organizationId !== '' ? $organizationId : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findUser(string $userId): ?array
    {
        $user = DB::table('identity_local_users')->where('id', $userId)->first();

        return $user !== null ? $this->mapUser($user) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findUserByPrincipal(string $principalId): ?array
    {
        $user = DB::table('identity_local_users')->where('principal_id', $principalId)->first();

        return $user !== null ? $this->mapUser($user) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findUserByExternalIdentity(string $authProvider, string $externalSubject): ?array
    {
        $user = DB::table('identity_local_users')
            ->where('auth_provider', $authProvider)
            ->where('external_subject', $externalSubject)
            ->first();

        return $user !== null ? $this->mapUser($user) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveUserByEmail(string $email): ?array
    {
        $user = DB::table('identity_local_users')
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->where('is_active', true)
            ->first();

        return $user !== null ? $this->mapUser($user) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveUserByLogin(string $login): ?array
    {
        $normalized = Str::lower(trim($login));

        if ($normalized === '') {
            return null;
        }

        $user = DB::table('identity_local_users')
            ->where('is_active', true)
            ->where(function ($query) use ($normalized): void {
                $query->whereRaw('LOWER(email) = ?', [$normalized])
                    ->orWhereRaw('LOWER(username) = ?', [$normalized]);
            })
            ->first();

        return $user !== null ? $this->mapUser($user) : null;
    }

    /**
     * @return array{user:array<string, mixed>, created:bool}
     */
    public function syncDirectoryUser(array $data): array
    {
        $authProvider = (string) ($data['auth_provider'] ?? 'ldap');
        $externalSubject = (string) ($data['external_subject'] ?? '');
        $existing = $externalSubject !== '' ? $this->findUserByExternalIdentity($authProvider, $externalSubject) : null;

        if ($existing === null) {
            $conflict = DB::table('identity_local_users')
                ->where(function ($query) use ($data): void {
                    $query->whereRaw('LOWER(email) = ?', [Str::lower((string) $data['email'])])
                        ->orWhereRaw('LOWER(username) = ?', [Str::lower((string) $data['username'])]);
                })
                ->first();

            if ($conflict !== null) {
                throw new \RuntimeException(sprintf(
                    'LDAP sync conflict for [%s]. A local account already uses this username or email.',
                    (string) $data['email']
                ));
            }

            $user = $this->createUser([
                'organization_id' => (string) $data['organization_id'],
                'display_name' => (string) $data['display_name'],
                'username' => (string) $data['username'],
                'email' => (string) $data['email'],
                'job_title' => $data['job_title'] ?? null,
                'password_enabled' => false,
                'magic_link_enabled' => (bool) ($data['magic_link_enabled'] ?? true),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            DB::table('identity_local_users')
                ->where('id', $user['id'])
                ->update([
                    'auth_provider' => $authProvider,
                    'external_subject' => $externalSubject !== '' ? $externalSubject : null,
                    'directory_source' => is_string($data['directory_source'] ?? null) ? $data['directory_source'] : null,
                    'directory_groups' => $this->encodeJson($this->normalizeStringArray($data['directory_groups'] ?? [])),
                    'directory_synced_at' => now(),
                    'updated_at' => now(),
                ]);

            return [
                'user' => $this->findUser((string) $user['id']) ?? $user,
                'created' => true,
            ];
        }

        $user = $this->updateUser((string) $existing['id'], [
            'organization_id' => (string) $data['organization_id'],
            'display_name' => (string) $data['display_name'],
            'username' => (string) $data['username'],
            'email' => (string) $data['email'],
            'job_title' => $data['job_title'] ?? null,
            'password_enabled' => false,
            'magic_link_enabled' => (bool) ($data['magic_link_enabled'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        DB::table('identity_local_users')
            ->where('id', $existing['id'])
            ->update([
                'auth_provider' => $authProvider,
                'external_subject' => $externalSubject !== '' ? $externalSubject : null,
                'directory_source' => is_string($data['directory_source'] ?? null) ? $data['directory_source'] : null,
                'directory_groups' => $this->encodeJson($this->normalizeStringArray($data['directory_groups'] ?? [])),
                'directory_synced_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'user' => $this->findUser((string) $existing['id']) ?? ($user ?? $existing),
            'created' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createUser(array $data, ?string $managedByPrincipalId = null): array
    {
        $displayName = (string) $data['display_name'];
        $username = Str::lower(trim((string) $data['username']));
        $email = (string) $data['email'];
        $jobTitle = is_string($data['job_title'] ?? null) && $data['job_title'] !== '' ? $data['job_title'] : null;
        $organizationId = (string) $data['organization_id'];
        $id = $this->nextUserId($displayName);
        $principalId = $this->nextPrincipalId($username, $displayName, $email);
        $password = is_string($data['password'] ?? null) && $data['password'] !== '' ? (string) $data['password'] : null;
        $passwordEnabled = (bool) ($data['password_enabled'] ?? false);
        $magicLinkEnabled = array_key_exists('magic_link_enabled', $data)
            ? (bool) $data['magic_link_enabled']
            : true;

        DB::table('identity_local_users')->insert([
            'id' => $id,
            'principal_id' => $principalId,
            'organization_id' => $organizationId,
            'username' => $username,
            'display_name' => $displayName,
            'email' => $email,
            'password_hash' => $password !== null ? Hash::make($password) : null,
            'password_enabled' => $passwordEnabled,
            'magic_link_enabled' => $magicLinkEnabled,
            'job_title' => $jobTitle,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = $this->findUser($id);

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-local.user.created',
            outcome: 'success',
            originComponent: 'identity-local',
            principalId: $managedByPrincipalId,
            organizationId: $organizationId,
            targetType: 'identity_local_user',
            targetId: $id,
            summary: [
                'principal_id' => $principalId,
                'username' => $username,
                'display_name' => $displayName,
                'email' => $email,
            ],
            executionOrigin: 'identity-local',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.identity-local.user.created',
            originComponent: 'identity-local',
            organizationId: $organizationId,
            payload: [
                'user_id' => $id,
                'principal_id' => $principalId,
            ],
        ));

        return is_array($user) ? $user : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function updateUser(string $userId, array $data, ?string $managedByPrincipalId = null): ?array
    {
        $existing = $this->findUser($userId);

        if ($existing === null) {
            return null;
        }

        $password = is_string($data['password'] ?? null) && $data['password'] !== '' ? (string) $data['password'] : null;
        $passwordEnabled = (bool) ($data['password_enabled'] ?? false);
        $magicLinkEnabled = array_key_exists('magic_link_enabled', $data)
            ? (bool) $data['magic_link_enabled']
            : (bool) ($existing['magic_link_enabled'] ?? true);

        DB::table('identity_local_users')
            ->where('id', $userId)
            ->update([
                'organization_id' => (string) $data['organization_id'],
                'username' => Str::lower(trim((string) $data['username'])),
                'display_name' => (string) $data['display_name'],
                'email' => (string) $data['email'],
                'password_hash' => $password !== null ? Hash::make($password) : ($existing['password_hash'] ?? null),
                'password_enabled' => $passwordEnabled,
                'magic_link_enabled' => $magicLinkEnabled,
                'job_title' => is_string($data['job_title'] ?? null) && $data['job_title'] !== '' ? $data['job_title'] : null,
                'is_active' => (bool) ($data['is_active'] ?? false),
                'updated_at' => now(),
            ]);

        $user = $this->findUser($userId);

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-local.user.updated',
            outcome: 'success',
            originComponent: 'identity-local',
            principalId: $managedByPrincipalId,
            organizationId: (string) $data['organization_id'],
            targetType: 'identity_local_user',
            targetId: $userId,
            summary: [
                'principal_id' => $existing['principal_id'] ?? null,
                'username' => $data['username'] ?? null,
                'display_name' => $data['display_name'] ?? null,
                'email' => $data['email'] ?? null,
                'password_enabled' => $passwordEnabled,
                'magic_link_enabled' => $magicLinkEnabled,
                'is_active' => (bool) ($data['is_active'] ?? false),
            ],
            executionOrigin: 'identity-local',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.identity-local.user.updated',
            originComponent: 'identity-local',
            organizationId: (string) $data['organization_id'],
            payload: [
                'user_id' => $userId,
                'principal_id' => $existing['principal_id'] ?? null,
            ],
        ));

        return $user;
    }

    public function deleteUser(string $userId, ?string $managedByPrincipalId = null): bool
    {
        $existing = $this->findUser($userId);

        if ($existing === null) {
            return false;
        }

        if (($existing['auth_provider'] ?? 'local') !== 'local') {
            throw new \RuntimeException('Directory-backed people are managed from Directory Sync, not deleted locally.');
        }

        DB::transaction(function () use ($existing, $userId): void {
            $membershipIds = DB::table('memberships')
                ->where('principal_id', (string) $existing['principal_id'])
                ->pluck('id')
                ->map(static fn ($membershipId): string => (string) $membershipId)
                ->all();

            if ($membershipIds !== []) {
                DB::table('authorization_grants')
                    ->where('target_type', 'membership')
                    ->whereIn('target_id', $membershipIds)
                    ->delete();

                DB::table('membership_scope')
                    ->whereIn('membership_id', $membershipIds)
                    ->delete();

                DB::table('memberships')
                    ->whereIn('id', $membershipIds)
                    ->delete();
            }

            DB::table('authorization_grants')
                ->where('target_type', 'principal')
                ->where('target_id', (string) $existing['principal_id'])
                ->delete();

            DB::table('principal_functional_actor_links')
                ->where('principal_id', (string) $existing['principal_id'])
                ->delete();

            DB::table('identity_local_login_links')
                ->where('principal_id', (string) $existing['principal_id'])
                ->delete();

            DB::table('identity_local_login_codes')
                ->where('principal_id', (string) $existing['principal_id'])
                ->delete();

            DB::table('identity_local_users')
                ->where('id', $userId)
                ->delete();
        });

        $this->authorizationStore->refresh();

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-local.user.deleted',
            outcome: 'success',
            originComponent: 'identity-local',
            principalId: $managedByPrincipalId,
            organizationId: (string) ($existing['organization_id'] ?? ''),
            targetType: 'identity_local_user',
            targetId: $userId,
            summary: [
                'principal_id' => $existing['principal_id'] ?? null,
                'username' => $existing['username'] ?? null,
                'email' => $existing['email'] ?? null,
            ],
            executionOrigin: 'identity-local',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.identity-local.user.deleted',
            originComponent: 'identity-local',
            organizationId: (string) ($existing['organization_id'] ?? ''),
            payload: [
                'user_id' => $userId,
                'principal_id' => $existing['principal_id'] ?? null,
            ],
        ));

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function membershipsForOrganization(string $organizationId): array
    {
        $records = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->orderBy('principal_id')
            ->orderBy('id')
            ->get();

        $membershipIds = $records->pluck('id')->all();
        $scopesByMembership = [];
        $rolesByMembership = [];

        if ($membershipIds !== []) {
            foreach (DB::table('membership_scope')
                ->whereIn('membership_id', $membershipIds)
                ->orderBy('scope_id')
                ->get(['membership_id', 'scope_id']) as $scope) {
                $scopesByMembership[$scope->membership_id][] = (string) $scope->scope_id;
            }

            foreach (DB::table('authorization_grants')
                ->where('target_type', 'membership')
                ->where('grant_type', 'role')
                ->where('context_type', 'organization')
                ->where('organization_id', $organizationId)
                ->whereIn('target_id', $membershipIds)
                ->orderBy('value')
                ->get(['target_id', 'value']) as $grant) {
                $rolesByMembership[$grant->target_id][] = (string) $grant->value;
            }
        }

        return $records->map(function ($membership) use ($rolesByMembership, $scopesByMembership): array {
            $fallbackRoles = $this->decodeStringArray($membership->roles ?? null);

            return [
                'id' => (string) $membership->id,
                'principal_id' => (string) $membership->principal_id,
                'organization_id' => (string) $membership->organization_id,
                'roles' => array_values(array_unique($rolesByMembership[$membership->id] ?? $fallbackRoles)),
                'scope_ids' => $scopesByMembership[$membership->id] ?? [],
                'is_active' => (bool) $membership->is_active,
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMembership(string $membershipId): ?array
    {
        $membership = DB::table('memberships')->where('id', $membershipId)->first();

        if ($membership === null) {
            return null;
        }

        $scopes = DB::table('membership_scope')
            ->where('membership_id', $membershipId)
            ->orderBy('scope_id')
            ->pluck('scope_id')
            ->map(static fn ($scopeId): string => (string) $scopeId)
            ->all();

        $roles = DB::table('authorization_grants')
            ->where('target_type', 'membership')
            ->where('target_id', $membershipId)
            ->where('grant_type', 'role')
            ->where('context_type', 'organization')
            ->where('organization_id', $membership->organization_id)
            ->orderBy('value')
            ->pluck('value')
            ->map(static fn ($role): string => (string) $role)
            ->all();

        return [
            'id' => (string) $membership->id,
            'principal_id' => (string) $membership->principal_id,
            'organization_id' => (string) $membership->organization_id,
            'roles' => $roles !== [] ? $roles : $this->decodeStringArray($membership->roles ?? null),
            'scope_ids' => $scopes,
            'is_active' => (bool) $membership->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createMembership(array $data, ?string $managedByPrincipalId = null): array
    {
        $principalId = (string) $data['principal_id'];
        $organizationId = (string) $data['organization_id'];
        $membershipId = $this->nextMembershipId($organizationId, $principalId);
        $roles = $this->normalizeStringArray($data['role_keys'] ?? []);
        $scopeIds = $this->normalizeStringArray($data['scope_ids'] ?? []);
        $isActive = (bool) ($data['is_active'] ?? true);

        DB::table('memberships')->insert([
            'id' => $membershipId,
            'principal_id' => $principalId,
            'organization_id' => $organizationId,
            'roles' => $this->encodeJson($roles),
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->syncMembershipScopes($membershipId, $scopeIds);
        $this->syncMembershipRoleGrants($membershipId, $organizationId, $roles);

        $membership = $this->findMembership($membershipId);

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-local.membership.created',
            outcome: 'success',
            originComponent: 'identity-local',
            principalId: $managedByPrincipalId,
            organizationId: $organizationId,
            targetType: 'membership',
            targetId: $membershipId,
            summary: [
                'principal_id' => $principalId,
                'roles' => $roles,
                'scope_ids' => $scopeIds,
            ],
            executionOrigin: 'identity-local',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.identity-local.membership.created',
            originComponent: 'identity-local',
            organizationId: $organizationId,
            payload: [
                'membership_id' => $membershipId,
                'principal_id' => $principalId,
            ],
        ));

        return is_array($membership) ? $membership : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function updateMembership(string $membershipId, array $data, ?string $managedByPrincipalId = null): ?array
    {
        $existing = $this->findMembership($membershipId);

        if ($existing === null) {
            return null;
        }

        $principalId = (string) $data['principal_id'];
        $organizationId = (string) $data['organization_id'];
        $roles = $this->normalizeStringArray($data['role_keys'] ?? []);
        $scopeIds = $this->normalizeStringArray($data['scope_ids'] ?? []);
        $isActive = (bool) ($data['is_active'] ?? false);

        DB::table('memberships')
            ->where('id', $membershipId)
            ->update([
                'principal_id' => $principalId,
                'organization_id' => $organizationId,
                'roles' => $this->encodeJson($roles),
                'is_active' => $isActive,
                'updated_at' => now(),
            ]);

        $this->syncMembershipScopes($membershipId, $scopeIds);
        $this->syncMembershipRoleGrants($membershipId, $organizationId, $roles);

        $membership = $this->findMembership($membershipId);

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-local.membership.updated',
            outcome: 'success',
            originComponent: 'identity-local',
            principalId: $managedByPrincipalId,
            organizationId: $organizationId,
            targetType: 'membership',
            targetId: $membershipId,
            summary: [
                'principal_id' => $principalId,
                'roles' => $roles,
                'scope_ids' => $scopeIds,
                'is_active' => $isActive,
            ],
            executionOrigin: 'identity-local',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.identity-local.membership.updated',
            originComponent: 'identity-local',
            organizationId: $organizationId,
            payload: [
                'membership_id' => $membershipId,
                'principal_id' => $principalId,
            ],
        ));

        return $membership;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsertManagedMembership(string $membershipId, array $data, ?string $managedByPrincipalId = null): array
    {
        $existing = $this->findMembership($membershipId);

        if ($existing === null) {
            $principalId = (string) $data['principal_id'];
            $organizationId = (string) $data['organization_id'];
            $roles = $this->normalizeStringArray($data['role_keys'] ?? []);
            $scopeIds = $this->normalizeStringArray($data['scope_ids'] ?? []);
            $isActive = (bool) ($data['is_active'] ?? true);

            DB::table('memberships')->insert([
                'id' => $membershipId,
                'principal_id' => $principalId,
                'organization_id' => $organizationId,
                'roles' => $this->encodeJson($roles),
                'is_active' => $isActive,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncMembershipScopes($membershipId, $scopeIds);
            $this->syncMembershipRoleGrants($membershipId, $organizationId, $roles);

            return $this->findMembership($membershipId) ?? [];
        }

        return $this->updateMembership($membershipId, $data, $managedByPrincipalId) ?? [];
    }

    /**
     * @param  array<int, string>  $activeExternalSubjects
     * @return array<int, string>
     */
    public function deactivateDirectoryUsersMissingFromSync(
        string $organizationId,
        string $authProvider,
        string $directorySource,
        array $activeExternalSubjects,
    ): array {
        $query = DB::table('identity_local_users')
            ->where('organization_id', $organizationId)
            ->where('auth_provider', $authProvider)
            ->where('directory_source', $directorySource);

        if ($activeExternalSubjects !== []) {
            $query->whereNotIn('external_subject', $activeExternalSubjects);
        }

        $users = $query->get(['id', 'principal_id']);
        $principals = $users->pluck('principal_id')->map(static fn ($principalId): string => (string) $principalId)->all();

        if ($users->isEmpty()) {
            return [];
        }

        DB::table('identity_local_users')
            ->whereIn('id', $users->pluck('id')->all())
            ->update([
                'is_active' => false,
                'directory_synced_at' => now(),
                'updated_at' => now(),
            ]);

        return $principals;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapUser(object $user): array
    {
        $vars = get_object_vars($user);

        return [
            'id' => (string) $user->id,
            'principal_id' => (string) $user->principal_id,
            'auth_provider' => is_string($user->auth_provider ?? null) ? $user->auth_provider : 'local',
            'external_subject' => is_string($user->external_subject ?? null) ? $user->external_subject : null,
            'directory_source' => is_string($user->directory_source ?? null) ? $user->directory_source : null,
            'directory_groups' => $this->decodeStringArray($vars['directory_groups'] ?? null),
            'directory_synced_at' => is_string($user->directory_synced_at ?? null) ? $user->directory_synced_at : null,
            'organization_id' => (string) $user->organization_id,
            'username' => is_string($user->username ?? null) ? $user->username : '',
            'display_name' => (string) $user->display_name,
            'email' => (string) $user->email,
            'password_hash' => is_string($user->password_hash ?? null) ? $user->password_hash : null,
            'password_enabled' => (bool) ($user->password_enabled ?? false),
            'magic_link_enabled' => ! array_key_exists('magic_link_enabled', get_object_vars($user))
                ? true
                : (bool) $user->magic_link_enabled,
            'job_title' => is_string($user->job_title) ? $user->job_title : '',
            'is_active' => (bool) $user->is_active,
        ];
    }

    public function ensurePlatformAdminGrant(string $principalId): void
    {
        $existing = collect($this->authorizationStore->grantRecords())->first(
            static fn (array $grant): bool => ($grant['target_type'] ?? null) === 'principal'
                && ($grant['target_id'] ?? null) === $principalId
                && ($grant['grant_type'] ?? null) === 'role'
                && ($grant['value'] ?? null) === 'platform-admin'
                && ($grant['context_type'] ?? null) === 'platform'
        );

        $this->authorizationStore->upsertGrant(
            id: is_array($existing) ? ($existing['id'] ?? null) : null,
            targetType: 'principal',
            targetId: $principalId,
            grantType: 'role',
            value: 'platform-admin',
            contextType: 'platform',
            organizationId: null,
            scopeId: null,
            isSystem: false,
        );
    }

    public function ensureBootstrapOrganizationAccess(string $principalId, string $organizationId): array
    {
        $existingMembershipId = DB::table('memberships')
            ->where('principal_id', $principalId)
            ->where('organization_id', $organizationId)
            ->value('id');

        $roles = [
            'asset-operator',
            'control-operator',
            'risk-operator',
            'findings-operator',
            'policy-operator',
            'privacy-operator',
            'continuity-operator',
            'assessment-operator',
            'identity-operator',
            'identity-ldap-operator',
        ];

        if (is_string($existingMembershipId) && $existingMembershipId !== '') {
            return $this->findMembership($existingMembershipId) ?? [];
        }

        return $this->createMembership([
            'principal_id' => $principalId,
            'organization_id' => $organizationId,
            'role_keys' => $roles,
            'scope_ids' => [],
            'is_active' => true,
        ], $principalId);
    }

    private function syncMembershipScopes(string $membershipId, array $scopeIds): void
    {
        DB::table('membership_scope')->where('membership_id', $membershipId)->delete();

        foreach (array_values(array_unique($scopeIds)) as $scopeId) {
            DB::table('membership_scope')->insert([
                'membership_id' => $membershipId,
                'scope_id' => $scopeId,
            ]);
        }
    }

    private function syncMembershipRoleGrants(string $membershipId, string $organizationId, array $roles): void
    {
        $existing = DB::table('authorization_grants')
            ->where('target_type', 'membership')
            ->where('target_id', $membershipId)
            ->where('grant_type', 'role')
            ->where('context_type', 'organization')
            ->where('organization_id', $organizationId)
            ->get(['id', 'value']);

        $existingIdsByRole = [];
        $obsoleteGrantIds = [];

        foreach ($existing as $grant) {
            $role = (string) $grant->value;

            if (! in_array($role, $roles, true)) {
                $obsoleteGrantIds[] = (string) $grant->id;

                continue;
            }

            $existingIdsByRole[$role] = (string) $grant->id;
        }

        if ($obsoleteGrantIds !== []) {
            DB::table('authorization_grants')->whereIn('id', $obsoleteGrantIds)->delete();
        }

        foreach ($roles as $role) {
            $this->authorizationStore->upsertGrant(
                id: $existingIdsByRole[$role] ?? null,
                targetType: 'membership',
                targetId: $membershipId,
                grantType: 'role',
                value: $role,
                contextType: 'organization',
                organizationId: $organizationId,
                scopeId: null,
                isSystem: false,
            );
        }

        $this->authorizationStore->refresh();
    }

    private function nextUserId(string $displayName): string
    {
        $base = 'identity-user-'.Str::slug($displayName);
        $candidate = $base !== 'identity-user-' ? $base : 'identity-user-'.Str::lower(Str::ulid());

        if (! DB::table('identity_local_users')->where('id', $candidate)->exists()) {
            return $candidate;
        }

        return $candidate.'-'.Str::lower(Str::random(4));
    }

    private function nextPrincipalId(string $username, string $displayName, string $email): string
    {
        $baseValue = $username !== ''
            ? $username
            : (trim(Str::before($email, '@')) !== '' ? Str::before($email, '@') : $displayName);
        $base = 'principal-'.Str::slug($baseValue);
        $candidate = $base !== 'principal-' ? $base : 'principal-'.Str::lower(Str::ulid());

        if (! $this->principalIdExists($candidate)) {
            return $candidate;
        }

        return $candidate.'-'.Str::lower(Str::random(4));
    }

    private function nextMembershipId(string $organizationId, string $principalId): string
    {
        $base = sprintf('membership-%s-%s', Str::slug($organizationId), Str::slug(Str::after($principalId, 'principal-')));
        $candidate = $base !== 'membership--' ? $base : 'membership-'.Str::lower(Str::ulid());

        if (! DB::table('memberships')->where('id', $candidate)->exists()) {
            return $candidate;
        }

        return $candidate.'-'.Str::lower(Str::random(4));
    }

    private function principalIdExists(string $principalId): bool
    {
        return DB::table('identity_local_users')->where('principal_id', $principalId)->exists()
            || DB::table('memberships')->where('principal_id', $principalId)->exists();
    }

    /**
     * @param  array<int, mixed>|mixed  $values
     * @return array<int, string>
     */
    private function normalizeStringArray(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): ?string => is_string($value) ? trim($value) : null, $values),
            static fn (?string $value): bool => is_string($value) && $value !== '',
        )));
    }

    /**
     * @return array<int, string>
     */
    private function decodeStringArray(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }

    /**
     * @param  array<int, string>  $value
     */
    private function encodeJson(array $value): string
    {
        return json_encode(array_values($value), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
