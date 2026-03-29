<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PymeSec\Core\Permissions\Contracts\AuthorizationStoreInterface;
use Tests\TestCase;

class ObjectLevelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_object_lists_are_filtered_by_functional_assignments(): void
    {
        $membershipId = $this->provisionPrincipalForActor(
            principalId: 'principal-it-owner',
            actorId: 'actor-it-services',
            roles: ['asset-operator', 'risk-operator', 'findings-operator'],
        );

        $this->get('/app?menu=plugin.asset-catalog.root&principal_id=principal-it-owner&organization_id=org-a&membership_ids[]='.$membershipId)
            ->assertOk()
            ->assertSee('Managed Laptop Fleet')
            ->assertDontSee('ERP Production');

        $this->get('/plugins/risks?principal_id=principal-it-owner&organization_id=org-a&membership_ids[]='.$membershipId)
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'risk-backup-assurance',
                'title' => 'Restore assurance gap',
            ])
            ->assertJsonMissing([
                'id' => 'risk-access-drift',
                'title' => 'Privileged access drift',
            ]);

        $this->get('/plugins/findings?principal_id=principal-it-owner&organization_id=org-a&membership_ids[]='.$membershipId)
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'finding-backup-test-gap',
                'title' => 'Restore test traceability gap',
            ])
            ->assertJsonMissing([
                'id' => 'finding-access-review-gap',
                'title' => 'Access review evidence gap',
            ]);
    }

    public function test_object_level_access_blocks_operations_on_unassigned_records(): void
    {
        $membershipId = $this->provisionPrincipalForActor(
            principalId: 'principal-it-owner',
            actorId: 'actor-it-services',
            roles: ['asset-operator', 'risk-operator', 'findings-operator'],
        );

        $payload = [
            'principal_id' => 'principal-it-owner',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.asset-catalog.root',
            'membership_id' => $membershipId,
        ];

        $this->post('/plugins/assets/asset-erp-prod', [
            ...$payload,
            'name' => 'ERP Production',
            'type' => 'application',
            'criticality' => 'critical',
            'classification' => 'restricted',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-finance-ops',
        ])->assertForbidden();

        $this->post('/plugins/risks/risk-access-drift/transitions/start-assessment', [
            ...$payload,
            'menu' => 'plugin.risk-management.root',
        ])->assertForbidden();

        $this->post('/plugins/findings/finding-access-review-gap/transitions/triage', [
            ...$payload,
            'menu' => 'plugin.findings-remediation.root',
        ])->assertForbidden();
    }

    public function test_phase_two_domains_are_filtered_by_functional_assignments(): void
    {
        $membershipId = $this->provisionPrincipalForActor(
            principalId: 'principal-it-owner',
            actorId: 'actor-it-services',
            roles: ['control-operator', 'continuity-operator', 'privacy-operator', 'policy-operator', 'assessment-operator'],
        );

        DB::table('functional_assignments')->insert([
            'id' => 'assignment-assessment-it-owner',
            'functional_actor_id' => 'actor-it-services',
            'domain_object_type' => 'assessment',
            'domain_object_id' => 'assessment-q2-access-resilience',
            'assignment_type' => 'owner',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'metadata' => json_encode(['source' => 'test'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('assessment_campaigns')->insert([
            'id' => 'assessment-unassigned-finance-check',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'framework_id' => 'framework-iso-27001',
            'title' => 'Finance segregation review',
            'summary' => 'Unassigned assessment used to verify object scoping.',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-15',
            'status' => 'draft',
            'signoff_notes' => null,
            'signed_off_on' => null,
            'signed_off_by_principal_id' => null,
            'closure_summary' => null,
            'closed_on' => null,
            'closed_by_principal_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $query = '?principal_id=principal-it-owner&organization_id=org-a&membership_ids[]='.$membershipId;

        $this->get('/plugins/controls'.$query)
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'control-backup-governance',
                'name' => 'Backup Governance',
            ])
            ->assertJsonMissing([
                'id' => 'control-access-review',
                'name' => 'Quarterly Access Review',
            ]);

        $this->get('/plugins/continuity/services'.$query)
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'continuity-service-backup-recovery',
                'title' => 'Backup and Recovery Operations',
            ])
            ->assertJsonMissing([
                'id' => 'continuity-service-customer-support',
                'title' => 'Customer Support Operations',
            ]);

        $this->get('/plugins/continuity/plans'.$query)
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'continuity-plan-restore-bridge',
                'title' => 'Restore bridge activation',
            ])
            ->assertJsonMissing([
                'id' => 'continuity-plan-support-fallback',
                'title' => 'Support fallback rota',
            ]);

        $this->get('/plugins/privacy/data-flows'.$query)
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'data-flow-backup-vendor-transfer',
                'title' => 'Backup vendor transfer',
            ])
            ->assertJsonMissing([
                'id' => 'data-flow-customer-support-handoff',
                'title' => 'Customer support handoff',
            ]);

        $this->get('/plugins/privacy/activities'.$query)
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'processing-activity-restore-assurance',
                'title' => 'Restore assurance coordination',
            ])
            ->assertJsonMissing([
                'id' => 'processing-activity-customer-support-operations',
                'title' => 'Customer support operations',
            ]);

        $this->get('/plugins/policies'.$query)
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'policy-backup-assurance',
                'title' => 'Backup Assurance Policy',
            ])
            ->assertJsonMissing([
                'id' => 'policy-access-governance',
                'title' => 'Access Governance Policy',
            ]);

        $this->get('/plugins/policies/exceptions'.$query)
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'exception-restore-lab-gap',
                'title' => 'Restore lab availability exception',
            ])
            ->assertJsonMissing([
                'id' => 'exception-break-glass-window',
                'title' => 'Break-glass approval window',
            ]);

        $this->get('/plugins/assessments'.$query)
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'assessment-q2-access-resilience',
                'title' => 'Q2 Access and Resilience Review',
            ])
            ->assertJsonMissing([
                'id' => 'assessment-unassigned-finance-check',
                'title' => 'Finance segregation review',
            ]);
    }

    public function test_phase_two_domains_block_operations_on_unassigned_records(): void
    {
        $membershipId = $this->provisionPrincipalForActor(
            principalId: 'principal-it-owner',
            actorId: 'actor-it-services',
            roles: ['control-operator', 'continuity-operator', 'privacy-operator', 'policy-operator', 'assessment-operator'],
        );

        DB::table('functional_assignments')->insert([
            'id' => 'assignment-assessment-it-owner-blocking',
            'functional_actor_id' => 'actor-it-services',
            'domain_object_type' => 'assessment',
            'domain_object_id' => 'assessment-q2-access-resilience',
            'assignment_type' => 'owner',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'metadata' => json_encode(['source' => 'test'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('assessment_campaigns')->insert([
            'id' => 'assessment-unassigned-finance-check',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'framework_id' => 'framework-iso-27001',
            'title' => 'Finance segregation review',
            'summary' => 'Unassigned assessment used to verify operation blocking.',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-15',
            'status' => 'draft',
            'signoff_notes' => null,
            'signed_off_on' => null,
            'signed_off_by_principal_id' => null,
            'closure_summary' => null,
            'closed_on' => null,
            'closed_by_principal_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'principal_id' => 'principal-it-owner',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => $membershipId,
        ];

        $this->post('/plugins/controls/control-access-review/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.controls-catalog.root',
        ])->assertForbidden();

        $this->post('/plugins/continuity/services/continuity-service-customer-support/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
        ])->assertForbidden();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
        ])->assertForbidden();

        $this->post('/plugins/privacy/data-flows/data-flow-customer-support-handoff/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.root',
        ])->assertForbidden();

        $this->post('/plugins/privacy/activities/processing-activity-customer-support-operations/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.activities',
        ])->assertForbidden();

        $this->post('/plugins/policies/policy-access-governance/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.root',
        ])->assertForbidden();

        $this->post('/plugins/policies/exceptions/exception-break-glass-window/transitions/approve', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.exceptions',
        ])->assertForbidden();

        $this->post('/plugins/assessments/assessment-unassigned-finance-check/transitions/activate', [
            ...$payload,
            'menu' => 'plugin.assessments-audits.root',
        ])->assertForbidden();
    }

    private function provisionPrincipalForActor(string $principalId, string $actorId, array $roles): string
    {
        $membershipId = 'membership-'.$principalId.'-org-a';

        DB::table('memberships')->insert([
            'id' => $membershipId,
            'principal_id' => $principalId,
            'organization_id' => 'org-a',
            'roles' => json_encode($roles, JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('principal_functional_actor_links')->insert([
            'id' => 'link-'.$principalId.'-'.$actorId,
            'principal_id' => $principalId,
            'functional_actor_id' => $actorId,
            'organization_id' => 'org-a',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $store = app(AuthorizationStoreInterface::class);

        foreach ($roles as $role) {
            $store->upsertGrant(
                id: null,
                targetType: 'membership',
                targetId: $membershipId,
                grantType: 'role',
                value: $role,
                contextType: 'organization',
                organizationId: 'org-a',
            );
        }

        return $membershipId;
    }
}
