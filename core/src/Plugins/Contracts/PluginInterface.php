<?php

namespace PymeSec\Core\Plugins\Contracts;

use PymeSec\Core\Plugins\PluginContext;

interface PluginInterface
{
    public function register(PluginContext $context): void;

    public function boot(PluginContext $context): void;
}
