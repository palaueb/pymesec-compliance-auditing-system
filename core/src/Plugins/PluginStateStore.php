<?php

namespace PymeSec\Core\Plugins;

class PluginStateStore
{
    public function __construct(
        private readonly string $path,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array{enabled: array<int, string>, disabled: array<int, string>}
     */
    public function overrides(): array
    {
        return $this->load();
    }

    /**
     * @param  array<int, string>  $baseEnabled
     * @return array<int, string>
     */
    public function effectiveEnabled(array $baseEnabled): array
    {
        $state = $this->load();
        $effective = array_fill_keys($this->normalize($baseEnabled), true);

        foreach ($state['enabled'] as $pluginId) {
            $effective[$pluginId] = true;
        }

        foreach ($state['disabled'] as $pluginId) {
            unset($effective[$pluginId]);
        }

        return array_keys($effective);
    }

    /**
     * @param  array<int, string>  $baseEnabled
     * @return array{enabled: array<int, string>, disabled: array<int, string>}
     */
    public function enable(string $pluginId, array $baseEnabled): array
    {
        $state = $this->load();
        $pluginId = trim($pluginId);

        $state['disabled'] = array_values(array_filter(
            $state['disabled'],
            static fn (string $candidate): bool => $candidate !== $pluginId,
        ));

        if (! in_array($pluginId, $baseEnabled, true) && ! in_array($pluginId, $state['enabled'], true)) {
            $state['enabled'][] = $pluginId;
        }

        $state['enabled'] = $this->normalize($state['enabled']);
        $this->save($state);

        return $state;
    }

    /**
     * @param  array<int, string>  $baseEnabled
     * @return array{enabled: array<int, string>, disabled: array<int, string>}
     */
    public function disable(string $pluginId, array $baseEnabled): array
    {
        $state = $this->load();
        $pluginId = trim($pluginId);

        $state['enabled'] = array_values(array_filter(
            $state['enabled'],
            static fn (string $candidate): bool => $candidate !== $pluginId,
        ));

        if (in_array($pluginId, $baseEnabled, true) && ! in_array($pluginId, $state['disabled'], true)) {
            $state['disabled'][] = $pluginId;
        }

        $state['disabled'] = $this->normalize($state['disabled']);
        $this->save($state);

        return $state;
    }

    public function clear(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * @return array{enabled: array<int, string>, disabled: array<int, string>}
     */
    private function load(): array
    {
        if (! is_file($this->path)) {
            return [
                'enabled' => [],
                'disabled' => [],
            ];
        }

        $contents = @file_get_contents($this->path);

        if ($contents === false) {
            return [
                'enabled' => [],
                'disabled' => [],
            ];
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return [
                'enabled' => [],
                'disabled' => [],
            ];
        }

        return [
            'enabled' => $this->normalize($decoded['enabled'] ?? []),
            'disabled' => $this->normalize($decoded['disabled'] ?? []),
        ];
    }

    /**
     * @param  array{enabled: array<int, string>, disabled: array<int, string>}  $state
     */
    private function save(array $state): void
    {
        if ($state['enabled'] === [] && $state['disabled'] === []) {
            $this->clear();

            return;
        }

        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $this->path,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }

    /**
     * @param  array<int, mixed>  $pluginIds
     * @return array<int, string>
     */
    private function normalize(array $pluginIds): array
    {
        $normalized = [];

        foreach ($pluginIds as $pluginId) {
            if (! is_string($pluginId)) {
                continue;
            }

            $pluginId = trim($pluginId);

            if ($pluginId === '') {
                continue;
            }

            $normalized[$pluginId] = true;
        }

        return array_keys($normalized);
    }
}
