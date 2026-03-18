<?php

namespace PymeSec\Plugins\FrameworkGdpr;

use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;

class FrameworkGdprPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        // Framework pack plugins have no runtime behavior.
        // All content is seeded by GdprFrameworkSeeder via SystemBootstrapSeeder.
    }

    public function boot(PluginContext $context): void
    {
        //
    }
}
