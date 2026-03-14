<?php

namespace PymeSec\Plugins\IdentityLdap;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;

class IdentityLdapRepository
{
    public function __construct(
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function connectionForOrganization(string $organizationId): ?array
    {
        $record = DB::table('identity_ldap_connections')
            ->where('organization_id', $organizationId)
            ->first();

        return $record !== null ? $this->mapConnection($record) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsertConnection(string $organizationId, array $data, ?string $managedByPrincipalId = null): array
    {
        $existing = $this->connectionForOrganization($organizationId);
        $connectionId = $existing['id'] ?? ('ldap-'.Str::slug($organizationId));

        DB::table('identity_ldap_connections')->updateOrInsert(
            ['organization_id' => $organizationId],
            [
                'id' => $connectionId,
                'name' => (string) $data['name'],
                'host' => (string) $data['host'],
                'port' => (int) ($data['port'] ?? 389),
                'base_dn' => (string) $data['base_dn'],
                'bind_dn' => is_string($data['bind_dn'] ?? null) && $data['bind_dn'] !== '' ? $data['bind_dn'] : null,
                'bind_password' => is_string($data['bind_password'] ?? null) && $data['bind_password'] !== ''
                    ? $data['bind_password']
                    : ($existing['bind_password'] ?? null),
                'user_dn_attribute' => (string) ($data['user_dn_attribute'] ?? 'uid'),
                'mail_attribute' => (string) ($data['mail_attribute'] ?? 'mail'),
                'display_name_attribute' => (string) ($data['display_name_attribute'] ?? 'cn'),
                'job_title_attribute' => (string) ($data['job_title_attribute'] ?? 'title'),
                'group_attribute' => (string) ($data['group_attribute'] ?? 'memberOf'),
                'login_mode' => (string) ($data['login_mode'] ?? 'username'),
                'sync_interval_minutes' => (int) ($data['sync_interval_minutes'] ?? 60),
                'user_filter' => is_string($data['user_filter'] ?? null) && $data['user_filter'] !== '' ? $data['user_filter'] : null,
                'fallback_email_enabled' => (bool) ($data['fallback_email_enabled'] ?? true),
                'is_enabled' => (bool) ($data['is_enabled'] ?? true),
                'updated_at' => now(),
                'created_at' => $existing === null ? now() : DB::raw('created_at'),
            ],
        );

        $connection = $this->connectionForOrganization($organizationId) ?? [];

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-ldap.connection.saved',
            outcome: 'success',
            originComponent: 'identity-ldap',
            principalId: $managedByPrincipalId,
            organizationId: $organizationId,
            targetType: 'identity_ldap_connection',
            targetId: $connectionId,
            summary: [
                'login_mode' => $connection['login_mode'] ?? null,
                'sync_interval_minutes' => $connection['sync_interval_minutes'] ?? null,
                'is_enabled' => $connection['is_enabled'] ?? null,
            ],
            executionOrigin: 'identity-ldap',
        ));

        return $connection;
    }

    public function markSyncStarted(string $connectionId): void
    {
        DB::table('identity_ldap_connections')
            ->where('id', $connectionId)
            ->update([
                'last_sync_started_at' => now(),
                'last_sync_status' => 'running',
                'last_sync_message' => 'Directory synchronization is running.',
                'updated_at' => now(),
            ]);
    }

