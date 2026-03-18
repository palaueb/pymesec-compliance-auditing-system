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
            'owner_label' => 'Finance Operations',
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
