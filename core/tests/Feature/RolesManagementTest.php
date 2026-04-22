<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PymeSec\Core\Permissions\Contracts\AuthorizationStoreInterface;
use Tests\TestCase;

class RolesManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_roles_screen_renders_inside_the_shell(): void
    {
        $this->get('/admin?menu=core.roles&principal_id=principal-admin')
            ->assertOk()
            ->assertSee('Roles & Grants')
            ->assertSee('Governance page. Define reusable role sets and grants here')
            ->assertSee('platform-admin')
            ->assertSee('Platform administration')
            ->assertSee('Operational workspaces')
            ->assertSee('Add role')
            ->assertSee('Assign grant')
            ->assertSee('Open');
    }

    public function test_roles_and_grants_can_be_created_and_updated_persistently(): void
    {
        $payload = [
            'principal_id' => 'principal-admin',
            'locale' => 'en',
            'menu' => 'core.roles',
        ];

        $this->post('/core/roles', [
            ...$payload,
            'key' => 'privacy-auditor',
            'label' => 'Privacy auditor',
            'permissions' => [
                'plugin.findings-remediation.findings.view',
            ],
        ])->assertFound();

        $this->post('/core/roles/grants', [
            ...$payload,
            'target_type' => 'membership',
            'target_id' => 'membership-org-b-ops',
            'grant_type' => 'role',
            'value' => 'privacy-auditor',
            'context_type' => 'organization',
            'organization_id' => 'org-b',
        ])->assertFound();

        $this->get('/core/authorization/check?principal_id=principal-org-a&permission=plugin.findings-remediation.findings.view&organization_id=org-b&membership_ids[]=membership-org-b-ops')
            ->assertOk()
            ->assertJsonPath('result.status', 'allow');

        $this->post('/core/roles', [
            ...$payload,
            'key' => 'privacy-auditor',
            'label' => 'Privacy and policy auditor',
            'permissions' => [
                'plugin.findings-remediation.findings.view',
                'plugin.policy-exceptions.policies.view',
            ],
        ])->assertFound();

        $this->get('/core/authorization/check?principal_id=principal-org-a&permission=plugin.policy-exceptions.policies.view&organization_id=org-b&membership_ids[]=membership-org-b-ops')
            ->assertOk()
            ->assertJsonPath('result.status', 'allow');

        $this->get('/admin?menu=core.roles&role_key=privacy-auditor&principal_id=principal-admin')
            ->assertOk()
            ->assertSee('privacy-auditor')
            ->assertSee('Privacy and policy auditor');
    }

    public function test_api_role_creation_preserves_system_roles(): void
    {
        $this->postJson('/api/v1/core/roles', [
            'principal_id' => 'principal-admin',
            'key' => 'custom-asset-viewer',
            'label' => 'Custom asset viewer',
            'permissions' => [
                'plugin.asset-catalog.assets.view',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.role.key', 'custom-asset-viewer')
            ->assertJsonPath('data.role.is_system', false);

        $roles = $this->app->make(AuthorizationStoreInterface::class)->roleDefinitions();

        $this->assertArrayHasKey('platform-admin', $roles);
        $this->assertArrayHasKey('custom-asset-viewer', $roles);
        $this->assertContains('core.roles.manage', $roles['platform-admin']->permissions);

        $this->get('/core/authorization/check?principal_id=principal-admin&permission=core.roles.manage')
            ->assertOk()
            ->assertJsonPath('result.status', 'allow');

        $this->postJson('/api/v1/core/roles/grants', [
            'principal_id' => 'principal-admin',
            'target_type' => 'membership',
            'target_id' => 'membership-org-a-hello',
            'grant_type' => 'role',
            'value' => 'custom-asset-viewer',
            'context_type' => 'organization',
            'organization_id' => 'org-a',
        ])->assertOk();

        $capabilities = $this->getJson('/api/v1/meta/capabilities?principal_id=principal-admin')
            ->assertOk()
            ->json('data.permissions');

        $this->assertIsArray($capabilities);
        $this->assertContains('core.roles.manage', $capabilities);
    }

    public function test_roles_and_grants_commands_report_persisted_records(): void
    {
        Artisan::call('roles:list');
        $this->assertStringContainsString('platform-admin', Artisan::output());

        Artisan::call('grants:list');
        $this->assertStringContainsString('membership-org-a-hello', Artisan::output());
    }
}
