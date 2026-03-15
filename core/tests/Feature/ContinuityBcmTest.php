<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContinuityBcmTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_continuity_plugin_routes_require_view_permission(): void
    {
        $this->get('/plugins/continuity/services?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'continuity-service-customer-support',
                'title' => 'Customer Support Operations',
            ]);

        $this->get('/plugins/continuity/plans?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'continuity-plan-support-fallback',
                'title' => 'Support fallback rota',
            ]);

        $this->get('/plugins/continuity/services?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_continuity_screens_render_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.continuity-bcm.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Continuity Services')
            ->assertSee('Customer Support Operations')
            ->assertSee('Add continuity service')
            ->assertSee('Edit details')
            ->assertSee('Backup and Recovery Operations');

        $this->get('/app?menu=plugin.continuity-bcm.root&service_id=continuity-service-customer-support&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Back to services')
            ->assertSee('Add recovery plan')
            ->assertSee('Dependencies')
            ->assertSee('Documents');

        $this->get('/app?menu=plugin.continuity-bcm.plans&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Recovery Plans')
            ->assertSee('Support fallback rota')
            ->assertSee('Choose service')
            ->assertSee('Open');

        $this->get('/app?menu=plugin.continuity-bcm.plans&plan_id=continuity-plan-support-fallback&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Support fallback rota')
            ->assertSee('Back to plans')
            ->assertSee('Exercises')
            ->assertSee('Evidence');
    }

    public function test_continuity_transitions_and_artifacts_render_inside_the_shell(): void
    {
        Storage::fake('local');

        $this->post('/plugins/continuity/services/continuity-service-customer-support/transitions/submit-review', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.continuity-bcm.root',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/artifacts', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.continuity-bcm.plans',
            'membership_id' => 'membership-org-a-hello',
            'label' => 'Recovery exercise',
            'artifact_type' => 'recovery-plan',
            'artifact' => UploadedFile::fake()->createWithContent('exercise.txt', 'exercise results'),
        ])->assertFound();

        $this->get('/app?menu=plugin.continuity-bcm.root&service_id=continuity-service-customer-support&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Activate');

        $this->get('/app?menu=plugin.continuity-bcm.plans&plan_id=continuity-plan-support-fallback&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Recovery exercise')
            ->assertSee('exercise.txt');
    }

    public function test_continuity_services_and_plans_can_be_created_and_edited_from_the_shell_runtime(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/continuity/services', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'title' => 'Finance operations bridge',
            'impact_tier' => 'critical',
            'recovery_time_objective_hours' => 6,
            'recovery_point_objective_hours' => 2,
            'linked_asset_id' => 'asset-erp-prod',
            'linked_risk_id' => 'risk-access-drift',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->post('/plugins/continuity/services/continuity-service-finance-operations-bridge/plans', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'title' => 'Finance bridge fallback',
            'strategy_summary' => 'Switch finance approvals to the fallback rota within one shift.',
            'test_due_on' => now()->addDays(30)->toDateString(),
            'linked_policy_id' => 'policy-access-governance',
            'linked_finding_id' => 'finding-access-review-gap',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->post('/plugins/continuity/services/continuity-service-finance-operations-bridge', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'title' => 'Finance operations fallback bridge',
            'impact_tier' => 'critical',
            'recovery_time_objective_hours' => 4,
            'recovery_point_objective_hours' => 1,
            'linked_asset_id' => 'asset-erp-prod',
            'linked_risk_id' => 'risk-access-drift',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->post('/plugins/continuity/plans/continuity-plan-finance-bridge-fallback', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'title' => 'Finance approval fallback',
            'strategy_summary' => 'Switch finance approvals to the backup rota and validate ledgers after restore.',
            'test_due_on' => now()->addDays(45)->toDateString(),
            'linked_policy_id' => 'policy-access-governance',
            'linked_finding_id' => 'finding-access-review-gap',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->get('/app?menu=plugin.continuity-bcm.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Finance operations fallback bridge')
            ->assertSee('Compliance Office');

        $this->get('/app?menu=plugin.continuity-bcm.plans&plan_id=continuity-plan-finance-bridge-fallback&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Finance approval fallback')
            ->assertSee('backup rota and validate ledgers after restore.')
            ->assertSee('Compliance Office');
    }

    public function test_continuity_dependencies_exercises_and_test_runs_can_be_logged_from_the_shell_runtime(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/continuity/services/continuity-service-customer-support/dependencies', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'depends_on_service_id' => 'continuity-service-backup-recovery',
            'dependency_kind' => 'critical',
            'recovery_notes' => 'Support fallback relies on backup restore validation.',
        ])->assertFound();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/exercises', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'exercise_date' => now()->toDateString(),
            'exercise_type' => 'tabletop',
            'scenario_summary' => 'Cross-team outage escalation using the fallback rota.',
            'outcome' => 'partial',
            'follow_up_summary' => 'Needs deeper vendor failover rehearsal.',
        ])->assertFound();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/executions', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'executed_on' => now()->toDateString(),
            'execution_type' => 'recovery-drill',
            'status' => 'passed',
            'participants' => 'Support Leads, Backup Operations',
            'notes' => 'Fallback intake stayed within the expected recovery window.',
        ])->assertFound();

        $this->get('/app?menu=plugin.continuity-bcm.root&service_id=continuity-service-customer-support&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Critical dependency')
            ->assertSee('Support fallback relies on backup restore validation.');

        $this->get('/app?menu=plugin.continuity-bcm.plans&plan_id=continuity-plan-support-fallback&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Cross-team outage escalation using the fallback rota.')
            ->assertSee('Needs deeper vendor failover rehearsal.')
            ->assertSee('Support Leads, Backup Operations')
            ->assertSee('Fallback intake stayed within the expected recovery window.');
    }
}
