<?php

namespace PymeSec\Core\Permissions\Contracts;

use PymeSec\Core\Permissions\PermissionGrant;
use PymeSec\Core\Permissions\RoleDefinition;

interface AuthorizationStoreInterface
{
    /**
     * @return array<string, RoleDefinition>
     */
    public function roleDefinitions(): array;

    /**
     * @return array<int, PermissionGrant>
     */
    public function grantDefinitions(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function roleRecords(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function grantRecords(): array;

    /**
     * @param  array<int, string>  $permissions
     */
    public function upsertRole(string $key, string $label, array $permissions, bool $isSystem = false): RoleDefinition;

    /**
     * @return array<string, mixed>
     */
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
    ): array;
}
