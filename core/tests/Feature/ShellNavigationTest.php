<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_the_shell_defaults_to_a_workspace_menu_before_platform_admin_when_both_are_visible(): void
    {
        DB::table('memberships')->insert([
            'id' => 'membership-org-a-admin',
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'roles' => json_encode(['asset-viewer'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('authorization_grants')->insert([
            'id' => 'grant-membership-org-a-admin-asset-viewer',
            'target_type' => 'membership',
            'target_id' => 'membership-org-a-admin',
            'grant_type' => 'role',
            'value' => 'asset-viewer',
            'context_type' => 'organization',
            'organization_id' => 'org-a',
            'scope_id' => null,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/app?principal_id=principal-admin&organization_id=org-a&membership_ids[]=membership-org-a-admin')
            ->assertOk()
            ->assertSee('Workspace Dashboard')
            ->assertSee('Today in your workspace')
            ->assertSee('Assets')
            ->assertDontSee('Plugin Runtime');
    }
}
