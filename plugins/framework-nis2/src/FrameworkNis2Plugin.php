<?php

namespace PymeSec\Plugins\FrameworkNis2;

use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;

class FrameworkNis2Plugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        // Framework pack plugin — no runtime behavior.
        // All content is seeded by Nis2FrameworkSeeder via SystemBootstrapSeeder.
    }

    public function boot(PluginContext $context): void
    {
        //
    }
}
