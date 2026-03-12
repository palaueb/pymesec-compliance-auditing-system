<?php

namespace PymeSec\Core\Permissions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PymeSec\Core\Permissions\Contracts\AuthorizationStoreInterface;

class DatabaseAuthorizationStore implements AuthorizationStoreInterface
{
    /**
     * @var array<string, RoleDefinition>|null
     */
    private ?array $roleDefinitions = null;

    /**
     * @var array<int, PermissionGrant>|null
     */
    private ?array $grantDefinitions = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $roleRecords = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $grantRecords = null;

    public function roleDefinitions(): array
    {
        if ($this->roleDefinitions !== null) {
            return $this->roleDefinitions;
        }

        $records = $this->roleRecords();
        $roles = [];

        foreach ($records as $record) {
            $key = $record['key'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $roles[$key] = new RoleDefinition(
                key: $key,
                label: (string) ($record['label'] ?? $key),
                permissions: array_values(array_filter(
                    $record['permissions'] ?? [],
                    static fn (mixed $permission): bool => is_string($permission) && $permission !== '',
                )),
            );
        }

        return $this->roleDefinitions = $roles;
    }

    public function grantDefinitions(): array
    {
        if ($this->grantDefinitions !== null) {
            return $this->grantDefinitions;
        }

        $grants = [];

        foreach ($this->grantRecords() as $record) {
            $targetType = $record['target_type'] ?? null;
            $targetId = $record['target_id'] ?? null;
            $grantType = $record['grant_type'] ?? null;
            $value = $record['value'] ?? null;
            $contextType = $record['context_type'] ?? null;

            if (! is_string($targetType) || ! is_string($targetId) || ! is_string($grantType) || ! is_string($value) || ! is_string($contextType)) {
                continue;
            }

            $grants[] = new PermissionGrant(
                targetType: $targetType,
                targetId: $targetId,
                grantType: $grantType,
                value: $value,
                contextType: $contextType,
                organizationId: is_string($record['organization_id'] ?? null) ? $record['organization_id'] : null,
                scopeId: is_string($record['scope_id'] ?? null) ? $record['scope_id'] : null,
            );
        }

        return $this->grantDefinitions = $grants;
    }

    public function roleRecords(): array
    {
        if ($this->roleRecords !== null) {
            return $this->roleRecords;
        }

        if (! $this->databaseIsReady('authorization_roles', 'authorization_role_permissions')) {
            return $this->roleRecords = $this->fallbackRoleRecords();
        }

        $rows = DB::table('authorization_roles')
            ->leftJoin('authorization_role_permissions', 'authorization_roles.key', '=', 'authorization_role_permissions.role_key')
            ->orderBy('authorization_roles.key')
            ->get([
                'authorization_roles.key',
                'authorization_roles.label',
                'authorization_roles.is_system',
                'authorization_role_permissions.permission_key',
            ]);

        if ($rows->isEmpty()) {
            return $this->roleRecords = $this->fallbackRoleRecords();
        }

        $grouped = [];

        foreach ($rows as $row) {
            $key = (string) $row->key;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'key' => $key,
                    'label' => (string) $row->label,
                    'permissions' => [],
                    'is_system' => (bool) $row->is_system,
                ];
            }

            if (is_string($row->permission_key) && $row->permission_key !== '') {
                $grouped[$key]['permissions'][] = $row->permission_key;
            }
        }

        foreach ($grouped as &$role) {
            $role['permissions'] = array_values(array_unique($role['permissions']));
        }

