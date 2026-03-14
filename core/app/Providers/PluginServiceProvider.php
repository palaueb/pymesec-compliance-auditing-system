<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PymeSec\Core\Menus\Contracts\MenuRegistryInterface;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;
use PymeSec\Core\Plugins\Contracts\PluginManagerInterface;
use PymeSec\Core\Plugins\PluginDiscovery;
use PymeSec\Core\Plugins\PluginLifecycleManager;
use PymeSec\Core\Plugins\PluginManager;
use PymeSec\Core\Plugins\PluginStateStore;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginDiscovery::class, function ($app): PluginDiscovery {
            return new PluginDiscovery(config('plugins.paths', []));
        });

        $this->app->singleton(PluginStateStore::class, function ($app): PluginStateStore {
            return new PluginStateStore((string) config('plugins.state_path'));
        });

        $this->app->singleton(PluginLifecycleManager::class, function ($app): PluginLifecycleManager {
            return new PluginLifecycleManager(
                discovery: $app->make(PluginDiscovery::class),
                state: $app->make(PluginStateStore::class),
                baseEnabled: config('plugins.enabled', []),
            );
        });

        $this->app->singleton(PluginManagerInterface::class, function ($app): PluginManagerInterface {
            $configuredEnabledPlugins = config('plugins.enabled', []);

            return new PluginManager(
                app: $app,
                discovery: $app->make(PluginDiscovery::class),
                permissions: $app->make(PermissionRegistryInterface::class),
                menus: $app->make(MenuRegistryInterface::class),
                enabledPluginIds: $app->make(PluginStateStore::class)->effectiveEnabled($configuredEnabledPlugins),
                coreVersion: (string) config('plugins.core_version', '0.1.0'),
            );
        });
    }

    public function boot(): void
    {
        $this->app->make(PluginManagerInterface::class)->boot();
    }
}
