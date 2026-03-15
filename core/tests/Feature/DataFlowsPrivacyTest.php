<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataFlowsPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_privacy_plugin_route_requires_view_permission(): void
    {
        $this->get('/plugins/privacy/data-flows?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'data-flow-customer-support-handoff',
                'title' => 'Customer support handoff',
            ]);

        $this->get('/plugins/privacy/data-flows?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_data_flow_register_screen_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.data-flows-privacy.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Data Flows Register')
            ->assertSee('Customer support handoff')
            ->assertSee('Add data flow')
            ->assertSee('Edit details');
    }

    public function test_privacy_transitions_and_artifacts_render_on_the_shell(): void
    {
        Storage::fake('local');

        $this->post('/plugins/privacy/data-flows/data-flow-customer-support-handoff/transitions/submit-review', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.data-flows-privacy.root',
            'flow_id' => 'data-flow-customer-support-handoff',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $this->post('/plugins/privacy/activities/processing-activity-customer-support-operations/artifacts', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.data-flows-privacy.activities',
            'activity_id' => 'processing-activity-customer-support-operations',
            'membership_id' => 'membership-org-a-hello',
            'label' => 'RoPA export',
            'artifact_type' => 'record',
            'artifact' => UploadedFile::fake()->createWithContent('ropa.txt', 'ropa draft'),
        ])->assertFound();

        $this->get('/app?menu=plugin.data-flows-privacy.root&flow_id=data-flow-customer-support-handoff&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('submit-review');

        $this->get('/app?menu=plugin.data-flows-privacy.activities&activity_id=processing-activity-customer-support-operations&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Processing Activities')
            ->assertSee('RoPA export')
            ->assertSee('ropa.txt');
    }

    public function test_data_flows_and_processing_activities_can_be_created_and_edited_from_the_shell_runtime(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/privacy/data-flows', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.root',
            'title' => 'HR onboarding transfer',
            'source' => 'HR workspace',
            'destination' => 'Payroll partner',
            'data_category_summary' => 'Employment identifiers and payroll setup data.',
            'transfer_type' => 'vendor',
            'scope_id' => 'scope-eu',
            'linked_asset_id' => 'asset-erp-prod',
            'linked_risk_id' => 'risk-access-drift',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->post('/plugins/privacy/activities', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.activities',
            'title' => 'HR onboarding administration',
            'purpose' => 'Set up staff records and payroll onboarding.',
            'lawful_basis' => 'contract',
            'scope_id' => 'scope-eu',
            'linked_data_flow_ids' => 'data-flow-hr-onboarding-transfer',
            'linked_risk_ids' => 'risk-access-drift',
            'linked_policy_id' => 'policy-access-governance',
            'linked_finding_id' => 'finding-access-review-gap',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->post('/plugins/privacy/data-flows/data-flow-hr-onboarding-transfer', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.root',
            'flow_id' => 'data-flow-hr-onboarding-transfer',
            'title' => 'HR onboarding vendor transfer',
            'source' => 'HR workspace',
            'destination' => 'Payroll partner',
            'data_category_summary' => 'Employment identifiers, payroll setup data, and contract references.',
            'transfer_type' => 'vendor',
            'scope_id' => 'scope-eu',
            'linked_asset_id' => 'asset-erp-prod',
            'linked_risk_id' => 'risk-access-drift',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->post('/plugins/privacy/activities/processing-activity-hr-onboarding-administration', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.activities',
            'activity_id' => 'processing-activity-hr-onboarding-administration',
            'title' => 'HR onboarding workflow',
            'purpose' => 'Manage staff onboarding records and payroll initiation.',
            'lawful_basis' => 'contract',
            'scope_id' => 'scope-eu',
            'linked_data_flow_ids' => 'data-flow-hr-onboarding-transfer',
            'linked_risk_ids' => 'risk-access-drift',
            'linked_policy_id' => 'policy-access-governance',
            'linked_finding_id' => 'finding-access-review-gap',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->get('/app?menu=plugin.data-flows-privacy.root&flow_id=data-flow-hr-onboarding-transfer&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('HR onboarding vendor transfer')
            ->assertSee('Employment identifiers, payroll setup data, and contract references.')
            ->assertSee('Compliance Office');

        $this->get('/app?menu=plugin.data-flows-privacy.activities&activity_id=processing-activity-hr-onboarding-administration&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('HR onboarding workflow')
            ->assertSee('Manage staff onboarding records and payroll initiation.');
    }
}
