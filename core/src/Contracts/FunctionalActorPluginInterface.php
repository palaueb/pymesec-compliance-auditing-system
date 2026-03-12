<?php

namespace PymeSec\Core\Contracts;

use PymeSec\Core\Plugins\Contracts\PluginInterface;

interface FunctionalActorPluginInterface extends PluginInterface
{
    public function functionalActorProviderKey(): string;
}
