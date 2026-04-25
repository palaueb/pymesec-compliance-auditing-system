<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PymeSec\Plugins\IdentityLocal\IdentityLocalRepository;
use Tests\TestCase;

class ShellNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shell_returns_not_implemented_for_unknown_menu_requests(): void
    {
        $this->get('/app?menu=plugin.missing.screen&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Screen unavailable')
            ->assertSee('plugin.missing.screen');
    }

    public function test_the_shell_returns_not_implemented_for_unavailable_menu_requests(): void
    {
        $this->get('/app?menu=plugin.identity-ldap.directory&principal_id=principal-admin&organization_id=org-a')
            ->assertOk()
            ->assertSee('Screen unavailable')
            ->assertSee('plugin.identity-ldap.directory');
    }

    public function test_the_admin_shell_returns_not_implemented_for_workspace_menu_requests(): void
    {
        $this->get('/admin?menu=plugin.asset-catalog.root&principal_id=principal-admin')
            ->assertOk()
            ->assertSee('Screen unavailable')
            ->assertSee('plugin.asset-catalog.root');
    }

    public function test_delegated_governance_screens_render_in_the_workspace_shell(): void
    {
        $this->get('/app?menu=core.management-reporting&principal_id=principal-admin&organization_id=org-a')
            ->assertOk()
            ->assertSee('Management Reporting')
            ->assertSee('Cross-domain executive summary')
            ->assertSee('PymeSec v0.3.0')
            ->assertSee('Repository');

        $this->get('/app?menu=core.governance&principal_id=principal-admin&organization_id=org-a')
            ->assertOk()
            ->assertSee('Use this page to inspect delegated access across linked principals, functional actors, and governed objects.')
            ->assertSee('Access entrypoints')
            ->assertSee('Use these records to understand where platform identities enter the workspace.');

        $this->get('/app?menu=core.functional-actors&principal_id=principal-admin&organization_id=org-a')
            ->assertOk()
            ->assertSee('Functional Directory');

        $this->get('/app?menu=core.assignments&principal_id=principal-admin&organization_id=org-a')
            ->assertOk()
            ->assertSee('Functional Assignments');

        $this->get('/app?menu=core.object-access&principal_id=principal-admin&organization_id=org-a')
            ->assertOk()
            ->assertSee('Object Access Matrix');
    }

    public function test_the_admin_shell_rejects_delegated_governance_menus(): void
    {
        $this->get('/admin?menu=core.management-reporting&principal_id=principal-admin&organization_id=org-a')
            ->assertOk()
            ->assertSee('Screen unavailable')
            ->assertSee('core.management-reporting');

        $this->get('/admin?menu=core.governance&principal_id=principal-admin&organization_id=org-a')
            ->assertOk()
            ->assertSee('Screen unavailable')
            ->assertSee('core.governance');

        $this->get('/admin?menu=core.functional-actors&principal_id=principal-admin&organization_id=org-a')
            ->assertOk()
            ->assertSee('Screen unavailable')
            ->assertSee('core.functional-actors');

        $this->get('/admin?menu=core.assignments&principal_id=principal-admin&organization_id=org-a')
            ->assertOk()
            ->assertSee('Screen unavailable')
            ->assertSee('core.assignments');

        $this->get('/admin?menu=core.object-access&principal_id=principal-admin&organization_id=org-a')
            ->assertOk()
            ->assertSee('Screen unavailable')
            ->assertSee('core.object-access');
    }

    public function test_platform_admin_bootstraps_workspace_access_and_lands_in_the_app(): void
    {
        DB::table('identity_local_users')->insert([
            'id' => 'identity-user-platform-bootstrap',
            'principal_id' => 'principal-platform-bootstrap',
            'organization_id' => 'org-a',
            'username' => 'platform.bootstrap',
            'display_name' => 'Platform Bootstrap',
            'email' => 'platform.bootstrap@northwind.test',
            'password_hash' => null,
            'password_enabled' => false,
            'magic_link_enabled' => true,
            'job_title' => 'Platform administrator',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app->make(IdentityLocalRepository::class)->ensurePlatformAdminGrant('principal-platform-bootstrap');

        $this->get('/app?principal_id=principal-platform-bootstrap&organization_id=org-a')
            ->assertOk()
            ->assertSee('Workspace Dashboard')
            ->assertSee('Today in your workspace')
            ->assertSee('Assets')
            ->assertSee('Automation')
            ->assertSee('Suppliers')
            ->assertSee('Actors')
            ->assertDontSee('Plugin Runtime');

        $membershipId = DB::table('memberships')
            ->where('principal_id', 'principal-platform-bootstrap')
            ->where('organization_id', 'org-a')
            ->value('id');

        $this->assertIsString($membershipId);

        $this->assertDatabaseHas('authorization_grants', [
            'target_type' => 'membership',
            'target_id' => $membershipId,
            'grant_type' => 'role',
            'value' => 'asset-operator',
            'context_type' => 'organization',
            'organization_id' => 'org-a',
        ]);

        $this->assertDatabaseHas('authorization_grants', [
            'target_type' => 'membership',
            'target_id' => $membershipId,
            'grant_type' => 'role',
            'value' => 'third-party-risk-operator',
            'context_type' => 'organization',
            'organization_id' => 'org-a',
        ]);

        $this->assertDatabaseHas('authorization_grants', [
            'target_type' => 'membership',
            'target_id' => $membershipId,
            'grant_type' => 'role',
            'value' => 'automation-operator',
            'context_type' => 'organization',
            'organization_id' => 'org-a',
        ]);
    }

    public function test_platform_admin_without_requested_organization_still_gets_workspace_bootstrap(): void
    {
        DB::table('identity_local_users')->insert([
            'id' => 'identity-user-platform-bootstrap-2',
            'principal_id' => 'principal-platform-bootstrap-2',
            'organization_id' => 'org-a',
            'username' => 'platform.bootstrap.2',
            'display_name' => 'Platform Bootstrap Two',
            'email' => 'platform.bootstrap.2@northwind.test',
            'password_hash' => null,
            'password_enabled' => false,
            'magic_link_enabled' => true,
            'job_title' => 'Platform administrator',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app->make(IdentityLocalRepository::class)
            ->ensurePlatformAdminGrant('principal-platform-bootstrap-2');

        $this->get('/app?principal_id=principal-platform-bootstrap-2')
            ->assertOk()
            ->assertSee('Workspace Dashboard')
            ->assertSee('Assets');

        $membershipId = DB::table('memberships')
            ->where('principal_id', 'principal-platform-bootstrap-2')
            ->where('organization_id', 'org-a')
            ->value('id');

        $this->assertIsString($membershipId);
    }

    public function test_platform_admin_bootstrap_does_not_overwrite_manual_workspace_roles(): void
    {
        DB::table('identity_local_users')->insert([
            'id' => 'identity-user-platform-bootstrap-3',
            'principal_id' => 'principal-platform-bootstrap-3',
            'organization_id' => 'org-a',
            'username' => 'platform.bootstrap.3',
            'display_name' => 'Platform Bootstrap Three',
            'email' => 'platform.bootstrap.3@northwind.test',
            'password_hash' => null,
            'password_enabled' => false,
            'magic_link_enabled' => true,
            'job_title' => 'Platform administrator',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $repository = $this->app->make(IdentityLocalRepository::class);
        $repository->ensurePlatformAdminGrant('principal-platform-bootstrap-3');

        $membership = $repository->ensureBootstrapOrganizationAccess('principal-platform-bootstrap-3', 'org-a');
        $membershipId = $membership['id'] ?? null;

        $this->assertIsString($membershipId);

        $repository->updateMembership((string) $membershipId, [
            'principal_id' => 'principal-platform-bootstrap-3',
            'organization_id' => 'org-a',
            'role_keys' => [
                'identity-operator',
                'identity-viewer',
                'identity-ldap-operator',
                'identity-ldap-viewer',
            ],
            'scope_ids' => [],
            'is_active' => true,
        ], 'principal-platform-bootstrap-3');

        $this->get('/app?principal_id=principal-platform-bootstrap-3&organization_id=org-a')
            ->assertOk()
            ->assertSee('Workspace Dashboard');

        $this->assertDatabaseHas('authorization_grants', [
            'target_type' => 'membership',
            'target_id' => $membershipId,
            'grant_type' => 'role',
            'value' => 'identity-viewer',
            'context_type' => 'organization',
            'organization_id' => 'org-a',
        ]);

        $this->assertDatabaseHas('authorization_grants', [
            'target_type' => 'membership',
            'target_id' => $membershipId,
            'grant_type' => 'role',
            'value' => 'identity-ldap-viewer',
            'context_type' => 'organization',
            'organization_id' => 'org-a',
        ]);

        $this->assertDatabaseMissing('authorization_grants', [
            'target_type' => 'membership',
            'target_id' => $membershipId,
            'grant_type' => 'role',
            'value' => 'asset-operator',
            'context_type' => 'organization',
            'organization_id' => 'org-a',
        ]);
    }

    public function test_shell_detail_pages_render_a_contextual_back_link_and_preserve_detail_query_in_shell_utilities(): void
    {
        $response = $this->get('/app?menu=plugin.continuity-bcm.plans&plan_id=continuity-plan-support-fallback&context_label=Recovery%20Plans&context_back_url=%2Fapp%3Fmenu%3Dplugin.continuity-bcm.plans&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello');

        $response
            ->assertOk()
            ->assertSee('Support fallback rota')
            ->assertSee('This page is open in detail context and returns to its parent list.')
            ->assertSee('href="/app?menu=plugin.continuity-bcm.plans"', false)
            ->assertSee('name="plan_id" value="continuity-plan-support-fallback"', false)
            ->assertSee('name="context_back_url" value="/app?menu=plugin.continuity-bcm.plans"', false)
            ->assertSee('name="context_label" value="Recovery Plans"', false);
    }

    public function test_shell_rejects_external_context_back_links(): void
    {
        $this->get('/app?menu=plugin.continuity-bcm.plans&plan_id=continuity-plan-support-fallback&context_label=External%20List&context_back_url=https%3A%2F%2Fevil.example%2Fsteal&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Support fallback rota')
            ->assertDontSee('This page is open in detail context and returns to its parent list.')
            ->assertDontSee('href="https://evil.example/steal"', false)
            ->assertDontSee('name="context_back_url" value="https://evil.example/steal"', false)
            ->assertDontSee('External List');
    }
}
