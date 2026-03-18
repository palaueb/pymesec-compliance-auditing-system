<?php

namespace PymeSec\Plugins\FrameworkEns;

use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;

class FrameworkEnsPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        // Framework pack plugins have no runtime behavior.
        // All content is seeded by EnsFrameworkSeeder via SystemBootstrapSeeder.
    }

    public function boot(PluginContext $context): void
    {
        //
    }
}