    public function markSyncCompleted(string $connectionId, string $status, string $message): void
    {
        DB::table('identity_ldap_connections')
            ->where('id', $connectionId)
            ->update([
                'last_sync_completed_at' => now(),
                'last_sync_status' => $status,
                'last_sync_message' => Str::limit($message, 1000, ''),
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mappingsForConnection(string $connectionId): array
    {
        return DB::table('identity_ldap_group_mappings')
            ->where('connection_id', $connectionId)
            ->orderBy('ldap_group')
            ->get()
            ->map(fn ($mapping): array => $this->mapMapping($mapping))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsertMapping(string $connectionId, array $data, ?string $managedByPrincipalId = null): array
    {
        $group = trim((string) $data['ldap_group']);
        $existing = DB::table('identity_ldap_group_mappings')
            ->where('connection_id', $connectionId)
            ->whereRaw('LOWER(ldap_group) = ?', [Str::lower($group)])
            ->first();
        $mappingId = is_string($data['id'] ?? null) && $data['id'] !== ''
            ? (string) $data['id']
            : (is_object($existing) ? (string) $existing->id : 'ldap-mapping-'.Str::lower(Str::ulid()));

        DB::table('identity_ldap_group_mappings')->updateOrInsert(
            ['id' => $mappingId],
            [
                'connection_id' => $connectionId,
                'ldap_group' => $group,
                'role_keys' => $this->encodeJson($this->normalizeStringArray($data['role_keys'] ?? [])),
                'scope_ids' => $this->encodeJson($this->normalizeStringArray($data['scope_ids'] ?? [])),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'updated_at' => now(),
                'created_at' => is_object($existing) ? DB::raw('created_at') : now(),
            ],
        );

        $mapping = DB::table('identity_ldap_group_mappings')->where('id', $mappingId)->first();

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-ldap.mapping.saved',
            outcome: 'success',
            originComponent: 'identity-ldap',
            principalId: $managedByPrincipalId,
            targetType: 'identity_ldap_group_mapping',
            targetId: $mappingId,
            summary: [
                'connection_id' => $connectionId,
                'ldap_group' => $group,
            ],
            executionOrigin: 'identity-ldap',
        ));

        return $mapping !== null ? $this->mapMapping($mapping) : [];
    }

    /**
     * @param  array<int, string>  $groupNames
     * @return array{roles:array<int, string>, scopes:array<int, string>}
     */
    public function resolveMappingAccess(string $connectionId, array $groupNames): array
    {
        $normalizedGroups = array_map(static fn (string $group): string => Str::lower(trim($group)), $groupNames);
        $roles = [];
        $scopes = [];

        foreach ($this->mappingsForConnection($connectionId) as $mapping) {
            if (! ($mapping['is_active'] ?? false)) {
                continue;
            }

            if (! in_array(Str::lower((string) $mapping['ldap_group']), $normalizedGroups, true)) {
                continue;
            }

            $roles = [...$roles, ...($mapping['role_keys'] ?? [])];
            $scopes = [...$scopes, ...($mapping['scope_ids'] ?? [])];
        }

        return [
            'roles' => array_values(array_unique($roles)),
            'scopes' => array_values(array_unique($scopes)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cachedUsersForOrganization(string $organizationId): array
    {
        return DB::table('identity_local_users')
            ->where('organization_id', $organizationId)
            ->where('auth_provider', 'ldap')
            ->orderBy('display_name')
            ->get()
            ->map(function ($user): array {
                $vars = get_object_vars($user);

                return [
                    'id' => (string) $user->id,
                    'principal_id' => (string) $user->principal_id,
                    'username' => (string) $user->username,
                    'display_name' => (string) $user->display_name,
                    'email' => (string) $user->email,
                    'job_title' => is_string($user->job_title ?? null) ? $user->job_title : '',
                    'external_subject' => is_string($user->external_subject ?? null) ? $user->external_subject : '',
                    'directory_source' => is_string($user->directory_source ?? null) ? $user->directory_source : '',
                    'directory_groups' => $this->decodeStringArray($vars['directory_groups'] ?? null),
                    'directory_synced_at' => is_string($user->directory_synced_at ?? null) ? $user->directory_synced_at : null,
                    'is_active' => (bool) $user->is_active,
                ];
            })
            ->all();
    }

    public function publishSyncEvent(string $organizationId, array $payload): void
    {
        $this->events->publish(new PublicEvent(
            name: 'plugin.identity-ldap.sync.completed',
            originComponent: 'identity-ldap',
            organizationId: $organizationId,
            payload: $payload,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapConnection(object $connection): array
    {
        return [
            'id' => (string) $connection->id,
            'organization_id' => (string) $connection->organization_id,
            'name' => (string) $connection->name,
            'host' => (string) $connection->host,
            'port' => (int) $connection->port,
            'base_dn' => (string) $connection->base_dn,
            'bind_dn' => is_string($connection->bind_dn ?? null) ? $connection->bind_dn : '',
            'bind_password' => is_string($connection->bind_password ?? null) ? $connection->bind_password : null,
            'user_dn_attribute' => (string) ($connection->user_dn_attribute ?? 'uid'),
            'mail_attribute' => (string) ($connection->mail_attribute ?? 'mail'),
            'display_name_attribute' => (string) ($connection->display_name_attribute ?? 'cn'),
            'job_title_attribute' => (string) ($connection->job_title_attribute ?? 'title'),
            'group_attribute' => (string) ($connection->group_attribute ?? 'memberOf'),
            'login_mode' => (string) ($connection->login_mode ?? 'username'),
            'sync_interval_minutes' => (int) ($connection->sync_interval_minutes ?? 60),
            'user_filter' => is_string($connection->user_filter ?? null) ? $connection->user_filter : '',
            'fallback_email_enabled' => (bool) ($connection->fallback_email_enabled ?? true),
            'is_enabled' => (bool) ($connection->is_enabled ?? true),
            'last_sync_started_at' => is_string($connection->last_sync_started_at ?? null) ? $connection->last_sync_started_at : null,
            'last_sync_completed_at' => is_string($connection->last_sync_completed_at ?? null) ? $connection->last_sync_completed_at : null,
            'last_sync_status' => is_string($connection->last_sync_status ?? null) ? $connection->last_sync_status : '',
            'last_sync_message' => is_string($connection->last_sync_message ?? null) ? $connection->last_sync_message : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMapping(object $mapping): array
    {
        return [
            'id' => (string) $mapping->id,
            'connection_id' => (string) $mapping->connection_id,
            'ldap_group' => (string) $mapping->ldap_group,
            'role_keys' => $this->decodeStringArray($mapping->role_keys ?? null),
            'scope_ids' => $this->decodeStringArray($mapping->scope_ids ?? null),
            'is_active' => (bool) $mapping->is_active,
        ];
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
