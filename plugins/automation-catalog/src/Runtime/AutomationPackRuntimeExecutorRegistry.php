<?php

namespace PymeSec\Plugins\AutomationCatalog\Runtime;

class AutomationPackRuntimeExecutorRegistry
{
    /**
     * @param  array<int, AutomationPackRuntimeExecutorInterface>  $executors
     */
    public function __construct(
        private readonly array $executors,
    ) {}

    /**
     * @param  array<string, string>  $pack
     */
    public function resolve(array $pack): ?AutomationPackRuntimeExecutorInterface
    {
        foreach ($this->executors as $executor) {
            if ($executor->supports($pack)) {
                return $executor;
            }
        }

        return null;
    }
}

