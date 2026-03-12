<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RiskManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_risk_plugin_route_requires_view_permission(): void
    {
        $this->get('/plugins/risks?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'risk-access-drift',
                'title' => 'Privileged access drift',
            ]);

        $this->get('/plugins/risks?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_risk_register_screen_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.risk-management.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Risk Register')
            ->assertSee('Privileged access drift')
            ->assertSee('Quarterly certification and emergency access review.')
            ->assertSee('Ava Mason');
    }

    public function test_risk_transition_and_artifact_render_on_the_board(): void
    {
        Storage::fake('local');

        $this->post('/plugins/risks/risk-access-drift/transitions/start-assessment', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.risk-management.root',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $this->post('/plugins/risks/risk-access-drift/artifacts', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.risk-management.root',
            'membership_id' => 'membership-org-a-hello',
            'label' => 'Assessment note',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('assessment.txt', 'review in progress'),
        ])->assertFound();

        $this->get('/app?menu=plugin.risk-management.board&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Risk Board')
            ->assertSee('start-assessment')
            ->assertSee('Assessment note')
            ->assertSee('assessment.txt');
    }

    public function test_risk_register_hides_transitions_for_view_only_access(): void
    {
        $this->get('/app?menu=plugin.risk-management.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-viewer')
            ->assertOk()
            ->assertSee('View-only access')
            ->assertDontSee('Start Assessment');
    }
}
