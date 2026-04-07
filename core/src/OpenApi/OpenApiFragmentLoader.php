<?php

namespace PymeSec\Core\OpenApi;

class OpenApiFragmentLoader
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function loadFragments(): array
    {
        $fragments = [];

        foreach ($this->fragmentPaths() as $path) {
            $contents = @file_get_contents($path);

            if (! is_string($contents) || trim($contents) === '') {
                continue;
            }

            $decoded = json_decode($contents, true);

            if (! is_array($decoded)) {
                continue;
            }

            $fragments[] = $decoded;
        }

        return $fragments;
    }

    /**
     * @return array<int, string>
     */
    private function fragmentPaths(): array
    {
        $paths = [];
        $corePattern = base_path('openapi/fragments/*.json');

        foreach (glob($corePattern) ?: [] as $path) {
            if (is_string($path) && is_file($path)) {
                $paths[] = $path;
            }
        }

        foreach ((array) config('plugins.paths', []) as $pluginsBasePath) {
            if (! is_string($pluginsBasePath) || trim($pluginsBasePath) === '') {
                continue;
            }

            $pattern = rtrim($pluginsBasePath, '/').'/*/openapi/fragments/*.json';

            foreach (glob($pattern) ?: [] as $path) {
                if (is_string($path) && is_file($path)) {
                    $paths[] = $path;
                }
            }
        }

        sort($paths);

        return array_values(array_unique($paths));
    }
}
