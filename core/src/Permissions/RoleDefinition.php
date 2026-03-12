<?php

namespace PymeSec\Core\Permissions;

class RoleDefinition
{
    /**
     * @param  array<int, string>  $permissions
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $permissions,
    ) {
    }
}
