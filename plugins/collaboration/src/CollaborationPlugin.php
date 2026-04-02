<?php

namespace PymeSec\Plugins\Collaboration;

use PymeSec\Core\Collaboration\Contracts\CollaborationEngineInterface;
use PymeSec\Core\Collaboration\Contracts\CollaborationStoreInterface;
use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;

class CollaborationPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(CollaborationEngineInterface::class, function (): CollaborationEngineInterface {
            return new CollaborationEngine;
        });

        $context->app()->singleton(CollaborationStoreInterface::class, function (): CollaborationStoreInterface {
            return new CollaborationStore;
        });
    }

    public function boot(PluginContext $context): void
    {
        //
    }
}
