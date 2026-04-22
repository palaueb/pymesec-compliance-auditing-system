<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PymeSec\Core\Security\ApiAccessTokenRepository;
use Tests\TestCase;

class FunctionalApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_workflow_keeps_identity_actor_roles_and_domain_ownership_boundaries(): void
    {
        $operatorContext = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
        ];

        $user = $this->postJson('/api/v1/identity-local/users', [
            ...$operatorContext,
            'display_name' => 'Josep Workflow',
            'username' => 'josep.workflow',
            'email' => 'josep.workflow@example.test',
            'job_title' => 'Operations lead',
            'password_enabled' => false,
            'magic_link_enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.display_name', 'Josep Workflow')
            ->assertJsonPath('data.password_enabled', false)
            ->assertJsonPath('data.magic_link_enabled', true)
            ->assertJsonMissingPath('data.password_hash')
            ->json('data');

        $principalId = (string) $user['principal_id'];

        $actor = $this->postJson('/api/v1/core/functional-actors', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'display_name' => 'Josep Workflow',
            'kind' => 'employee',
            'metadata' => [
                'email' => 'josep.workflow@example.test',
                'role' => 'Operations lead',
                'notes' => 'Created by functional API workflow test.',
            ],
        ])->assertOk()
            ->assertJsonPath('data.kind', 'employee')
            ->assertJsonPath('data.metadata.email', 'josep.workflow@example.test')
            ->json('data');

        $actorId = (string) $actor['id'];

        $membership = $this->postJson('/api/v1/identity-local/memberships', [
            ...$operatorContext,
            'subject_principal_id' => $principalId,
            'role_keys' => [],
            'scope_ids' => [],
        ])->assertOk()
            ->assertJsonPath('data.principal_id', $principalId)
            ->assertJsonPath('data.roles', [])
            ->json('data');

        $membershipId = (string) $membership['id'];

        $this->postJson('/api/v1/assets', [
            'principal_id' => $principalId,
            'organization_id' => 'org-a',
            'membership_id' => $membershipId,
            'name' => 'Unauthorized workflow asset',
            'type' => 'application',
            'criticality' => 'medium',
            'classification' => 'internal',
        ])->assertForbidden();

        $this->postJson('/api/v1/core/roles', [
            'principal_id' => 'principal-admin',
            'key' => 'workflow-domain-operator',
            'label' => 'Workflow domain operator',
            'permissions' => [
                'plugin.asset-catalog.assets.view',
                'plugin.asset-catalog.assets.manage',
                'plugin.risk-management.risks.view',
                'plugin.risk-management.risks.manage',
            ],
        ])->assertOk()
            ->assertJsonPath('data.role.is_system', false);

        $this->postJson('/api/v1/core/roles/grants', [
            'principal_id' => 'principal-admin',
            'target_type' => 'membership',
            'target_id' => $membershipId,
            'grant_type' => 'role',
            'value' => 'workflow-domain-operator',
            'context_type' => 'organization',
            'organization_id' => 'org-a',
        ])->assertOk();

        $this->postJson('/api/v1/core/functional-actors/links', [
            'principal_id' => 'principal-admin',
            'actor_id' => $actorId,
            'subject_principal_id' => $principalId,
            'organization_id' => 'org-a',
        ])->assertOk();

        $capabilities = $this->getJson('/api/v1/meta/capabilities?'.http_build_query([
            'principal_id' => $principalId,
            'organization_id' => 'org-a',
            'membership_id' => $membershipId,
        ]))->assertOk()
            ->json('data.permissions');

        $this->assertContains('plugin.asset-catalog.assets.manage', $capabilities);
        $this->assertContains('plugin.risk-management.risks.manage', $capabilities);

        $issued = $this->app->make(ApiAccessTokenRepository::class)->issue(
            principalId: $principalId,
            label: 'Workflow API token',
            organizationId: 'org-a',
            scopeId: null,
            createdByPrincipalId: 'principal-admin',
            expiresAt: null,
            abilities: [
                'plugin.asset-catalog.assets.view',
                'plugin.asset-catalog.assets.manage',
                'plugin.risk-management.risks.view',
                'plugin.risk-management.risks.manage',
            ],
        );

        $tokenCapabilities = $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/meta/capabilities')
            ->assertOk()
            ->assertJsonPath('data.principal_id', $principalId)
            ->json('data.permissions');

        $this->assertContains('plugin.asset-catalog.assets.manage', $tokenCapabilities);

        $asset = $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->postJson('/api/v1/assets', [
                'name' => 'Workflow Asset',
                'type' => 'application',
                'criticality' => 'high',
                'classification' => 'restricted',
                'owner_actor_id' => $actorId,
            ])->assertOk()
            ->assertJsonPath('data.owner_label', 'Josep Workflow')
            ->assertJsonPath('data.owner_assignments.0.actor_id', $actorId)
            ->json('data');

        $assetId = (string) $asset['id'];

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/assets/'.urlencode($assetId))
            ->assertOk()
            ->assertJsonPath('data.owner_label', 'Josep Workflow')
            ->assertJsonPath('data.owner_assignments.0.display_name', 'Josep Workflow');

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/assets')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $assetId,
                'owner_label' => 'Josep Workflow',
            ]);

        $risk = $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->postJson('/api/v1/risks', [
                'title' => 'Workflow Risk',
                'category' => 'operations',
                'inherent_score' => 45,
                'residual_score' => 20,
                'linked_asset_id' => $assetId,
                'treatment' => 'Track and reduce workflow exposure.',
                'owner_actor_id' => $actorId,
            ])->assertOk()
            ->assertJsonPath('data.linked_asset_id', $assetId)
            ->json('data');

        $this->assertIsString($risk['id'] ?? null);

        $this->getJson('/api/v1/assets/'.urlencode($assetId).'?'.http_build_query([
            'principal_id' => 'principal-org-b-ops',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
        ]))->assertForbidden();

        $this->patchJson('/api/v1/assets/'.urlencode($assetId), [
            'principal_id' => $principalId,
            'organization_id' => 'org-b',
            'membership_id' => $membershipId,
            'name' => 'Spoofed workflow asset',
            'type' => 'application',
            'criticality' => 'high',
            'classification' => 'restricted',
        ])->assertForbidden();

        $this->postJson('/api/v1/assets', [
            ...$operatorContext,
            'name' => 'Cross owner spoof asset',
            'type' => 'application',
            'criticality' => 'medium',
            'classification' => 'internal',
            'owner_actor_id' => 'actor-operations-control',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertDatabaseMissing('assets', [
            'name' => 'Cross owner spoof asset',
        ]);
    }

    public function test_functional_actor_kinds_metadata_principal_lookup_user_list_and_archive_are_api_visible(): void
    {
        $this->getJson('/api/v1/lookups/functional-actor-kinds/options?'.http_build_query([
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
        ]))
            ->assertOk()
            ->assertJsonFragment(['id' => 'employee', 'label' => 'Employee'])
            ->assertJsonFragment(['id' => 'external-provider', 'label' => 'External provider']);

        $this->postJson('/api/v1/core/functional-actors', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'display_name' => 'Invalid Actor',
            'kind' => '__invalid__',
        ])->assertStatus(422);

        $actor = $this->postJson('/api/v1/core/functional-actors', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'display_name' => 'Maria Archive',
            'kind' => 'employee',
            'metadata' => [
                'email' => 'maria.archive@example.test',
                'role' => 'Conservation',
                'notes' => 'Can be archived without active assignments.',
            ],
        ])->assertOk()
            ->assertJsonPath('data.metadata.role', 'Conservation')
            ->json('data');

        $actorId = (string) $actor['id'];

        $this->getJson('/api/v1/lookups/principals/options?principal_id=principal-admin')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'principal-org-a',
                'label' => 'Ava Mason @ava.mason',
            ]);

        $this->getJson('/api/v1/identity-local/users?'.http_build_query([
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
        ]))->assertOk()
            ->assertJsonFragment(['principal_id' => 'principal-org-a'])
            ->assertJsonMissing(['principal_id' => 'principal-org-b-ops'])
            ->assertJsonMissingPath('data.0.password_hash');

        $this->postJson('/api/v1/core/functional-actors/'.$actorId.'/archive', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
        ])->assertOk()
            ->assertJsonPath('data.archived', true)
            ->assertJsonPath('data.deactivated_assignment_count', 0);

        $this->getJson('/api/v1/lookups/actors/options?'.http_build_query([
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
        ]))->assertOk()
            ->assertJsonMissing(['id' => $actorId]);
    }

    public function test_archiving_actor_with_active_assignments_requires_explicit_assignment_deactivation(): void
    {
        $actor = $this->postJson('/api/v1/core/functional-actors', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'display_name' => 'Pere Assigned',
            'kind' => 'contractor',
        ])->assertOk()
            ->json('data');

        $actorId = (string) $actor['id'];

        $assignment = $this->postJson('/api/v1/core/functional-actors/assignments', [
            'principal_id' => 'principal-admin',
            'actor_id' => $actorId,
            'subject_key' => 'asset::asset-vault-docs',
            'assignment_type' => 'owner',
            'organization_id' => 'org-a',
        ])->assertOk()
            ->json('data');

        $assignmentId = (string) $assignment['id'];

        $this->postJson('/api/v1/core/functional-actors/'.$actorId.'/archive', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertDatabaseHas('functional_assignments', [
            'id' => $assignmentId,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/core/functional-actors/'.$actorId.'/archive', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'deactivate_assignments' => true,
        ])->assertOk()
            ->assertJsonPath('data.archived', true)
            ->assertJsonPath('data.deactivated_assignment_count', 1);

        $this->assertDatabaseHas('functional_actors', [
            'id' => $actorId,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('functional_assignments', [
            'id' => $assignmentId,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'core.functional-actors.actor.archived',
            'target_id' => $actorId,
        ]);
    }
}
