<?php

namespace PymeSec\Core\Permissions;

use InvalidArgumentException;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;

class PermissionRegistry implements PermissionRegistryInterface
{
    /**
     * @var array<string, PermissionDefinition>
     */
    private array $definitions = [];

    public function register(PermissionDefinition $definition): void
    {
        $this->assertValidKey($definition->key);
        $this->definitions[$definition->key] = $definition;
        ksort($this->definitions);
    }

    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function forOrigin(string $origin): array
    {
        return array_values(array_filter(
            $this->definitions,
            static fn (PermissionDefinition $definition): bool => $definition->origin === $origin,
        ));
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function find(string $key): ?PermissionDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    private function assertValidKey(string $key): void
    {
        if (! preg_match('/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/', $key)) {
            throw new InvalidArgumentException(sprintf(
                'Permission key [%s] is invalid. Use lowercase namespaced keys.',
                $key,
            ));
        }
    }
}
