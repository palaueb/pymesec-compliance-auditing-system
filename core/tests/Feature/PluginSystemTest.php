<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PymeSec\Core\Plugins\Contracts\PluginManagerInterface;
use PymeSec\Core\Plugins\PluginStateStore;
use Tests\TestCase;

class PluginSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_plugin_registry_endpoint_lists_discovered_plugins(): void
    {
        $this->get('/core/plugins')
            ->assertOk()
            ->assertJsonPath('core_version', '0.3.1')
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
                'id' => 'assessments-audits',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 1,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'actor-directory',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 0,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'controls-catalog',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 4,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'data-flows-privacy',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 2,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'continuity-bcm',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 2,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'automation-catalog',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 1,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'collaboration',
                'enabled' => true,
                'booted' => true,
                'route_count' => 0,
                'menu_count' => 0,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'evidence-management',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 1,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'identity-local',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 2,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'identity-ldap',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 1,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'risk-management',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 2,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'third-party-risk',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 1,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'findings-remediation',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 2,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'policy-exceptions',
                'enabled' => true,
                'booted' => true,
                'route_count' => 1,
                'menu_count' => 2,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'framework-platform',
                'enabled' => true,
                'booted' => true,
                'route_count' => 0,
                'menu_count' => 0,
                'runtime_contract_satisfied' => true,
            ])
            ->assertJsonFragment([
                'id' => 'framework-gdpr',
                'enabled' => true,
                'booted' => true,
                'route_count' => 0,
                'menu_count' => 0,
                'runtime_contract_satisfied' => true,
            ]);
    }

    public function test_the_example_plugin_route_is_loaded(): void
    {
        $this->app->make(PluginManagerInterface::class)->boot();

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
            ->assertJsonFragment([
                'key' => 'plugin.data-flows-privacy.records.view',
                'origin' => 'data-flows-privacy',
            ])
            ->assertJsonFragment([
                'key' => 'plugin.continuity-bcm.plans.view',
                'origin' => 'continuity-bcm',
            ])
            ->assertJsonFragment([
                'key' => 'plugin.automation-catalog.packs.view',
                'origin' => 'automation-catalog',
            ])
            ->assertJsonFragment([
                'key' => 'plugin.identity-local.users.view',
                'origin' => 'identity-local',
            ])
            ->assertJsonFragment([
                'key' => 'plugin.identity-ldap.directory.view',
                'origin' => 'identity-ldap',
            ])
            ->assertJsonFragment([
                'key' => 'plugin.assessments-audits.assessments.view',
                'origin' => 'assessments-audits',
            ])
            ->assertJsonFragment([
                'key' => 'plugin.evidence-management.evidence.view',
                'origin' => 'evidence-management',
            ])
            ->assertJsonFragment([
                'key' => 'plugin.third-party-risk.vendors.view',
                'origin' => 'third-party-risk',
            ]);
    }

    public function test_the_plugins_list_command_reports_manifest_metadata(): void
    {
        $this->artisan('plugins:list')
            ->expectsOutputToContain('| ID')
            ->expectsOutputToContain('| controls-catalog     | domain         | yes     | yes    | 2           | 2      | 4')
            ->expectsOutputToContain('| framework-gdpr       | framework-pack | yes     | yes    | 0           | 0      | 0')
            ->expectsOutputToContain('| framework-platform   | domain         | yes     | yes    | 0           | 0      | 0')
            ->expectsOutputToContain('| risk-management      | domain         | yes     | yes    | 2           | 2      | 2')
            ->assertExitCode(0);
    }

    public function test_the_permissions_list_command_reports_registered_permissions(): void
    {
        $this->artisan('permissions:list')
            ->expectsTable(
                ['Key', 'Origin', 'Operation', 'Contexts'],
                [
                    ['core.api-tokens.manage', 'core', 'manage', 'platform'],
                    ['core.api-tokens.view', 'core', 'view', 'platform'],
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
                    ['core.roles.manage', 'core', 'manage', 'platform'],
                    ['core.roles.view', 'core', 'view', 'platform'],
                    ['core.tenancy.manage', 'core', 'manage', 'platform'],
                    ['core.tenancy.view', 'core', 'view', 'platform'],
                    ['core.workflows.view', 'core', 'view', 'platform'],
                    ['plugin.actor-directory.actors.manage', 'actor-directory', 'manage', 'organization'],
                    ['plugin.actor-directory.actors.view', 'actor-directory', 'view', 'organization'],
                    ['plugin.assessments-audits.assessments.manage', 'assessments-audits', 'manage', 'organization'],
                    ['plugin.assessments-audits.assessments.view', 'assessments-audits', 'view', 'organization'],
                    ['plugin.asset-catalog.assets.manage', 'asset-catalog', 'manage', 'organization'],
                    ['plugin.asset-catalog.assets.view', 'asset-catalog', 'view', 'organization'],
                    ['plugin.automation-catalog.packs.manage', 'automation-catalog', 'manage', 'organization'],
                    ['plugin.automation-catalog.packs.view', 'automation-catalog', 'view', 'organization'],
                    ['plugin.continuity-bcm.plans.manage', 'continuity-bcm', 'manage', 'organization'],
                    ['plugin.continuity-bcm.plans.view', 'continuity-bcm', 'view', 'organization'],
                    ['plugin.controls-catalog.controls.manage', 'controls-catalog', 'manage', 'organization'],
                    ['plugin.controls-catalog.controls.view', 'controls-catalog', 'view', 'organization'],
                    ['plugin.data-flows-privacy.records.manage', 'data-flows-privacy', 'manage', 'organization'],
                    ['plugin.data-flows-privacy.records.view', 'data-flows-privacy', 'view', 'organization'],
                    ['plugin.evidence-management.evidence.manage', 'evidence-management', 'manage', 'organization'],
                    ['plugin.evidence-management.evidence.view', 'evidence-management', 'view', 'organization'],
                    ['plugin.findings-remediation.findings.manage', 'findings-remediation', 'manage', 'organization'],
                    ['plugin.findings-remediation.findings.view', 'findings-remediation', 'view', 'organization'],
                    ['plugin.hello-world.hello.view', 'hello-world', 'view', 'organization'],
                    ['plugin.identity-ldap.directory.manage', 'identity-ldap', 'manage', 'organization'],
                    ['plugin.identity-ldap.directory.view', 'identity-ldap', 'view', 'organization'],
                    ['plugin.identity-local.memberships.manage', 'identity-local', 'manage', 'organization'],
                    ['plugin.identity-local.memberships.view', 'identity-local', 'view', 'organization'],
                    ['plugin.identity-local.users.manage', 'identity-local', 'manage', 'organization'],
                    ['plugin.identity-local.users.view', 'identity-local', 'view', 'organization'],
                    ['plugin.policy-exceptions.policies.manage', 'policy-exceptions', 'manage', 'organization'],
                    ['plugin.policy-exceptions.policies.view', 'policy-exceptions', 'view', 'organization'],
                    ['plugin.risk-management.risks.manage', 'risk-management', 'manage', 'organization'],
                    ['plugin.risk-management.risks.view', 'risk-management', 'view', 'organization'],
                    ['plugin.third-party-risk.vendors.manage', 'third-party-risk', 'manage', 'organization'],
                    ['plugin.third-party-risk.vendors.view', 'third-party-risk', 'view', 'organization'],
                ],
            )
            ->assertExitCode(0);
    }

    public function test_the_plugins_enable_command_persists_a_local_override(): void
    {
        $this->artisan('plugins:enable identity-local')
            ->expectsOutputToContain('Plugin [identity-local] is already enabled.')
            ->assertExitCode(0);

        $effective = $this->app->make(PluginStateStore::class)->effectiveEnabled(config('plugins.enabled', []));

        $this->assertSame(config('plugins.enabled', []), $effective);
    }

    public function test_the_plugins_disable_command_persists_a_local_override(): void
    {
        $this->artisan('plugins:disable hello-world')
            ->expectsOutputToContain('Plugin [hello-world] will be disabled on the next bootstrap.')
            ->assertExitCode(0);

        $effective = $this->app->make(PluginStateStore::class)->effectiveEnabled(config('plugins.enabled', []));

        $expected = array_values(array_filter(
            config('plugins.enabled', []),
            static fn (string $pluginId): bool => $pluginId !== 'hello-world',
        ));

        $this->assertSame($expected, $effective);
    }

    public function test_the_plugins_disable_command_rejects_disabling_a_required_dependency(): void
    {
        $this->artisan('plugins:disable identity-local')
            ->expectsOutputToContain('Plugin [identity-local] is still required by enabled plugins: identity-ldap.')
            ->assertExitCode(1);

        $effective = $this->app->make(PluginStateStore::class)->effectiveEnabled(config('plugins.enabled', []));

        $this->assertSame(config('plugins.enabled', []), $effective);
    }

    public function test_the_plugins_enable_command_rejects_when_required_dependencies_are_disabled(): void
    {
        $this->artisan('plugins:disable identity-ldap')
            ->assertExitCode(0);

        $this->artisan('plugins:disable identity-local')
            ->assertExitCode(0);

        $this->artisan('plugins:enable identity-ldap')
            ->expectsOutputToContain('Plugin [identity-ldap] requires enabled dependencies: identity-local.')
            ->assertExitCode(1);

        $effective = $this->app->make(PluginStateStore::class)->effectiveEnabled(config('plugins.enabled', []));

        $expected = array_values(array_filter(
            config('plugins.enabled', []),
            static fn (string $pluginId): bool => ! in_array($pluginId, ['identity-local', 'identity-ldap'], true),
        ));

        $this->assertSame($expected, $effective);
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
            ->assertJsonPath('menus.0.id', 'core.dashboard')
            ->assertJsonPath('menus.1.id', 'core.management-reporting')
            ->assertJsonPath('menus.2.id', 'core.support')
            ->assertJsonPath('menus.3.id', 'core.governance')
            ->assertJsonPath('menus.3.children.0.id', 'core.functional-actors')
            ->assertJsonPath('menus.3.children.1.id', 'core.assignments')
            ->assertJsonPath('menus.3.children.2.id', 'core.object-access')
            ->assertJsonPath('menus.3.children.3.id', 'plugin.identity-local.memberships')
            ->assertJsonPath('menus.4.id', 'core.platform')
            ->assertJsonPath('menus.5.id', 'plugin.asset-catalog.root')
            ->assertJsonPath('menus.5.children.0.id', 'plugin.asset-catalog.lifecycle')
            ->assertJsonPath('menus.6.id', 'plugin.controls-catalog.root')
            ->assertJsonPath('menus.6.children.0.id', 'plugin.controls-catalog.catalog')
            ->assertJsonPath('menus.6.children.1.id', 'plugin.controls-catalog.framework-adoption')
            ->assertJsonPath('menus.6.children.2.id', 'plugin.assessments-audits.root')
            ->assertJsonPath('menus.6.children.3.id', 'plugin.controls-catalog.reviews')
            ->assertJsonPath('menus.7.id', 'plugin.hello-world.root')
            ->assertJsonPath('menus.8.id', 'plugin.risk-management.root')
            ->assertJsonPath('menus.9.id', 'plugin.third-party-risk.root')
            ->assertJsonPath('menus.10.id', 'plugin.findings-remediation.root')
            ->assertJsonPath('menus.11.id', 'plugin.policy-exceptions.root')
            ->assertJsonPath('menus.12.id', 'plugin.evidence-management.root')
            ->assertJsonPath('menus.13.id', 'plugin.data-flows-privacy.root')
            ->assertJsonPath('menus.14.id', 'plugin.identity-local.users')
            ->assertJsonPath('menus.15.id', 'plugin.continuity-bcm.root')
            ->assertJsonPath('menus.16.id', 'plugin.automation-catalog.root')
            ->assertJsonPath('issues', []);
    }

    public function test_the_menu_registry_hides_plugin_items_without_required_permission_context(): void
    {
        $this->get('/core/menus?principal_id=principal-admin')
            ->assertOk()
            ->assertJsonPath('visible_menus.0.id', 'core.dashboard')
            ->assertJsonPath('visible_menus.1.id', 'core.management-reporting')
            ->assertJsonPath('visible_menus.2.id', 'core.support')
            ->assertJsonPath('visible_menus.3.id', 'core.governance')
            ->assertJsonPath('visible_menus.4.id', 'core.platform')
            ->assertJsonCount(5, 'visible_menus');

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
                ['ID', 'Owner', 'Parent', 'Route', 'Permission', 'Area', 'Order'],
                [
                    ['core.dashboard', 'core', '', 'core.shell.index', '', 'app', '5'],
                    ['core.management-reporting', 'core', '', 'core.shell.index', '', 'app', '6'],
                    ['core.support', 'core', '', 'core.shell.index', '', 'app', '8'],
                    ['core.governance', 'core', '', 'core.shell.index', '', 'app', '9'],
                    ['core.functional-actors', 'core', 'core.governance', 'core.shell.index', 'core.functional-actors.view', 'app', '10'],
                    ['core.assignments', 'core', 'core.governance', 'core.shell.index', 'core.functional-actors.view', 'app', '15'],
                    ['core.object-access', 'core', 'core.governance', 'core.shell.index', 'core.functional-actors.view', 'app', '20'],
                    ['plugin.identity-local.memberships', 'identity-local', 'core.governance', 'plugin.identity-local.memberships.index', 'plugin.identity-local.memberships.view', 'app', '30'],
                    ['core.platform', 'core', '', '', '', 'admin', '10'],
                    ['core.plugins', 'core', 'core.platform', 'core.plugins.index', 'core.plugins.view', 'admin', '10'],
                    ['core.permissions', 'core', 'core.platform', 'core.permissions.index', 'core.permissions.view', 'admin', '20'],
                    ['core.roles', 'core', 'core.platform', 'core.roles.index', 'core.roles.view', 'admin', '25'],
                    ['core.tenancy', 'core', 'core.platform', 'core.tenancy.index', 'core.tenancy.view', 'admin', '30'],
                    ['core.reference-data', 'core', 'core.platform', 'core.reference-data.index', 'core.reference-data.view', 'admin', '35'],
                    ['core.audit', 'core', 'core.platform', 'core.audit.index', 'core.audit-logs.view', 'admin', '40'],
                    ['core.notifications', 'core', 'core.platform', 'core.notifications.index', 'core.notifications.view', 'admin', '45'],
                    ['core.api-tokens', 'core', 'core.platform', 'core.api-tokens.index', 'core.api-tokens.view', 'admin', '50'],
                    ['plugin.asset-catalog.root', 'asset-catalog', '', 'plugin.asset-catalog.index', 'plugin.asset-catalog.assets.view', 'app', '20'],
                    ['plugin.asset-catalog.lifecycle', 'asset-catalog', 'plugin.asset-catalog.root', 'plugin.asset-catalog.lifecycle', 'plugin.asset-catalog.assets.view', 'app', '10'],
                    ['plugin.controls-catalog.root', 'controls-catalog', '', '', 'plugin.controls-catalog.controls.view', 'app', '25'],
                    ['plugin.controls-catalog.catalog', 'controls-catalog', 'plugin.controls-catalog.root', 'plugin.controls-catalog.index', 'plugin.controls-catalog.controls.view', 'app', '5'],
                    ['plugin.controls-catalog.framework-adoption', 'controls-catalog', 'plugin.controls-catalog.root', 'plugin.controls-catalog.framework-adoption', 'plugin.controls-catalog.controls.view', 'app', '10'],
                    ['plugin.assessments-audits.root', 'assessments-audits', 'plugin.controls-catalog.root', 'plugin.assessments-audits.index', 'plugin.assessments-audits.assessments.view', 'app', '15'],
                    ['plugin.controls-catalog.reviews', 'controls-catalog', 'plugin.controls-catalog.root', 'plugin.controls-catalog.reviews', 'plugin.controls-catalog.controls.view', 'app', '20'],
                    ['plugin.hello-world.root', 'hello-world', '', 'plugin.hello-world.index', 'plugin.hello-world.hello.view', 'app', '30'],
                    ['plugin.hello-world.examples', 'hello-world', 'plugin.hello-world.root', 'plugin.hello-world.index', 'plugin.hello-world.hello.view', 'app', '10'],
                    ['plugin.risk-management.root', 'risk-management', '', 'plugin.risk-management.index', 'plugin.risk-management.risks.view', 'app', '35'],
                    ['plugin.risk-management.board', 'risk-management', 'plugin.risk-management.root', 'plugin.risk-management.board', 'plugin.risk-management.risks.view', 'app', '10'],
                    ['plugin.third-party-risk.root', 'third-party-risk', '', 'plugin.third-party-risk.index', 'plugin.third-party-risk.vendors.view', 'app', '37'],
                    ['plugin.findings-remediation.root', 'findings-remediation', '', 'plugin.findings-remediation.index', 'plugin.findings-remediation.findings.view', 'app', '38'],
                    ['plugin.findings-remediation.board', 'findings-remediation', 'plugin.findings-remediation.root', 'plugin.findings-remediation.board', 'plugin.findings-remediation.findings.view', 'app', '10'],
                    ['plugin.policy-exceptions.root', 'policy-exceptions', '', 'plugin.policy-exceptions.index', 'plugin.policy-exceptions.policies.view', 'app', '42'],
                    ['plugin.policy-exceptions.exceptions', 'policy-exceptions', 'plugin.policy-exceptions.root', 'plugin.policy-exceptions.exceptions', 'plugin.policy-exceptions.policies.view', 'app', '10'],
                    ['plugin.evidence-management.root', 'evidence-management', '', 'plugin.evidence-management.index', 'plugin.evidence-management.evidence.view', 'app', '44'],
                    ['plugin.data-flows-privacy.root', 'data-flows-privacy', '', 'plugin.data-flows-privacy.index', 'plugin.data-flows-privacy.records.view', 'app', '45'],
                    ['plugin.data-flows-privacy.activities', 'data-flows-privacy', 'plugin.data-flows-privacy.root', 'plugin.data-flows-privacy.activities', 'plugin.data-flows-privacy.records.view', 'app', '10'],
                    ['plugin.identity-local.users', 'identity-local', '', 'plugin.identity-local.users.index', 'plugin.identity-local.users.view', 'admin', '47'],
                    ['plugin.identity-ldap.directory', 'identity-ldap', 'plugin.identity-local.users', 'plugin.identity-ldap.directory.index', 'plugin.identity-ldap.directory.view', 'admin', '20'],
                    ['plugin.continuity-bcm.root', 'continuity-bcm', '', 'plugin.continuity-bcm.index', 'plugin.continuity-bcm.plans.view', 'app', '50'],
                    ['plugin.continuity-bcm.plans', 'continuity-bcm', 'plugin.continuity-bcm.root', 'plugin.continuity-bcm.plans', 'plugin.continuity-bcm.plans.view', 'app', '10'],
                    ['plugin.automation-catalog.root', 'automation-catalog', '', 'plugin.automation-catalog.index', 'plugin.automation-catalog.packs.view', 'app', '52'],
                ],
            )
            ->assertExitCode(0);
    }
}
