<?php

namespace PymeSec\Core\Plugins\Contracts;

interface PluginManagerInterface
{
    public function boot(): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function status(): array;

    /**
     * @return array<string, PluginInterface>
     */
    public function active(): array;

    public function has(string $pluginId): bool;

    public function plugin(string $pluginId): ?PluginInterface;
}
