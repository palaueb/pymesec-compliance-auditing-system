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

    public function test_the_controls_create_and_update_routes_require_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.root',
            'membership_id' => 'membership-org-a-viewer',
            'name' => 'Viewer Control',
            'framework' => 'ISO 27001',
            'domain' => 'Identity',
            'evidence' => 'Viewer should not create',
        ];

        $this->post('/plugins/controls', $payload)->assertForbidden();
        $this->post('/plugins/controls/control-access-review', $payload)->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/controls', $payload)->assertFound();
        $this->post('/plugins/controls/control-access-review', $payload)->assertFound();
    }

    public function test_the_risk_transition_and_artifact_routes_require_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.risk-management.root',
            'membership_id' => 'membership-org-a-viewer',
        ];

        $this->post('/plugins/risks/risk-access-drift/transitions/start-assessment', $payload)
            ->assertForbidden();

        $this->post('/plugins/risks/risk-access-drift/artifacts', [
            ...$payload,
            'label' => 'Viewer risk attempt',
            'artifact_type' => 'evidence',
            'artifact' => \Illuminate\Http\UploadedFile::fake()->createWithContent('viewer-risk.txt', 'viewer'),
        ])->assertForbidden();

        $this->post('/plugins/risks', [
            ...$payload,
            'title' => 'Viewer risk',
            'category' => 'Ops',
            'inherent_score' => 10,
            'residual_score' => 5,
            'treatment' => 'Blocked',
        ])->assertForbidden();

        $this->post('/plugins/risks/risk-access-drift', [
            ...$payload,
            'title' => 'Viewer risk',
            'category' => 'Ops',
            'inherent_score' => 10,
            'residual_score' => 5,
            'treatment' => 'Blocked',
        ])->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/risks/risk-access-drift/transitions/start-assessment', $payload)
            ->assertFound();

        $this->post('/plugins/risks/risk-access-drift/artifacts', [
            ...$payload,
            'label' => 'Operator risk evidence',
            'artifact_type' => 'evidence',
            'artifact' => \Illuminate\Http\UploadedFile::fake()->createWithContent('operator-risk.txt', 'operator'),
        ])->assertFound();

        $this->post('/plugins/risks', [
            ...$payload,
            'title' => 'Operator risk',
            'category' => 'Ops',
            'inherent_score' => 10,
            'residual_score' => 5,
            'treatment' => 'Allowed',
        ])->assertFound();

        $this->post('/plugins/risks/risk-access-drift', [
            ...$payload,
            'title' => 'Operator updated risk',
            'category' => 'Ops',
            'inherent_score' => 11,
            'residual_score' => 6,
            'treatment' => 'Allowed update',
        ])->assertFound();
    }

    public function test_the_findings_routes_require_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.findings-remediation.root',
            'membership_id' => 'membership-org-a-viewer',
        ];

        $this->post('/plugins/findings/finding-access-review-gap/transitions/triage', $payload)
            ->assertForbidden();

        $this->post('/plugins/findings/finding-access-review-gap/artifacts', [
            ...$payload,
            'label' => 'Viewer finding attempt',
            'artifact_type' => 'evidence',
            'artifact' => \Illuminate\Http\UploadedFile::fake()->createWithContent('viewer-finding.txt', 'viewer'),
        ])->assertForbidden();

        $this->post('/plugins/findings', [
            ...$payload,
            'title' => 'Viewer finding',
            'severity' => 'medium',
            'description' => 'Viewer should not create findings.',
        ])->assertForbidden();

        $this->post('/plugins/findings/finding-access-review-gap', [
            ...$payload,
            'title' => 'Viewer update',
            'severity' => 'medium',
            'description' => 'Viewer should not update findings.',
        ])->assertForbidden();

        $this->post('/plugins/findings/finding-access-review-gap/actions', [
            ...$payload,
            'title' => 'Viewer action',
            'status' => 'planned',
        ])->assertForbidden();

        $this->post('/plugins/findings/actions/action-access-review-pack', [
            ...$payload,
            'title' => 'Viewer action update',
            'status' => 'planned',
        ])->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/findings/finding-access-review-gap/transitions/triage', $payload)
            ->assertFound();

        $this->post('/plugins/findings/finding-access-review-gap/artifacts', [
            ...$payload,
            'label' => 'Operator finding evidence',
            'artifact_type' => 'evidence',
            'artifact' => \Illuminate\Http\UploadedFile::fake()->createWithContent('operator-finding.txt', 'operator'),
        ])->assertFound();

        $this->post('/plugins/findings', [
            ...$payload,
            'title' => 'Operator finding',
            'severity' => 'high',
            'description' => 'Operator can create findings.',
        ])->assertFound();

        $this->post('/plugins/findings/finding-access-review-gap', [
            ...$payload,
            'title' => 'Operator updated finding',
            'severity' => 'critical',
            'description' => 'Operator can update findings.',
        ])->assertFound();

        $this->post('/plugins/findings/finding-access-review-gap/actions', [
            ...$payload,
            'title' => 'Operator action',
            'status' => 'planned',
        ])->assertFound();

        $this->post('/plugins/findings/actions/action-access-review-pack', [
            ...$payload,
            'title' => 'Operator updated action',
            'status' => 'done',
        ])->assertFound();
    }
}
