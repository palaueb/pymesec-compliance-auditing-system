<?php

namespace PymeSec\Plugins\IdentityLocal;

use PymeSec\Core\Contracts\IdentityPluginInterface;
use PymeSec\Core\Plugins\PluginContext;

class IdentityLocalPlugin implements IdentityPluginInterface
{
    public function identityProviderKey(): string
    {
        return 'identity-local';
    }

    public function register(PluginContext $context): void
    {
        //
    }

    public function boot(PluginContext $context): void
    {
        //
    }
}
