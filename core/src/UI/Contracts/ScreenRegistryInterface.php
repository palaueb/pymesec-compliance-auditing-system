<?php

namespace PymeSec\Core\UI\Contracts;

use PymeSec\Core\UI\ScreenDefinition;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\UI\ScreenViewModel;

interface ScreenRegistryInterface
{
    public function register(ScreenDefinition $definition): void;

    public function has(string $menuId): bool;

    public function definition(string $menuId): ?ScreenDefinition;

    public function render(string $menuId, ScreenRenderContext $context): ?ScreenViewModel;
}
