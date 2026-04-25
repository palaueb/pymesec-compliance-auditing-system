<?php

namespace PymeSec\Plugins\FrameworkPlatform\Contracts;

interface FrameworkPlatformRegistryInterface
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function register(string $frameworkId, array $definition): void;

    public function has(string $frameworkId): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function definition(string $frameworkId): ?array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array;
}
