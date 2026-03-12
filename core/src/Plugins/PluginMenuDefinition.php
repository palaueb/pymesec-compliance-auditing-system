<?php

namespace PymeSec\Core\Plugins;

class PluginMenuDefinition
{
    public function __construct(
        public readonly string $id,
        public readonly string $labelKey,
        public readonly ?string $routeName = null,
        public readonly ?string $parentId = null,
        public readonly ?string $icon = null,
        public readonly int $order = 100,
        public readonly ?string $permission = null,
    ) {
    }
}
