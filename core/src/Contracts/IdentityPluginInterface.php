<?php

namespace PymeSec\Core\Contracts;

use PymeSec\Core\Plugins\Contracts\PluginInterface;

interface IdentityPluginInterface extends PluginInterface
{
    public function identityProviderKey(): string;
}
