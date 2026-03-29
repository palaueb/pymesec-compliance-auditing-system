<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PymeSec\Core\Permissions\Contracts\AuthorizationStoreInterface;
use Tests\TestCase;

class AuthenticatedRequestContextIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_requests_cannot_borrow_manage_membership_from_another_principal(): void
    {
        $viewerMembershipId = $this->provisionMembership(
            principalId: 'principal-security-viewer',
            organizationId: 'org-a',
            roles: ['asset-viewer'],
        );

        $operatorMembershipId = $this->provisionMembership(
            principalId: 'principal-security-operator',
            organizationId: 'org-a',
            roles: ['asset-operator'],
        );

        $this->withSession(['auth.principal_id' => 'principal-security-viewer'])
            ->post('/plugins/assets', [
                'principal_id' => 'principal-security-operator',
                'organization_id' => 'org-a',
                'locale' => 'en',
                'menu' => 'plugin.asset-catalog.root',
                'membership_id' => $operatorMembershipId,
                'membership_ids' => [$operatorMembershipId, $viewerMembershipId],
                'name' => 'Spoofed operator asset',
                'type' => 'application',
                'criticality' => 'high',
                'classification' => 'internal',
            ])->assertForbidden();

        $this->assertDatabaseMissing('assets', [
            'name' => 'Spoofed operator asset',
            'organization_id' => 'org-a',
        ]);
    }

    public function test_authenticated_requests_reject_organizations_without_membership_context(): void
    {
        $this->provisionMembership(
            principalId: 'principal-org-a-only-viewer',
            organizationId: 'org-a',
            roles: ['asset-viewer'],
        );

        $this->withSession(['auth.principal_id' => 'principal-org-a-only-viewer'])
            ->get('/plugins/assets?organization_id=org-b')
            ->assertForbidden();
    }

    public function test_authenticated_requests_default_scoped_memberships_to_their_allowed_scope(): void
    {
        $membershipId = $this->provisionMembership(
            principalId: 'principal-risk-scope-it-viewer',
            organizationId: 'org-a',
            roles: ['risk-viewer'],
            scopeIds: ['scope-it'],
        );

        $this->withSession(['auth.principal_id' => 'principal-risk-scope-it-viewer'])
            ->get('/plugins/risks?organization_id=org-a&membership_ids[]='.$membershipId)
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'risk-backup-assurance',
                'title' => 'Restore assurance gap',
            ])
            ->assertJsonMissing([
                'id' => 'risk-access-drift',
                'title' => 'Privileged access drift',
            ]);
    }

    public function test_authenticated_requests_reject_explicit_scopes_outside_membership_grants(): void
    {
        $membershipId = $this->provisionMembership(
            principalId: 'principal-risk-scope-it-viewer',
            organizationId: 'org-a',
            roles: ['risk-viewer'],
            scopeIds: ['scope-it'],
        );

        $this->withSession(['auth.principal_id' => 'principal-risk-scope-it-viewer'])
            ->get('/plugins/risks?organization_id=org-a&scope_id=scope-eu&membership_ids[]='.$membershipId)
            ->assertForbidden();
    }

    public function test_authenticated_mutations_reject_scopes_outside_membership_grants(): void
    {
        $membershipId = $this->provisionMembership(
            principalId: 'principal-risk-scope-it-operator',
            organizationId: 'org-a',
            roles: ['risk-operator'],
            scopeIds: ['scope-it'],
        );

        $this->withSession(['auth.principal_id' => 'principal-risk-scope-it-operator'])
            ->post('/plugins/risks', [
                'principal_id' => 'principal-admin',
                'organization_id' => 'org-a',
                'locale' => 'en',
                'menu' => 'plugin.risk-management.root',
                'membership_id' => $membershipId,
                'title' => 'Cross scope spoof attempt',
                'category' => 'Ops',
                'inherent_score' => 34,
                'residual_score' => 21,
                'treatment' => 'This should not be created outside the granted scope.',
                'scope_id' => 'scope-eu',
            ])->assertForbidden();

        $this->assertDatabaseMissing('risks', [
            'title' => 'Cross scope spoof attempt',
            'organization_id' => 'org-a',
        ]);
    }

    /**
     * @param  array<int, string>  $roles
     * @param  array<int, string>  $scopeIds
     */
    private function provisionMembership(string $principalId, string $organizationId, array $roles, array $scopeIds = []): string
    {
        $membershipId = 'membership-'.$principalId.'-'.$organizationId;

        DB::table('memberships')->insert([
            'id' => $membershipId,
            'principal_id' => $principalId,
            'organization_id' => $organizationId,
            'roles' => json_encode($roles, JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($scopeIds as $scopeId) {
            DB::table('membership_scope')->insert([
                'membership_id' => $membershipId,
                'scope_id' => $scopeId,
            ]);
        }

        $store = app(AuthorizationStoreInterface::class);

        foreach ($roles as $role) {
            $store->upsertGrant(
                id: null,
                targetType: 'membership',
                targetId: $membershipId,
                grantType: 'role',
                value: $role,
                contextType: 'organization',
                organizationId: $organizationId,
            );
        }

        return $membershipId;
    }
}
