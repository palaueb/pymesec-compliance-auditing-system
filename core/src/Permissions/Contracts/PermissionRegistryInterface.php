<?php

namespace PymeSec\Core\Permissions\Contracts;

use PymeSec\Core\Permissions\PermissionDefinition;

interface PermissionRegistryInterface
{
    public function register(PermissionDefinition $definition): void;

    /**
     * @return array<int, PermissionDefinition>
     */
    public function all(): array;

    /**
     * @return array<int, PermissionDefinition>
     */
    public function forOrigin(string $origin): array;

    public function has(string $key): bool;

    public function find(string $key): ?PermissionDefinition;
}
