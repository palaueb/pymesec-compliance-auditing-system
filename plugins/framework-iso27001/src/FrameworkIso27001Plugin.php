<?php

namespace PymeSec\Plugins\FrameworkIso27001;

use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;

class FrameworkIso27001Plugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        // Framework pack plugins have no runtime behavior.
        // All content is seeded by Iso27001FrameworkSeeder via SystemBootstrapSeeder.
    }

    public function boot(PluginContext $context): void
    {
        //
    }
}
