<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
            ->assertSee('Impact tiers and dependency kinds are business-managed catalog values')
            ->assertSee('Continuity service list')
            ->assertSee('This list stays focused on impact summary, owner summary, linked records, state, and Open.')
            ->assertSee('Customer Support Operations')
            ->assertSee('Add continuity service')
            ->assertSee('Open')
            ->assertDontSee('Backup and Recovery Operations');

        $this->get('/app?menu=plugin.continuity-bcm.root&service_id=continuity-service-customer-support&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Continuity Service Detail keeps recovery plans, dependencies, documents, workflow, ownership, and service maintenance in one workspace.')
            ->assertSee('Continuity Service Detail')
            ->assertSee('Back to services')
            ->assertSee('Add recovery plan')
            ->assertSee('Dependencies')
            ->assertSee('Documents');

        $this->get('/app?menu=plugin.continuity-bcm.plans&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Recovery Plans')
            ->assertSee('system-controlled continuity values')
            ->assertSee('Support fallback rota')
            ->assertSee('Choose service')
            ->assertSee('This list stays summary-only.')
            ->assertSee('Open')
            ->assertDontSee('Attach evidence')
            ->assertDontSee('Log exercise')
            ->assertDontSee('Log test run');

        $this->get('/app?menu=plugin.continuity-bcm.plans&plan_id=continuity-plan-support-fallback&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Support fallback rota')
            ->assertSee('Back to plans')
            ->assertSee('Recovery Plan Detail keeps workflow, linked records, ownership, evidence, exercises, and test runs in one record workspace.')
            ->assertSee('Linked records')
            ->assertSee('Open continuity service')
            ->assertSee('Governance actions')
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
            'owner_actor_id' => 'actor-ava-mason',
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
            'owner_actor_id' => 'actor-ava-mason',
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
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->get('/app?menu=plugin.continuity-bcm.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Finance operations fallback bridge')
            ->assertSee('Ava Mason');

        $this->get('/app?menu=plugin.continuity-bcm.plans&plan_id=continuity-plan-finance-bridge-fallback&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Finance approval fallback')
            ->assertSee('backup rota and validate ledgers after restore.')
            ->assertSee('Ava Mason');
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

    public function test_continuity_dependency_ids_are_bounded_for_long_service_identifiers(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-hello',
        ];

        $sourceTitle = 'Alpha museum continuity service with a very descriptive operating name for ticketing reception lighting security and public visitor access';
        $targetTitle = 'Bravo museum continuity service with a very descriptive operating name for electrical backup alarms cameras and safe visitor routes';

        $this->post('/plugins/continuity/services', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'title' => $sourceTitle,
            'impact_tier' => 'critical',
            'recovery_time_objective_hours' => 4,
            'recovery_point_objective_hours' => 1,
            'linked_asset_id' => 'asset-erp-prod',
            'linked_risk_id' => 'risk-access-drift',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->post('/plugins/continuity/services', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'title' => $targetTitle,
            'impact_tier' => 'critical',
            'recovery_time_objective_hours' => 2,
            'recovery_point_objective_hours' => 0,
            'linked_asset_id' => 'asset-erp-prod',
            'linked_risk_id' => 'risk-access-drift',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $sourceId = DB::table('continuity_services')->where('title', $sourceTitle)->value('id');
        $targetId = DB::table('continuity_services')->where('title', $targetTitle)->value('id');

        $this->assertIsString($sourceId);
        $this->assertIsString($targetId);
        $this->assertLessThanOrEqual(120, strlen($sourceId));
        $this->assertLessThanOrEqual(120, strlen($targetId));

        $this->post("/plugins/continuity/services/{$sourceId}/dependencies", [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'scope_id' => 'scope-eu',
            'depends_on_service_id' => $targetId,
            'dependency_kind' => 'critical',
            'recovery_notes' => 'The public visit process depends on power and monitored access being available.',
        ])->assertFound();

        $dependencyId = DB::table('continuity_service_dependencies')
            ->where('source_service_id', $sourceId)
            ->where('depends_on_service_id', $targetId)
            ->value('id');

        $this->assertIsString($dependencyId);
        $this->assertLessThanOrEqual(120, strlen($dependencyId));
    }

    public function test_continuity_rejects_invalid_governed_reference_values(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->from('/app?menu=plugin.continuity-bcm.root')
            ->post('/plugins/continuity/services', [
                ...$payload,
                'menu' => 'plugin.continuity-bcm.root',
                'title' => 'Broken service',
                'impact_tier' => 'urgent',
                'recovery_time_objective_hours' => 4,
                'recovery_point_objective_hours' => 1,
            ])
            ->assertRedirect('/app?menu=plugin.continuity-bcm.root')
            ->assertSessionHasErrors(['impact_tier']);

        $this->from('/app?menu=plugin.continuity-bcm.root&service_id=continuity-service-customer-support')
            ->post('/plugins/continuity/services/continuity-service-customer-support/dependencies', [
                ...$payload,
                'menu' => 'plugin.continuity-bcm.root',
                'depends_on_service_id' => 'continuity-service-backup-recovery',
                'dependency_kind' => 'partner',
            ])
            ->assertRedirect('/app?menu=plugin.continuity-bcm.root&service_id=continuity-service-customer-support')
            ->assertSessionHasErrors(['dependency_kind']);

        $this->from('/app?menu=plugin.continuity-bcm.plans&plan_id=continuity-plan-support-fallback')
            ->post('/plugins/continuity/plans/continuity-plan-support-fallback/exercises', [
                ...$payload,
                'menu' => 'plugin.continuity-bcm.plans',
                'exercise_date' => now()->toDateString(),
                'exercise_type' => 'game-day',
                'scenario_summary' => 'Invalid exercise type should fail.',
                'outcome' => 'partial',
            ])
            ->assertRedirect('/app?menu=plugin.continuity-bcm.plans&plan_id=continuity-plan-support-fallback')
            ->assertSessionHasErrors(['exercise_type']);

        $this->from('/app?menu=plugin.continuity-bcm.plans&plan_id=continuity-plan-support-fallback')
            ->post('/plugins/continuity/plans/continuity-plan-support-fallback/executions', [
                ...$payload,
                'menu' => 'plugin.continuity-bcm.plans',
                'executed_on' => now()->toDateString(),
                'execution_type' => 'restore-test',
                'status' => 'warning',
            ])
            ->assertRedirect('/app?menu=plugin.continuity-bcm.plans&plan_id=continuity-plan-support-fallback')
            ->assertSessionHasErrors(['status']);
    }

    public function test_continuity_services_and_plans_support_multiple_owner_assignments_and_owner_removal(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/continuity/services/continuity-service-customer-support', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'service_id' => 'continuity-service-customer-support',
            'title' => 'Customer Support Operations',
            'impact_tier' => 'high',
            'recovery_time_objective_hours' => 8,
            'recovery_point_objective_hours' => 2,
            'linked_asset_id' => 'asset-erp-prod',
            'linked_risk_id' => 'risk-access-drift',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->assertSame(['actor-ava-mason', 'actor-compliance-office'], DB::table('functional_assignments')
            ->where('domain_object_type', 'continuity-service')
            ->where('domain_object_id', 'continuity-service-customer-support')
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->orderBy('functional_actor_id')
            ->pluck('functional_actor_id')
            ->all());

        $this->get('/app?menu=plugin.continuity-bcm.root&service_id=continuity-service-customer-support&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Owners: 2')
            ->assertSee('Ava Mason')
            ->assertSee('Compliance Office');

        $serviceAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'continuity-service')
            ->where('domain_object_id', 'continuity-service-customer-support')
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-compliance-office')
            ->value('id');

        $this->post("/plugins/continuity/services/continuity-service-customer-support/owners/{$serviceAssignmentId}/remove", [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
        ])->assertFound();

        $this->assertSame(['actor-ava-mason'], DB::table('functional_assignments')
            ->where('domain_object_type', 'continuity-service')
            ->where('domain_object_id', 'continuity-service-customer-support')
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->pluck('functional_actor_id')
            ->all());

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'plan_id' => 'continuity-plan-support-fallback',
            'title' => 'Support fallback rota',
            'strategy_summary' => 'Switch support intake to the fallback rota and route escalations to the continuity bridge.',
            'test_due_on' => DB::table('continuity_recovery_plans')->where('id', 'continuity-plan-support-fallback')->value('test_due_on'),
            'linked_policy_id' => 'policy-access-governance',
            'linked_finding_id' => 'finding-access-review-gap',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->assertSame(['actor-ava-mason', 'actor-compliance-office'], DB::table('functional_assignments')
            ->where('domain_object_type', 'continuity-plan')
            ->where('domain_object_id', 'continuity-plan-support-fallback')
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->orderBy('functional_actor_id')
            ->pluck('functional_actor_id')
            ->all());

        $this->get('/app?menu=plugin.continuity-bcm.plans&plan_id=continuity-plan-support-fallback&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Owners: 2')
            ->assertSee('Ava Mason')
            ->assertSee('Compliance Office');

        $planAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'continuity-plan')
            ->where('domain_object_id', 'continuity-plan-support-fallback')
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-compliance-office')
            ->value('id');

        $this->post("/plugins/continuity/plans/continuity-plan-support-fallback/owners/{$planAssignmentId}/remove", [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
        ])->assertFound();

        $this->assertSame(['actor-ava-mason'], DB::table('functional_assignments')
            ->where('domain_object_type', 'continuity-plan')
            ->where('domain_object_id', 'continuity-plan-support-fallback')
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->pluck('functional_actor_id')
            ->all());
    }
}
