<?php

namespace PymeSec\Core\Plugins;

use DirectoryIterator;

class PluginDiscovery
{
    /**
     * @param  array<int, string>  $paths
     */
    public function __construct(
        private readonly array $paths,
    ) {}

    /**
     * @return array<int, PluginDescriptor>
     */
    public function discover(): array
    {
        $plugins = [];

        foreach ($this->paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach (new DirectoryIterator($path) as $directory) {
                if (! $directory->isDir() || $directory->isDot()) {
                    continue;
                }

                $manifestPath = $directory->getPathname().'/plugin.json';

                if (! is_file($manifestPath)) {
                    continue;
                }

                $manifest = PluginManifest::fromFile($manifestPath);
                $plugins[$manifest->id()] = new PluginDescriptor(
                    path: $directory->getPathname(),
                    manifest: $manifest,
                );
            }
        }

        ksort($plugins);

        return array_values($plugins);
    }
}
