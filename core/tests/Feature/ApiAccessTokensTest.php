<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PymeSec\Core\Security\ApiAccessTokenRepository;
use Tests\TestCase;

class ApiAccessTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_token_admin_routes_require_platform_permissions(): void
    {
        $this->get('/core/api-tokens?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertForbidden();

        $this->post('/core/api-tokens', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'menu' => 'core.api-tokens',
            'locale' => 'en',
            'owner_principal_id' => 'principal-org-a',
            'label' => 'Viewer token',
            'expires_in_days' => 30,
            'membership_ids' => ['membership-org-a-hello'],
        ])->assertForbidden();
    }

    public function test_platform_admin_can_issue_and_revoke_tokens_from_admin_screen(): void
    {
        $response = $this->post('/core/api-tokens', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'menu' => 'core.api-tokens',
            'locale' => 'en',
            'owner_principal_id' => 'principal-org-a',
            'label' => 'Operations integration token',
            'expires_in_days' => 90,
            'abilities' => 'plugin.asset-catalog.assets.view plugin.risk-management.risks.view plugin.findings-remediation.findings.manage',
        ]);

        $response->assertFound()
            ->assertSessionHas('status')
            ->assertSessionHas('api_token_issued');

        $record = DB::table('api_access_tokens')
            ->where('label', 'Operations integration token')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('principal-org-a', $record->principal_id);
        $this->assertSame('org-a', $record->organization_id);
        $this->assertSame('scope-eu', $record->scope_id);
        $this->assertSame('principal-admin', $record->created_by_principal_id);
        $this->assertSame([
            'plugin.asset-catalog.assets.view',
            'plugin.risk-management.risks.view',
            'plugin.findings-remediation.findings.manage',
        ], json_decode((string) $record->abilities, true));

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'core.api-tokens.issued',
            'target_type' => 'api-access-token',
            'target_id' => $record->id,
        ]);

        $this->post('/core/api-tokens/'.$record->id.'/revoke', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'menu' => 'core.api-tokens',
            'locale' => 'en',
            'owner_principal_id' => 'principal-org-a',
        ])->assertFound()->assertSessionHas('status');

        $this->assertNotNull(DB::table('api_access_tokens')->where('id', $record->id)->value('revoked_at'));

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'core.api-tokens.revoked',
            'target_type' => 'api-access-token',
            'target_id' => $record->id,
        ]);
    }

    public function test_platform_admin_can_manage_tokens_via_api_v1_endpoints(): void
    {
        $issueResponse = $this->postJson('/api/v1/api-tokens', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'owner_principal_id' => 'principal-org-a',
            'label' => 'API v1 managed token',
            'expires_in_days' => 90,
            'abilities' => [
                'plugin.asset-catalog.assets.view',
                'plugin.risk-management.risks.view',
            ],
        ])->assertOk();

        $issued = $issueResponse->json('data');
        $this->assertIsArray($issued);
        $this->assertIsString($issued['id'] ?? null);
        $this->assertIsString($issued['token'] ?? null);

        $tokenId = (string) $issued['id'];
        $oldToken = (string) $issued['token'];

        $this->getJson('/api/v1/api-tokens?'.http_build_query([
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'owner_principal_id' => 'principal-org-a',
        ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $tokenId]);

        $rotateResponse = $this->postJson('/api/v1/api-tokens/'.$tokenId.'/rotate', [
            'principal_id' => 'principal-admin',
        ])->assertOk();

        $rotated = $rotateResponse->json('data');
        $this->assertIsArray($rotated);
        $this->assertSame($tokenId, $rotated['id'] ?? null);
        $this->assertIsString($rotated['token'] ?? null);
        $this->assertNotSame($oldToken, $rotated['token']);

        $this->withHeader('Authorization', 'Bearer '.$oldToken)
            ->getJson('/api/v1/meta/capabilities')
            ->assertUnauthorized()
            ->assertJsonPath('error.reason', 'api_token_invalid_or_expired');

        $this->postJson('/api/v1/api-tokens/'.$tokenId.'/revoke', [
            'principal_id' => 'principal-admin',
        ])->assertOk()
            ->assertJsonPath('data.id', $tokenId)
            ->assertJsonPath('data.revoked', true);

        $this->withHeader('Authorization', 'Bearer '.(string) $rotated['token'])
            ->getJson('/api/v1/meta/capabilities')
            ->assertUnauthorized()
            ->assertJsonPath('error.reason', 'api_token_invalid_or_expired');

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'core.api-tokens.issued',
            'target_type' => 'api-access-token',
            'target_id' => $tokenId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'core.api-tokens.rotated',
            'target_type' => 'api-access-token',
            'target_id' => $tokenId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'core.api-tokens.revoked',
            'target_type' => 'api-access-token',
            'target_id' => $tokenId,
        ]);
    }

    public function test_bearer_token_authenticates_api_requests_and_sets_audit_author(): void
    {
        $issued = $this->app->make(ApiAccessTokenRepository::class)->issue(
            principalId: 'principal-org-a',
            label: 'Capabilities token',
            organizationId: 'org-a',
            scopeId: null,
            createdByPrincipalId: 'principal-admin',
            expiresAt: null,
            abilities: ['meta.read'],
        );

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/meta/capabilities')
            ->assertOk()
            ->assertJsonPath('data.principal_id', 'principal-org-a')
            ->assertJsonPath('data.organization_id', 'org-a');

        $this->assertNotNull(DB::table('api_access_tokens')->where('id', $issued['id'])->value('last_used_at'));

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'core.http.request',
            'channel' => 'api',
            'author_type' => 'api_token',
            'author_id' => $issued['id'],
            'status_code' => 200,
        ]);
    }

    public function test_non_platform_token_manager_cannot_issue_tokens_for_other_principals(): void
    {
        DB::table('authorization_grants')->insert([
            'id' => 'grant-api-token-manage-principal-org-a',
            'target_type' => 'principal',
            'target_id' => 'principal-org-a',
            'grant_type' => 'permission',
            'value' => 'core.api-tokens.manage',
            'context_type' => 'platform',
            'organization_id' => null,
            'scope_id' => null,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/core/api-tokens', [
            'principal_id' => 'principal-org-a',
            'menu' => 'core.api-tokens',
            'locale' => 'en',
            'owner_principal_id' => 'principal-admin',
            'label' => 'Escalation attempt token',
            'expires_in_days' => 30,
        ])->assertForbidden();
    }

    public function test_non_platform_token_manager_cannot_rotate_or_revoke_other_principal_tokens(): void
    {
        DB::table('authorization_grants')->insert([
            'id' => 'grant-api-token-manage-principal-org-a-2',
            'target_type' => 'principal',
            'target_id' => 'principal-org-a',
            'grant_type' => 'permission',
            'value' => 'core.api-tokens.manage',
            'context_type' => 'platform',
            'organization_id' => null,
            'scope_id' => null,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $issued = $this->app->make(ApiAccessTokenRepository::class)->issue(
            principalId: 'principal-admin',
            label: 'Admin token cannot be managed by org user',
            organizationId: null,
            scopeId: null,
            createdByPrincipalId: 'principal-admin',
            expiresAt: null,
            abilities: ['core.plugins.view'],
        );

        $this->post('/core/api-tokens/'.$issued['id'].'/rotate', [
            'principal_id' => 'principal-org-a',
            'menu' => 'core.api-tokens',
            'locale' => 'en',
        ])->assertForbidden();

        $this->post('/core/api-tokens/'.$issued['id'].'/revoke', [
            'principal_id' => 'principal-org-a',
            'menu' => 'core.api-tokens',
            'locale' => 'en',
        ])->assertForbidden();
    }

    public function test_non_platform_token_manager_cannot_manage_other_principal_tokens_via_api_v1(): void
    {
        DB::table('authorization_grants')->insert([
            'id' => 'grant-api-token-manage-principal-org-a-api-v1',
            'target_type' => 'principal',
            'target_id' => 'principal-org-a',
            'grant_type' => 'permission',
            'value' => 'core.api-tokens.manage',
            'context_type' => 'platform',
            'organization_id' => null,
            'scope_id' => null,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $issued = $this->app->make(ApiAccessTokenRepository::class)->issue(
            principalId: 'principal-admin',
            label: 'Admin token cannot be managed by org user (api)',
            organizationId: null,
            scopeId: null,
            createdByPrincipalId: 'principal-admin',
            expiresAt: null,
            abilities: ['core.plugins.view'],
        );

        $this->postJson('/api/v1/api-tokens', [
            'principal_id' => 'principal-org-a',
            'owner_principal_id' => 'principal-admin',
            'label' => 'Escalation attempt token api',
            'expires_in_days' => 30,
        ])->assertForbidden();

        $this->postJson('/api/v1/api-tokens/'.$issued['id'].'/rotate', [
            'principal_id' => 'principal-org-a',
        ])->assertForbidden();

        $this->postJson('/api/v1/api-tokens/'.$issued['id'].'/revoke', [
            'principal_id' => 'principal-org-a',
        ])->assertForbidden();
    }

    public function test_non_platform_token_manager_cannot_issue_api_token_with_abilities_outside_owner_permissions(): void
    {
        DB::table('authorization_grants')->insert([
            'id' => 'grant-api-token-manage-principal-org-a-overreach',
            'target_type' => 'principal',
            'target_id' => 'principal-org-a',
            'grant_type' => 'permission',
            'value' => 'core.api-tokens.manage',
            'context_type' => 'platform',
            'organization_id' => null,
            'scope_id' => null,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/api-tokens', [
            'principal_id' => 'principal-org-a',
            'owner_principal_id' => 'principal-org-a',
            'label' => 'Overreach token attempt',
            'abilities' => ['core.roles.manage'],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertDatabaseMissing('api_access_tokens', [
            'label' => 'Overreach token attempt',
        ]);
    }

    public function test_non_platform_token_manager_can_issue_self_token_without_explicit_abilities_and_get_effective_default(): void
    {
        DB::table('authorization_grants')->insert([
            'id' => 'grant-api-token-manage-principal-org-a-defaults',
            'target_type' => 'principal',
            'target_id' => 'principal-org-a',
            'grant_type' => 'permission',
            'value' => 'core.api-tokens.manage',
            'context_type' => 'platform',
            'organization_id' => null,
            'scope_id' => null,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/api-tokens', [
            'principal_id' => 'principal-org-a',
            'owner_principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'label' => 'Implicit abilities token',
            'expires_in_days' => 30,
        ])->assertOk();

        $abilities = $response->json('data.abilities');
        $this->assertIsArray($abilities);
        $this->assertGreaterThan(0, count($abilities));
    }

    public function test_non_platform_viewer_list_is_scoped_to_own_tokens_in_api_v1(): void
    {
        DB::table('authorization_grants')->insert([
            'id' => 'grant-api-token-view-principal-org-a-api-v1',
            'target_type' => 'principal',
            'target_id' => 'principal-org-a',
            'grant_type' => 'permission',
            'value' => 'core.api-tokens.view',
            'context_type' => 'platform',
            'organization_id' => null,
            'scope_id' => null,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app->make(ApiAccessTokenRepository::class)->issue(
            principalId: 'principal-org-a',
            label: 'Owned by org user',
            organizationId: 'org-a',
            scopeId: null,
            createdByPrincipalId: 'principal-admin',
            expiresAt: null,
            abilities: ['plugin.asset-catalog.assets.view'],
        );

        $this->app->make(ApiAccessTokenRepository::class)->issue(
            principalId: 'principal-admin',
            label: 'Owned by admin',
            organizationId: null,
            scopeId: null,
            createdByPrincipalId: 'principal-admin',
            expiresAt: null,
            abilities: ['core.plugins.view'],
        );

        $list = $this->getJson('/api/v1/api-tokens?'.http_build_query([
            'principal_id' => 'principal-org-a',
        ]))
            ->assertOk()
            ->json('data');

        $this->assertIsArray($list);
        $owners = array_values(array_unique(array_map(
            static fn (array $row): string => (string) ($row['principal_id'] ?? ''),
            $list,
        )));
        $this->assertContains('principal-org-a', $owners);
        $this->assertNotContains('principal-admin', $owners);

        $this->getJson('/api/v1/api-tokens?'.http_build_query([
            'principal_id' => 'principal-org-a',
            'owner_principal_id' => 'principal-admin',
        ]))->assertForbidden();
    }

    public function test_token_rotation_invalidates_old_secret_and_returns_new_one_time_secret(): void
    {
        $issued = $this->app->make(ApiAccessTokenRepository::class)->issue(
            principalId: 'principal-org-a',
            label: 'Rotation token',
            organizationId: 'org-a',
            scopeId: null,
            createdByPrincipalId: 'principal-admin',
            expiresAt: null,
            abilities: ['plugin.asset-catalog.assets.view'],
        );

        $response = $this->post('/core/api-tokens/'.$issued['id'].'/rotate', [
            'principal_id' => 'principal-admin',
            'menu' => 'core.api-tokens',
            'locale' => 'en',
            'owner_principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
        ])->assertFound()->assertSessionHas('api_token_issued');

        $rotated = $response->baseResponse->getSession()->get('api_token_issued');
        $this->assertIsArray($rotated);
        $this->assertSame($issued['id'], $rotated['id']);
        $this->assertNotSame($issued['token'], $rotated['token']);

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/meta/capabilities')
            ->assertUnauthorized()
            ->assertJsonPath('error.reason', 'api_token_invalid_or_expired');

        $this->withHeader('Authorization', 'Bearer '.$rotated['token'])
            ->getJson('/api/v1/assets')
            ->assertOk();
    }

    public function test_token_abilities_are_enforced_on_permission_guarded_api_routes(): void
    {
        $issued = $this->app->make(ApiAccessTokenRepository::class)->issue(
            principalId: 'principal-org-a',
            label: 'View-only assets token',
            organizationId: 'org-a',
            scopeId: null,
            createdByPrincipalId: 'principal-admin',
            expiresAt: null,
            abilities: ['plugin.asset-catalog.assets.view'],
        );

        $capabilities = $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/meta/capabilities')
            ->assertOk()
            ->assertJsonPath('data.principal_id', 'principal-org-a')
            ->json('data.permissions');

        $this->assertIsArray($capabilities);
        $this->assertContains('plugin.asset-catalog.assets.view', $capabilities);
        $this->assertNotContains('plugin.asset-catalog.assets.manage', $capabilities);

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/assets')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->postJson('/api/v1/assets', [
                'name' => 'Token denied asset',
                'type' => 'application',
                'criticality' => 'high',
                'classification' => 'internal',
            ])
            ->assertForbidden()
            ->assertJsonPath('reason', 'token_ability_denied');
    }
}
