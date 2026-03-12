<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_is_allowed_to_view_plugins(): void
    {
        $this->get('/core/authorization/check?principal_id=principal-admin&permission=core.plugins.view')
            ->assertOk()
            ->assertJsonPath('result.status', 'allow')
            ->assertJsonPath('result.allowed', true)
            ->assertJsonPath('result.reason', 'grant_matched');
    }

    public function test_organization_grant_allows_the_hello_world_permission(): void
    {
        $this->get('/core/authorization/check?principal_id=principal-org-a&permission=plugin.hello-world.hello.view&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonPath('result.status', 'allow')
            ->assertJsonPath('result.allowed', true);
    }

    public function test_missing_organization_context_is_denied_for_organization_scoped_permission(): void
    {
        $this->get('/core/authorization/check?principal_id=principal-org-a&permission=plugin.hello-world.hello.view')
            ->assertOk()
            ->assertJsonPath('result.status', 'deny')
            ->assertJsonPath('result.reason', 'organization_context_required');
    }

    public function test_registered_permission_without_matching_grant_is_unresolved(): void
    {
        $this->get('/core/authorization/check?principal_id=principal-org-a&permission=core.permissions.view&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonPath('result.status', 'unresolved')
            ->assertJsonPath('result.reason', 'no_matching_grant');
    }

    public function test_platform_admin_is_allowed_to_view_roles(): void
    {
        $this->get('/core/authorization/check?principal_id=principal-admin&permission=core.roles.view')
            ->assertOk()
            ->assertJsonPath('result.status', 'allow')
            ->assertJsonPath('result.allowed', true);
    }
}
