<?php

namespace PymeSec\Core\Plugins;

class PluginLifecycleResult
{
    /**
     * @param  array<int, string>  $effectiveBefore
     * @param  array<int, string>  $effectiveAfter
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $pluginId,
        public readonly string $operation,
        public readonly string $message,
        public readonly ?string $reason = null,
        public readonly array $effectiveBefore = [],
        public readonly array $effectiveAfter = [],
        public readonly array $details = [],
    ) {}

    public function changed(): bool
    {
        return $this->effectiveBefore !== $this->effectiveAfter;
    }
}
