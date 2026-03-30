<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
            ->assertSee('Risk categories are business-managed catalog values')
            ->assertSee('Risk register list')
            ->assertSee('This list stays focused on category, score, owner summary, linked records, state, and Open.')
            ->assertSee('Privileged access drift')
            ->assertSee('Ava Mason')
            ->assertSee('Add risk')
            ->assertSee('Open');

        $this->get('/app?menu=plugin.risk-management.root&risk_id=risk-access-drift&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Risk Detail keeps evidence, workflow, linked records, ownership, and treatment maintenance in one workspace.')
            ->assertSee('Risk Detail')
            ->assertSee('Quarterly certification and emergency access review.')
            ->assertSee('Back to risks')
            ->assertSee('Edit risk')
            ->assertSee('Workflow');
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
            ->assertSee('Risk workflow board')
            ->assertSee('start-assessment')
            ->assertSee('Assessment note')
            ->assertSee('assessment.txt');
    }

    public function test_risk_register_hides_transitions_for_view_only_access(): void
    {
        $this->get('/app?menu=plugin.risk-management.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-viewer')
            ->assertOk()
            ->assertSee('Open')
            ->assertDontSee('Start Assessment');

        $this->get('/app?menu=plugin.risk-management.root&risk_id=risk-access-drift&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-viewer')
            ->assertOk()
            ->assertSee('View-only access')
            ->assertDontSee('Start Assessment');
    }

    public function test_risks_can_be_created_and_edited_from_the_shell_runtime(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.risk-management.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/risks', [
            ...$payload,
            'title' => 'Supplier onboarding gap',
            'category' => 'third-party',
            'inherent_score' => 22,
            'residual_score' => 12,
            'linked_asset_id' => 'asset-erp-prod',
            'linked_control_id' => 'control-access-review',
            'treatment' => 'Add supplier onboarding approval review.',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->get('/app?menu=plugin.risk-management.root&risk_id=risk-supplier-onboarding-gap&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier onboarding gap')
            ->assertSee('Add supplier onboarding approval review.');

        $this->post('/plugins/risks/risk-supplier-onboarding-gap', [
            ...$payload,
            'title' => 'Supplier onboarding coverage gap',
            'category' => 'third-party',
            'inherent_score' => 24,
            'residual_score' => 11,
            'linked_asset_id' => 'asset-erp-prod',
            'linked_control_id' => 'control-access-review',
            'treatment' => 'Add onboarding approvals and quarterly supplier review.',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->get('/app?menu=plugin.risk-management.root&risk_id=risk-supplier-onboarding-gap&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier onboarding coverage gap')
            ->assertSee('Add onboarding approvals and quarterly supplier review.')
            ->assertSee('Compliance Office');
    }

    public function test_risks_support_multiple_owner_assignments_and_owner_removal(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.risk-management.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/risks/risk-access-drift', [
            ...$payload,
            'title' => 'Privileged access drift',
            'category' => 'third-party',
            'inherent_score' => 20,
            'residual_score' => 10,
            'linked_asset_id' => 'asset-erp-prod',
            'linked_control_id' => 'control-access-review',
            'treatment' => 'Quarterly certification and emergency access review.',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->assertSame(['actor-ava-mason', 'actor-compliance-office'], DB::table('functional_assignments')
            ->where('domain_object_type', 'risk')
            ->where('domain_object_id', 'risk-access-drift')
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->orderBy('functional_actor_id')
            ->pluck('functional_actor_id')
            ->all());

        $this->get('/app?menu=plugin.risk-management.root&risk_id=risk-access-drift&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Owners: 2')
            ->assertSee('Ava Mason')
            ->assertSee('Compliance Office');

        $assignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'risk')
            ->where('domain_object_id', 'risk-access-drift')
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-compliance-office')
            ->value('id');

        $this->post("/plugins/risks/risk-access-drift/owners/{$assignmentId}/remove", $payload)->assertFound();

        $this->assertFalse((bool) DB::table('functional_assignments')
            ->where('id', $assignmentId)
            ->value('is_active'));

        $this->assertSame(['actor-ava-mason'], DB::table('functional_assignments')
            ->where('domain_object_type', 'risk')
            ->where('domain_object_id', 'risk-access-drift')
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->pluck('functional_actor_id')
            ->all());
    }
}
