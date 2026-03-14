<?php

namespace PymeSec\Core\Plugins;

use InvalidArgumentException;
use PymeSec\Core\Permissions\PermissionDefinition;

class PluginManifest
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly array $data,
    ) {
        $this->assertRequiredPluginFields();
    }

    public static function fromFile(string $path): self
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException(sprintf('Unable to read plugin manifest [%s].', $path));
        }

        $data = json_decode($contents, true);

        if (! is_array($data)) {
            throw new InvalidArgumentException(sprintf('Plugin manifest [%s] must contain a valid JSON object.', $path));
        }

        return new self($data);
    }

    public function id(): string
    {
        return (string) $this->pluginValue('id');
    }

    public function name(): string
    {
        return (string) $this->pluginValue('name');
    }

    public function description(): ?string
    {
        $description = data_get($this->data, 'plugin.description');

        return is_string($description) && trim($description) !== '' ? trim($description) : null;
    }

    public function version(): string
    {
        return (string) $this->pluginValue('version');
    }

    public function type(): string
    {
        return (string) $this->pluginValue('type');
    }

    public function coreConstraint(): string
    {
        return (string) data_get($this->data, 'compatibility.core', '*');
    }

    public function runtimeClass(): ?string
    {
        $runtimeClass = data_get($this->data, 'runtime.class');

        return is_string($runtimeClass) && $runtimeClass !== '' ? $runtimeClass : null;
    }

    /**
     * @return array<string, string>
     */
    public function runtimePsr4Autoload(): array
    {
        $autoload = data_get($this->data, 'runtime.autoload.psr-4', []);

        if (! is_array($autoload)) {
            return [];
        }

        $mappings = [];

        foreach ($autoload as $namespace => $path) {
            if (! is_string($namespace) || ! is_string($path) || $namespace === '' || $path === '') {
                continue;
            }

            $mappings[$namespace] = $path;
        }

        return $mappings;
    }

    /**
     * @return array<int, PermissionDefinition>
     */
    public function permissions(): array
    {
        $permissions = data_get($this->data, 'permissions', []);

        if (! is_array($permissions)) {
            return [];
        }

        $definitions = [];

        foreach ($permissions as $permission) {
            if (! is_array($permission)) {
                continue;
            }

            $key = $permission['key'] ?? null;
            $label = $permission['label'] ?? null;
            $description = $permission['description'] ?? null;

            if (! is_string($key) || ! is_string($label) || ! is_string($description)) {
                continue;
            }

            $definitions[] = new PermissionDefinition(
                key: $key,
                label: $label,
                description: $description,
                origin: $this->id(),
                featureArea: is_string($permission['feature_area'] ?? null) ? $permission['feature_area'] : null,
                operation: is_string($permission['operation'] ?? null) ? $permission['operation'] : null,
                contexts: is_array($permission['contexts'] ?? null)
                    ? array_values(array_filter($permission['contexts'], static fn (mixed $context): bool => is_string($context) && $context !== ''))
                    : [],
            );
        }

        return $definitions;
    }

    /**
     * @return array<int, PluginRouteDefinition>
     */
    public function routes(): array
    {
        $routes = data_get($this->data, 'routes', []);

        if (! is_array($routes)) {
            return [];
        }

        $definitions = [];

        foreach ($routes as $route) {
            if (! is_array($route)) {
                continue;
            }

            $id = $route['id'] ?? null;
            $type = $route['type'] ?? null;
            $file = $route['file'] ?? null;

            if (! is_string($id) || ! is_string($type) || ! is_string($file) || $id === '' || $type === '' || $file === '') {
                continue;
            }

            $definitions[] = new PluginRouteDefinition(
                id: $id,
                type: $type,
                file: $file,
                middleware: is_array($route['middleware'] ?? null)
                    ? array_values(array_filter($route['middleware'], static fn (mixed $value): bool => is_string($value) && $value !== ''))
                    : [],
                prefix: is_string($route['prefix'] ?? null) ? $route['prefix'] : null,
                permission: is_string($route['permission'] ?? null) ? $route['permission'] : null,
            );
        }

        return $definitions;
    }

    /**
     * @return array<int, PluginMenuDefinition>
     */
    public function menus(): array
    {
        $menus = data_get($this->data, 'menus', []);

        if (! is_array($menus)) {
            return [];
        }

        $definitions = [];

        foreach ($menus as $menu) {
            if (! is_array($menu)) {
                continue;
            }

            $id = $menu['id'] ?? null;
            $labelKey = $menu['label_key'] ?? null;

            if (! is_string($id) || ! is_string($labelKey) || $id === '' || $labelKey === '') {
                continue;
            }

            $definitions[] = new PluginMenuDefinition(
                id: $id,
                labelKey: $labelKey,
                routeName: is_string($menu['route'] ?? null) && $menu['route'] !== '' ? $menu['route'] : null,
                parentId: is_string($menu['parent_id'] ?? null) && $menu['parent_id'] !== '' ? $menu['parent_id'] : null,
                icon: is_string($menu['icon'] ?? null) && $menu['icon'] !== '' ? $menu['icon'] : null,
                order: is_int($menu['order'] ?? null) ? $menu['order'] : (is_numeric($menu['order'] ?? null) ? (int) $menu['order'] : 100),
                permission: is_string($menu['permission'] ?? null) && $menu['permission'] !== '' ? $menu['permission'] : null,
                area: is_string($menu['area'] ?? null) && in_array($menu['area'], ['app', 'admin'], true)
                    ? $menu['area']
                    : 'app',
            );
        }

        return $definitions;
    }

    /**
     * @return array<int, array{target: string, type: string}>
     */
    public function dependencies(): array
    {
        $dependencies = data_get($this->data, 'dependencies', []);

        if (! is_array($dependencies)) {
            return [];
        }

        $normalized = [];

        foreach ($dependencies as $dependency) {
            if (is_string($dependency) && trim($dependency) !== '') {
                $normalized[trim($dependency)] = [
                    'target' => trim($dependency),
                    'type' => 'required',
                ];

                continue;
            }

            if (! is_array($dependency)) {
                continue;
            }

            $target = $dependency['target'] ?? null;
            $type = $dependency['type'] ?? 'required';

            if (! is_string($target) || trim($target) === '' || ! is_string($type)) {
                continue;
            }

            $type = trim($type);

            if (! in_array($type, ['required', 'optional'], true)) {
                continue;
            }

            $normalized[trim($target)] = [
                'target' => trim($target),
                'type' => $type,
            ];
        }

        return array_values($normalized);
    }

    /**
     * @return array<int, string>
     */
    public function dependentPluginIds(): array
    {
        return array_values(array_map(
            static fn (array $dependency): string => $dependency['target'],
            $this->dependencies(),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function requiredDependencyPluginIds(): array
    {
        return array_values(array_map(
            static fn (array $dependency): string => $dependency['target'],
            array_filter($this->dependencies(), static fn (array $dependency): bool => $dependency['type'] === 'required'),
        ));
    }

    public function settingsMenuId(): ?string
    {
        $menuId = data_get($this->data, 'admin.settings_menu_id');

        return is_string($menuId) && trim($menuId) !== '' ? trim($menuId) : null;
    }

    private function assertRequiredPluginFields(): void
    {
        foreach (['id', 'name', 'version', 'type'] as $field) {
            $value = data_get($this->data, 'plugin.'.$field);

            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException(sprintf(
                    'Plugin manifest must define plugin.%s as a non-empty string.',
                    $field,
                ));
            }
        }
    }

    private function pluginValue(string $key): mixed
    {
        return data_get($this->data, 'plugin.'.$key);
    }
}
