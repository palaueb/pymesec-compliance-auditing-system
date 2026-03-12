<?php

namespace PymeSec\Core\Plugins;

class PluginRouteDefinition
{
    /**
     * @param  array<int, string>  $middleware
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $file,
        public readonly array $middleware = [],
        public readonly ?string $prefix = null,
        public readonly ?string $permission = null,
    ) {
    }
}
