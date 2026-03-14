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
    }
}
