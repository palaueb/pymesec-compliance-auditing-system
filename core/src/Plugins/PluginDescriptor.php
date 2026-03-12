<?php

namespace PymeSec\Core\Plugins;

class PluginDescriptor
{
    public function __construct(
        private readonly string $path,
        private readonly PluginManifest $manifest,
    ) {}

    public function id(): string
    {
        return $this->manifest->id();
    }

    public function path(): string
    {
        return $this->path;
    }

    public function manifest(): PluginManifest
    {
        return $this->manifest;
    }
}
