<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PymeSec\Core\Plugins\PluginStateStore;
use Tests\TestCase;

class PluginSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_plugin_registry_endpoint_lists_discovered_plugins(): void
    {
        $this->get('/core/plugins')
            ->assertOk()
            ->assertJsonPath('core_version', '0.1.0')
            ->assertJsonFragment([
                'id' => 'hello-world',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 2,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'asset-catalog',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 2,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'actor-directory',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 2,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'controls-catalog',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 2,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'identity-local',
                'enabled' => false,
                'booted' => false,
                'reason' => 'plugin_not_enabled',
            ]);
    }

    public function test_the_example_plugin_route_is_loaded(): void
    {
        $this->get('/plugins/hello-world?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJson([
                'plugin' => 'hello-world',
                'message' => 'Hello from the example plugin.',
            ]);
    }

    public function test_the_permission_registry_endpoint_lists_core_and_plugin_permissions(): void
    {
        $this->get('/core/permissions')
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'core.plugins.view',
                'origin' => 'core',
            ])
            ->assertJsonFragment([
                'key' => 'plugin.hello-world.hello.view',
                'origin' => 'hello-world',
            ])
            ->assertJsonFragment([
                'key' => 'plugin.asset-catalog.assets.view',
                'origin' => 'asset-catalog',
            ])
            ->assertJsonMissing([
                'key' => 'plugin.identity-local.principals.view',
            ]);
    }

    public function test_the_plugins_list_command_reports_manifest_metadata(): void
    {
        $this->artisan('plugins:list')
            ->expectsTable(
                ['ID', 'Type', 'Enabled', 'Booted', 'Permissions', 'Routes', 'Menus', 'Reason'],
                [
                    ['actor-directory', 'domain-actor', 'yes', 'yes', '2', '1', '2', ''],
                    ['asset-catalog', 'domain', 'yes', 'yes', '2', '1', '2', ''],
                    ['controls-catalog', 'domain', 'yes', 'yes', '2', '1', '2', ''],
                    ['hello-world', 'ui', 'yes', 'yes', '1', '1', '2', ''],
                    ['identity-local', 'identity', 'no', 'no', '1', '0', '0', 'plugin_not_enabled'],
                ],
            )
            ->assertExitCode(0);
    }

    public function test_the_permissions_list_command_reports_registered_permissions(): void
    {
        $this->artisan('permissions:list')
            ->expectsTable(
                ['Key', 'Origin', 'Operation', 'Contexts'],
                [
                    ['core.artifacts.manage', 'core', 'manage', 'platform'],
                    ['core.artifacts.view', 'core', 'view', 'platform'],
                    ['core.audit-logs.export', 'core', 'export', 'platform'],
                    ['core.audit-logs.view', 'core', 'view', 'platform'],
                    ['core.events.view', 'core', 'view', 'platform'],
                    ['core.functional-actors.manage', 'core', 'manage', 'platform'],
                    ['core.functional-actors.view', 'core', 'view', 'platform'],
                    ['core.menus.view', 'core', 'view', 'platform'],
                    ['core.notifications.manage', 'core', 'manage', 'platform'],
                    ['core.notifications.view', 'core', 'view', 'platform'],
                    ['core.permissions.manage', 'core', 'manage', 'platform'],
                    ['core.permissions.view', 'core', 'view', 'platform'],
                    ['core.plugins.manage', 'core', 'manage', 'platform'],
                    ['core.plugins.view', 'core', 'view', 'platform'],
                    ['core.tenancy.manage', 'core', 'manage', 'platform'],
                    ['core.tenancy.view', 'core', 'view', 'platform'],
                    ['core.workflows.view', 'core', 'view', 'platform'],
                    ['plugin.actor-directory.actors.manage', 'actor-directory', 'manage', 'organization'],
                    ['plugin.actor-directory.actors.view', 'actor-directory', 'view', 'organization'],
                    ['plugin.asset-catalog.assets.manage', 'asset-catalog', 'manage', 'organization'],
                    ['plugin.asset-catalog.assets.view', 'asset-catalog', 'view', 'organization'],
                    ['plugin.controls-catalog.controls.manage', 'controls-catalog', 'manage', 'organization'],
                    ['plugin.controls-catalog.controls.view', 'controls-catalog', 'view', 'organization'],
                    ['plugin.hello-world.hello.view', 'hello-world', 'view', 'organization'],
                ],
            )
            ->assertExitCode(0);
    }

    public function test_the_plugins_enable_command_persists_a_local_override(): void
    {
        $this->artisan('plugins:enable identity-local')
            ->expectsOutputToContain('Plugin [identity-local] will be enabled on the next bootstrap.')
            ->assertExitCode(0);

        $effective = $this->app->make(PluginStateStore::class)->effectiveEnabled(config('plugins.enabled', []));

        $this->assertSame(['hello-world', 'asset-catalog', 'actor-directory', 'controls-catalog', 'identity-local'], $effective);
    }

    public function test_the_plugins_disable_command_persists_a_local_override(): void
    {
        $this->artisan('plugins:disable hello-world')
            ->expectsOutputToContain('Plugin [hello-world] will be disabled on the next bootstrap.')
            ->assertExitCode(0);

        $effective = $this->app->make(PluginStateStore::class)->effectiveEnabled(config('plugins.enabled', []));

        $this->assertSame(['asset-catalog', 'actor-directory', 'controls-catalog'], $effective);
    }

    public function test_the_plugins_disable_command_removes_a_previous_local_enable_override(): void
    {
        $this->artisan('plugins:enable identity-local')
            ->assertExitCode(0);

        $this->artisan('plugins:disable identity-local')
            ->expectsOutputToContain('Plugin [identity-local] will be disabled on the next bootstrap.')
            ->assertExitCode(0);

        $effective = $this->app->make(PluginStateStore::class)->effectiveEnabled(config('plugins.enabled', []));

        $this->assertSame(['hello-world', 'asset-catalog', 'actor-directory', 'controls-catalog'], $effective);
    }

    public function test_the_plugins_enable_command_rejects_unknown_plugins(): void
    {
        $this->artisan('plugins:enable missing-plugin')
            ->expectsOutputToContain('Unknown plugin [missing-plugin].')
            ->assertExitCode(1);
    }

    public function test_the_menu_registry_endpoint_returns_core_and_plugin_items(): void
    {
        $this->get('/core/menus?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonPath('menus.0.id', 'core.platform')
            ->assertJsonPath('menus.1.id', 'plugin.asset-catalog.root')
            ->assertJsonPath('menus.1.children.0.id', 'plugin.asset-catalog.lifecycle')
            ->assertJsonPath('menus.2.id', 'plugin.controls-catalog.root')
            ->assertJsonPath('menus.3.id', 'plugin.hello-world.root')
            ->assertJsonPath('menus.4.id', 'plugin.actor-directory.root')
            ->assertJsonPath('issues', []);
    }

    public function test_the_menu_registry_hides_plugin_items_without_required_permission_context(): void
    {
        $this->get('/core/menus?principal_id=principal-admin')
            ->assertOk()
            ->assertJsonPath('visible_menus.0.id', 'core.platform')
            ->assertJsonCount(1, 'visible_menus');

        $this->get('/core/menus?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'plugin.hello-world.root',
                'owner' => 'hello-world',
            ]);
    }

    public function test_the_menus_list_command_reports_finalized_entries(): void
    {
        $this->artisan('menus:list')
            ->expectsTable(
                ['ID', 'Owner', 'Parent', 'Route', 'Permission', 'Order'],
                [
                    ['core.platform', 'core', '', '', '', '10'],
                    ['core.plugins', 'core', 'core.platform', 'core.plugins.index', 'core.plugins.view', '10'],
                    ['core.permissions', 'core', 'core.platform', 'core.permissions.index', 'core.permissions.view', '20'],
                    ['core.tenancy', 'core', 'core.platform', 'core.tenancy.index', 'core.tenancy.view', '30'],
                    ['core.audit', 'core', 'core.platform', 'core.audit.index', 'core.audit-logs.view', '40'],
                    ['core.functional-actors', 'core', 'core.platform', 'core.functional-actors.index', 'core.functional-actors.view', '50'],
                    ['plugin.asset-catalog.root', 'asset-catalog', '', 'plugin.asset-catalog.index', 'plugin.asset-catalog.assets.view', '20'],
                    ['plugin.asset-catalog.lifecycle', 'asset-catalog', 'plugin.asset-catalog.root', 'plugin.asset-catalog.lifecycle', 'plugin.asset-catalog.assets.view', '10'],
                    ['plugin.controls-catalog.root', 'controls-catalog', '', 'plugin.controls-catalog.index', 'plugin.controls-catalog.controls.view', '25'],
                    ['plugin.controls-catalog.reviews', 'controls-catalog', 'plugin.controls-catalog.root', 'plugin.controls-catalog.reviews', 'plugin.controls-catalog.controls.view', '10'],
                    ['plugin.hello-world.root', 'hello-world', '', 'plugin.hello-world.index', 'plugin.hello-world.hello.view', '30'],
                    ['plugin.hello-world.examples', 'hello-world', 'plugin.hello-world.root', 'plugin.hello-world.index', 'plugin.hello-world.hello.view', '10'],
                    ['plugin.actor-directory.root', 'actor-directory', '', 'plugin.actor-directory.index', 'plugin.actor-directory.actors.view', '40'],
                    ['plugin.actor-directory.assignments', 'actor-directory', 'plugin.actor-directory.root', 'plugin.actor-directory.assignments', 'plugin.actor-directory.actors.view', '10'],
                ],
            )
            ->assertExitCode(0);
    }
}
