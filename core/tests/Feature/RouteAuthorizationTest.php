<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_asset_plugin_route_requires_view_permission(): void
    {
        $this->get('/plugins/assets?principal_id=principal-org-a&organization_id=org-a')
            ->assertFound();

        $this->get('/plugins/assets?principal_id=principal-org-a&organization_id=org-missing')
            ->assertForbidden();
    }

    public function test_the_asset_transition_route_requires_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.asset-catalog.root',
            'membership_id' => 'membership-org-a-viewer',
        ];

        $this->post('/plugins/assets/asset-erp-prod/transitions/submit-review', $payload)
            ->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/assets/asset-erp-prod/transitions/submit-review', $payload)
            ->assertFound();
    }

    public function test_the_asset_shell_screen_hides_transitions_for_view_only_access(): void
    {
        $this->get('/app?menu=plugin.asset-catalog.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-viewer')
            ->assertOk()
            ->assertSee('View-only access')
            ->assertDontSee('Submit Review');
    }

    public function test_the_actor_directory_route_requires_view_permission(): void
    {
        $this->get('/plugins/actors?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk();

        $this->get('/plugins/actors?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_controls_artifact_upload_route_requires_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.root',
            'membership_id' => 'membership-org-a-viewer',
            'label' => 'Viewer attempt',
            'artifact_type' => 'evidence',
            'artifact' => \Illuminate\Http\UploadedFile::fake()->createWithContent('viewer.txt', 'viewer'),
        ];

        $this->post('/plugins/controls/control-access-review/artifacts', $payload)
            ->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/controls/control-access-review/artifacts', $payload)
            ->assertFound();
    }
}
