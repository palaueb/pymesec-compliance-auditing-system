<?php

namespace PymeSec\Core\Plugins;

use Illuminate\Contracts\Foundation\Application;
use PymeSec\Core\Contracts\FunctionalActorPluginInterface;
use PymeSec\Core\Contracts\IdentityPluginInterface;
use PymeSec\Core\Menus\Contracts\MenuRegistryInterface;
use PymeSec\Core\Menus\MenuDefinition;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;
use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\Contracts\PluginManagerInterface;
use RuntimeException;

class PluginManager implements PluginManagerInterface
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $status = [];

    /**
     * @var array<string, PluginInterface>
     */
    private array $activePlugins = [];

    private bool $booted = false;

    /**
     * @param  array<int, string>  $enabledPluginIds
     */
    public function __construct(
        private readonly Application $app,
        private readonly PluginDiscovery $discovery,
        private readonly PermissionRegistryInterface $permissions,
        private readonly MenuRegistryInterface $menus,
        private readonly array $enabledPluginIds,
        private readonly string $coreVersion,
    ) {}

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $contexts = [];

        foreach ($this->descriptorsInLoadOrder() as $descriptor) {
            $manifest = $descriptor->manifest();
            $enabled = in_array($descriptor->id(), $this->enabledPluginIds, true);
            $compatible = VersionConstraint::matches($this->coreVersion, $manifest->coreConstraint());
            $hasRuntime = $manifest->runtimeClass() !== null;
            $requiredDependencies = $manifest->requiredDependencyPluginIds();
            $missingRequiredDependencies = array_values(array_filter(
                $requiredDependencies,
                fn (string $dependencyId): bool => ! in_array($dependencyId, $this->enabledPluginIds, true),
            ));

            $record = [
                'id' => $manifest->id(),
                'name' => $manifest->name(),
                'description' => $manifest->description(),
                'version' => $manifest->version(),
                'type' => $manifest->type(),
                'path' => $descriptor->path(),
                'enabled' => $enabled,
                'compatible_with_core' => $compatible,
                'core_constraint' => $manifest->coreConstraint(),
                'has_runtime' => $hasRuntime,
                'permission_count' => count($manifest->permissions()),
                'route_count' => count($manifest->routes()),
                'menu_count' => count($manifest->menus()),
                'dependencies' => $manifest->dependentPluginIds(),
                'required_dependencies' => $requiredDependencies,
                'missing_dependencies' => $missingRequiredDependencies,
                'support_path' => $manifest->supportPath(),
                'support_locales' => $manifest->supportLocales(),
                'expected_runtime_contract' => $this->expectedRuntimeContract($manifest->type()),
                'runtime_contract_satisfied' => null,
                'booted' => false,
                'reason' => null,
            ];

            if (! $enabled) {
                $record['reason'] = 'plugin_not_enabled';
                $this->status[] = $record;

                continue;
            }

            if (! $compatible) {
                $record['reason'] = 'core_version_not_compatible';
                $this->status[] = $record;

                continue;
            }

            if ($missingRequiredDependencies !== []) {
                $record['reason'] = 'required_dependency_not_enabled';
                $this->status[] = $record;

                continue;
            }

            if (! $hasRuntime) {
                $record['reason'] = 'runtime_not_declared';
                $this->status[] = $record;

                continue;
            }

            $this->registerAutoloadMappings($descriptor);

            $runtimeClass = $manifest->runtimeClass();

            if (! is_string($runtimeClass) || ! class_exists($runtimeClass)) {
                $record['reason'] = 'runtime_class_not_found';
                $this->status[] = $record;

                continue;
            }

            $plugin = $this->app->make($runtimeClass);

            if (! $plugin instanceof PluginInterface) {
                throw new RuntimeException(sprintf(
                    'Plugin runtime class [%s] must implement [%s].',
                    $runtimeClass,
                    PluginInterface::class,
                ));
            }

            $expectedContract = $this->expectedRuntimeContract($manifest->type());

            if (is_string($expectedContract) && ! $plugin instanceof $expectedContract) {
                $record['reason'] = 'runtime_contract_mismatch';
                $record['runtime_contract_satisfied'] = false;
                $this->status[] = $record;

                continue;
            }

            $record['runtime_contract_satisfied'] = true;

            foreach ($manifest->permissions() as $definition) {
                $this->assertPluginPermissionPrefix($descriptor, $definition->key);
                $this->permissions->register($definition);
            }

            $context = new PluginContext($this->app, $descriptor);
            $plugin->register($context);

            $this->activePlugins[$descriptor->id()] = $plugin;
            $contexts[$descriptor->id()] = $context;
            $this->status[] = $record;
        }

        foreach ($this->status as &$record) {
            $pluginId = $record['id'];

            if (! isset($this->activePlugins[$pluginId], $contexts[$pluginId])) {
                continue;
            }

            $missingActiveDependencies = array_values(array_filter(
                $contexts[$pluginId]->manifest()->requiredDependencyPluginIds(),
                fn (string $dependencyId): bool => ! isset($this->activePlugins[$dependencyId]),
            ));

            if ($missingActiveDependencies !== []) {
                unset($this->activePlugins[$pluginId], $contexts[$pluginId]);
                $record['booted'] = false;
                $record['reason'] = 'required_dependency_not_booted';
                $record['missing_dependencies'] = $missingActiveDependencies;

                continue;
            }

            foreach ($contexts[$pluginId]->manifest()->routes() as $route) {
                $contexts[$pluginId]->loadManifestRoute($route);
            }

            foreach ($contexts[$pluginId]->manifest()->menus() as $menu) {
                $this->menus->registerPlugin(
                    new MenuDefinition(
                        id: $menu->id,
                        owner: $pluginId,
                        labelKey: $menu->labelKey,
                        routeName: $menu->routeName,
                        parentId: $menu->parentId,
                        icon: $menu->icon,
                        order: $menu->order,
                        permission: $menu->permission,
                        area: $menu->area,
                    ),
                    $contexts[$pluginId]->manifest()->dependentPluginIds(),
                );
            }

            $this->activePlugins[$pluginId]->boot($contexts[$pluginId]);
            $record['booted'] = true;
            $record['reason'] = null;
        }
        unset($record);

        $this->booted = true;
    }

    public function status(): array
    {
        return $this->status;
    }

    public function active(): array
    {
        return $this->activePlugins;
    }

    public function has(string $pluginId): bool
    {
        return isset($this->activePlugins[$pluginId]);
    }

    public function plugin(string $pluginId): ?PluginInterface
    {
        return $this->activePlugins[$pluginId] ?? null;
    }

    /**
     * @return array<int, PluginDescriptor>
     */
    private function descriptorsInLoadOrder(): array
    {
        $descriptors = [];

        foreach ($this->discovery->discover() as $descriptor) {
            $descriptors[$descriptor->id()] = $descriptor;
        }

        $ordered = [];
        $visiting = [];

        $visit = function (string $pluginId) use (&$visit, &$ordered, &$visiting, $descriptors): void {
            if (isset($ordered[$pluginId]) || isset($visiting[$pluginId]) || ! isset($descriptors[$pluginId])) {
                return;
            }

            $visiting[$pluginId] = true;

            foreach ($descriptors[$pluginId]->manifest()->requiredDependencyPluginIds() as $dependencyId) {
                $visit($dependencyId);
            }

            unset($visiting[$pluginId]);
            $ordered[$pluginId] = $descriptors[$pluginId];
        };

        foreach (array_keys($descriptors) as $pluginId) {
            $visit($pluginId);
        }

        return array_values($ordered);
    }

    private function registerAutoloadMappings(PluginDescriptor $descriptor): void
    {
        foreach ($descriptor->manifest()->runtimePsr4Autoload() as $namespace => $relativePath) {
            spl_autoload_register(static function (string $class) use ($descriptor, $namespace, $relativePath): void {
                if (! str_starts_with($class, $namespace)) {
                    return;
                }

                $relativeClass = substr($class, strlen($namespace));
                $file = $descriptor->path().'/'.trim($relativePath, '/').'/'.str_replace('\\', '/', $relativeClass).'.php';

                if (is_file($file)) {
                    require_once $file;
                }
            });
        }
    }

    private function expectedRuntimeContract(string $pluginType): ?string
    {
        return match ($pluginType) {
            'identity' => IdentityPluginInterface::class,
            'domain-actor' => FunctionalActorPluginInterface::class,
            default => null,
        };
    }

    private function assertPluginPermissionPrefix(PluginDescriptor $descriptor, string $key): void
    {
        $expectedPrefix = 'plugin.'.$descriptor->id().'.';

        if (! str_starts_with($key, $expectedPrefix)) {
            throw new RuntimeException(sprintf(
                'Plugin [%s] permission [%s] must start with [%s].',
                $descriptor->id(),
                $key,
                $expectedPrefix,
            ));
        }
    }
}
