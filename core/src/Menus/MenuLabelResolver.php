<?php

namespace PymeSec\Core\Menus;

use Illuminate\Support\Facades\Lang;
use PymeSec\Core\Plugins\Contracts\PluginManagerInterface;

class MenuLabelResolver
{
    private bool $pluginPathsLoaded = false;

    /**
     * @var array<string, string>
     */
    private array $pluginPaths = [];

    /**
     * @var array<string, array<string, array<string, string>>>
     */
    private array $catalogues = [];

    public function __construct(
        private readonly PluginManagerInterface $plugins,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $menus
     * @return array<int, array<string, mixed>>
     */
    public function resolveTree(array $menus, string $locale): array
    {
        return array_map(fn (array $menu): array => $this->resolveItem($menu, $locale), $menus);
    }

    /**
     * @param  array<string, mixed>  $menu
     * @return array<string, mixed>
     */
    private function resolveItem(array $menu, string $locale): array
    {
        $owner = is_string($menu['owner'] ?? null) ? $menu['owner'] : 'core';
        $labelKey = is_string($menu['label_key'] ?? null) ? $menu['label_key'] : '';

        return [
            ...$menu,
            'label' => $this->label($owner, $labelKey, $locale),
            'children' => array_map(
                fn (array $child): array => $this->resolveItem($child, $locale),
                is_array($menu['children'] ?? null) ? $menu['children'] : [],
            ),
        ];
    }

    public function label(string $owner, string $labelKey, string $locale): string
    {
        if ($owner === 'core') {
            $translated = Lang::get($labelKey, [], $locale);

            return is_string($translated) ? $translated : $labelKey;
        }

        $catalogue = $this->pluginCatalogue($owner, $locale);

        if (isset($catalogue[$labelKey])) {
            return $catalogue[$labelKey];
        }

        $fallback = $this->pluginCatalogue($owner, 'en');

        return $fallback[$labelKey] ?? $labelKey;
    }

    /**
     * @return array<string, string>
     */
    private function pluginCatalogue(string $pluginId, string $locale): array
    {
        $this->loadPluginPaths();

        if (isset($this->catalogues[$pluginId][$locale])) {
            return $this->catalogues[$pluginId][$locale];
        }

        $path = $this->pluginPaths[$pluginId] ?? null;

        if ($path === null) {
            return $this->catalogues[$pluginId][$locale] = [];
        }

        $file = $path.'/resources/lang/'.$locale.'.json';

        if (! is_file($file)) {
            return $this->catalogues[$pluginId][$locale] = [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        if (! is_array($decoded)) {
            return $this->catalogues[$pluginId][$locale] = [];
        }

        $catalogue = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_string($value) && $key !== '') {
                $catalogue[$key] = $value;
            }
        }

        return $this->catalogues[$pluginId][$locale] = $catalogue;
    }

    private function loadPluginPaths(): void
    {
        if ($this->pluginPathsLoaded) {
            return;
        }

        $this->plugins->boot();

        foreach ($this->plugins->status() as $plugin) {
            if (! is_array($plugin)) {
                continue;
            }

            $pluginId = $plugin['id'] ?? null;
            $path = $plugin['path'] ?? null;

            if (! is_string($pluginId) || ! is_string($path) || $pluginId === '' || $path === '') {
                continue;
            }

            $this->pluginPaths[$pluginId] = $path;
        }

        $this->pluginPathsLoaded = true;
    }
}
