<?php

namespace PymeSec\Core\Menus;

class MenuRegistration
{
    /**
     * @param  array<int, string>  $dependencyPluginIds
     */
    public function __construct(
        public readonly MenuDefinition $definition,
        public readonly array $dependencyPluginIds = [],
    ) {}
}
