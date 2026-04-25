<?php

namespace PymeSec\Plugins\FrameworkPlatform;

use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Plugins\FrameworkPlatform\Contracts\FrameworkPlatformRegistryInterface;

class FrameworkPlatformPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(FrameworkPlatformRegistryInterface::class, function (): FrameworkPlatformRegistryInterface {
            return new FrameworkPlatformRegistry;
        });
    }

    public function boot(PluginContext $context): void
    {
        //
    }
}
