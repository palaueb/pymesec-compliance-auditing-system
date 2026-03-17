<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PymeSec\Core\Plugins\PluginDescriptor;
use PymeSec\Core\Plugins\PluginDiscovery;

/**
 * Seeds the minimum data required for a working PymeSec installation.
 *
 * This seeder runs on every fresh install (APP_INSTALL_PROFILE=system, the default).
 * It must only contain data that is:
 *   - Required for the platform to function (authorization roles)
 *   - Global content that ships with the product (framework packs)
 *
 * It must NOT contain:
 *   - Demo organizations, users, or tenant data (→ DemoCompanySeeder)
 *   - Test fixtures (→ TestDatabaseSeeder)
 *
 * See ADR-018 (installation profiles) and ADR-020 (framework packs) for rationale.
 */
class SystemBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAuthorizationRoles();
        $this->seedFrameworkPacks();
    }

    private function seedAuthorizationRoles(): void
    {
        $roles = [];
        $rolePermissions = [];

        foreach (config('authorization.roles', []) as $key => $role) {
            if (! is_string($key) || ! is_array($role)) {
                continue;
            }

            $roles[] = [
                'key' => $key,
                'label' => (string) ($role['label'] ?? $key),
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            foreach (array_values(array_filter(
                $role['permissions'] ?? [],
                static fn (mixed $permission): bool => is_string($permission) && $permission !== '',
            )) as $permission) {
                $rolePermissions[] = [
                    'role_key' => $key,
                    'permission_key' => $permission,
                ];
            }
        }

        DB::table('authorization_roles')->insertOrIgnore($roles);
        DB::table('authorization_role_permissions')->insertOrIgnore($rolePermissions);
    }

    /**
     * Discovers all framework-pack plugins and runs their declared lifecycle.seeder.
     *
     * Framework packs are auto-discovered from the plugins/ directory — no hard-coded
     * class names in composer.json or in this file. To add a new framework pack, create
     * a plugin under plugins/framework-<id>/ with type "framework-pack" and declare
     * lifecycle.seeder in plugin.json.
     *
     * Plugin classes are loaded via spl_autoload_register using the PSR-4 mappings from
     * plugin.json, exactly as PluginManager does for runtime plugins.
     */
    private function seedFrameworkPacks(): void
    {
        $discovery = new PluginDiscovery([
            realpath(__DIR__.'/../../../plugins') ?: '',
        ]);

        foreach ($discovery->discover() as $descriptor) {
            if ($descriptor->manifest()->type() !== 'framework-pack') {
                continue;
            }

            $seederClass = $descriptor->manifest()->lifecycleSeeder();

            if ($seederClass === null) {
                continue;
            }

            $this->registerPluginAutoload($descriptor);

            if (! class_exists($seederClass)) {
                continue;
            }

            $this->call([$seederClass]);
        }
    }

    /**
     * Registers spl_autoload_register for a plugin's PSR-4 namespaces,
     * using the same mechanism as PluginManager::registerAutoloadMappings().
     */
    private function registerPluginAutoload(PluginDescriptor $descriptor): void
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
}
