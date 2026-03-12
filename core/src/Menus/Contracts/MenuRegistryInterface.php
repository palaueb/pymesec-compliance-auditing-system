<?php

namespace PymeSec\Core\Menus\Contracts;

use PymeSec\Core\Menus\MenuDefinition;
use PymeSec\Core\Menus\MenuVisibilityContext;

interface MenuRegistryInterface
{
    public function registerCore(MenuDefinition $definition): void;

    /**
     * @param  array<int, string>  $dependencyPluginIds
     */
    public function registerPlugin(MenuDefinition $definition, array $dependencyPluginIds = []): void;

    public function finalize(): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function visible(MenuVisibilityContext $context): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function issues(): array;
}