        return $this->roleRecords = array_values($grouped);
    }

    public function grantRecords(): array
    {
        if ($this->grantRecords !== null) {
            return $this->grantRecords;
        }

        if (! $this->databaseIsReady('authorization_grants')) {
            return $this->grantRecords = $this->fallbackGrantRecords();
        }

        $rows = DB::table('authorization_grants')
            ->orderBy('target_type')
            ->orderBy('target_id')
            ->orderBy('value')
            ->get();

        if ($rows->isEmpty()) {
            return $this->grantRecords = $this->fallbackGrantRecords();
        }

        return $this->grantRecords = $rows->map(static fn ($row): array => [
            'id' => (string) $row->id,
            'target_type' => (string) $row->target_type,
            'target_id' => (string) $row->target_id,
            'grant_type' => (string) $row->grant_type,
            'value' => (string) $row->value,
            'context_type' => (string) $row->context_type,
            'organization_id' => is_string($row->organization_id) ? $row->organization_id : null,
            'scope_id' => is_string($row->scope_id) ? $row->scope_id : null,
            'is_system' => (bool) $row->is_system,
        ])->all();
    }

    public function upsertRole(string $key, string $label, array $permissions, bool $isSystem = false): RoleDefinition
    {
        DB::table('authorization_roles')->updateOrInsert(
            ['key' => $key],
            [
                'label' => $label,
                'is_system' => $isSystem,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        DB::table('authorization_role_permissions')->where('role_key', $key)->delete();

        foreach (array_values(array_unique(array_filter(
            $permissions,
            static fn (mixed $permission): bool => is_string($permission) && $permission !== '',
        ))) as $permission) {
            DB::table('authorization_role_permissions')->insert([
                'role_key' => $key,
                'permission_key' => $permission,
            ]);
        }

        $this->flushCaches();

        return $this->roleDefinitions()[$key];
    }

    public function upsertGrant(
        ?string $id,
        string $targetType,
        string $targetId,
        string $grantType,
        string $value,
        string $contextType,
        ?string $organizationId = null,
        ?string $scopeId = null,
        bool $isSystem = false,
    ): array {
        $id = is_string($id) && $id !== '' ? $id : 'grant-'.Str::lower(Str::ulid());

        DB::table('authorization_grants')->updateOrInsert(
            ['id' => $id],
            [
                'target_type' => $targetType,
                'target_id' => $targetId,
                'grant_type' => $grantType,
                'value' => $value,
                'context_type' => $contextType,
                'organization_id' => $organizationId,
                'scope_id' => $scopeId,
                'is_system' => $isSystem,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->flushCaches();

        $record = collect($this->grantRecords())->first(
            static fn (array $grant): bool => ($grant['id'] ?? null) === $id
        );

        return is_array($record) ? $record : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fallbackRoleRecords(): array
    {
        $records = [];

        foreach (config('authorization.roles', []) as $key => $role) {
            if (! is_string($key) || ! is_array($role)) {
                continue;
            }

            $records[] = [
                'key' => $key,
                'label' => (string) ($role['label'] ?? $key),
                'permissions' => array_values(array_filter(
                    $role['permissions'] ?? [],
                    static fn (mixed $permission): bool => is_string($permission) && $permission !== '',
                )),
                'is_system' => true,
            ];
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fallbackGrantRecords(): array
    {
        $records = [];

        foreach (config('authorization.grants', []) as $index => $grant) {
            if (! is_array($grant)) {
                continue;
            }

            $targetType = $grant['target_type'] ?? null;
            $targetId = $grant['target_id'] ?? null;
            $grantType = $grant['grant_type'] ?? null;
            $value = $grant['value'] ?? null;
            $contextType = $grant['context_type'] ?? null;

            if (! is_string($targetType) || ! is_string($targetId) || ! is_string($grantType) || ! is_string($value) || ! is_string($contextType)) {
                continue;
            }

            $records[] = [
                'id' => sprintf('config-grant-%03d', $index + 1),
                'target_type' => $targetType,
                'target_id' => $targetId,
                'grant_type' => $grantType,
                'value' => $value,
                'context_type' => $contextType,
                'organization_id' => is_string($grant['organization_id'] ?? null) ? $grant['organization_id'] : null,
                'scope_id' => is_string($grant['scope_id'] ?? null) ? $grant['scope_id'] : null,
                'is_system' => true,
            ];
        }

        return $records;
    }

    private function databaseIsReady(string ...$tables): bool
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function flushCaches(): void
    {
        $this->roleDefinitions = null;
        $this->grantDefinitions = null;
        $this->roleRecords = null;
        $this->grantRecords = null;
    }
}
