<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PymeSec\Core\Security\ApiAccessTokenRepository;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_profile_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/meta/mcp-server')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'authentication_failed');
    }

    public function test_mcp_profile_endpoint_returns_official_server_metadata_for_authenticated_context(): void
    {
        $issued = $this->app->make(ApiAccessTokenRepository::class)->issue(
            principalId: 'principal-org-a',
            label: 'MCP profile token',
            organizationId: 'org-a',
            scopeId: null,
            createdByPrincipalId: 'principal-admin',
            expiresAt: null,
            abilities: ['plugin.asset-catalog.assets.view'],
        );

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/meta/mcp-server')
            ->assertOk()
            ->assertJsonPath('data.server.binary', 'pymesec-mcp')
            ->assertJsonPath('data.server.transport', 'stdio')
            ->assertJsonPath('data.server.tools.0', 'pymesec_api_request')
            ->assertJsonPath('data.server.tools.1', 'pymesec_call_operation')
            ->assertJsonPath('data.server.tools.2', 'pymesec_list_operations')
            ->assertJsonPath('data.server.autoconfiguration.openapi_url', '/openapi/v1.json')
            ->assertJsonPath('data.server.autoconfiguration.api_base_url_env', 'PYMESEC_API_BASE_URL')
            ->assertJsonPath('data.server.security_model.authentication', 'bearer_api_token')
            ->assertJsonPath('data.context.principal_id', 'principal-org-a')
            ->assertJsonPath('data.context.organization_id', 'org-a');
    }

    public function test_mcp_profile_endpoint_still_respects_token_ability_and_audit_parity(): void
    {
        $issued = $this->app->make(ApiAccessTokenRepository::class)->issue(
            principalId: 'principal-org-a',
            label: 'MCP profile limited token',
            organizationId: 'org-a',
            scopeId: null,
            createdByPrincipalId: 'principal-admin',
            expiresAt: null,
            abilities: ['core.plugins.view'],
        );

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/meta/mcp-server')
            ->assertOk()
            ->assertJsonPath('data.context.principal_id', 'principal-org-a');

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->postJson('/api/v1/assets', [
                'name' => 'Denied by ability',
                'type' => 'application',
                'criticality' => 'high',
                'classification' => 'internal',
                'owner_actor_id' => 'actor-ava-mason',
            ])
            ->assertForbidden()
            ->assertJsonPath('reason', 'token_ability_denied');

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'core.http.request',
            'channel' => 'api',
            'author_type' => 'api_token',
            'author_id' => $issued['id'],
            'status_code' => 403,
        ]);
    }

    public function test_mcp_binary_autoconfiguration_contract_is_exposed_by_api_profile(): void
    {
        $issued = $this->app->make(ApiAccessTokenRepository::class)->issue(
            principalId: 'principal-org-a',
            label: 'MCP metadata token',
            organizationId: 'org-a',
            scopeId: null,
            createdByPrincipalId: 'principal-admin',
            expiresAt: null,
            abilities: ['core.plugins.view'],
        );

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/meta/mcp-server')
            ->assertOk()
            ->assertJsonPath('data.server.autoconfiguration.api_token_env', 'PYMESEC_API_TOKEN')
            ->assertJsonPath('data.server.autoconfiguration.openapi_url_env', 'PYMESEC_OPENAPI_URL')
            ->assertJsonPath('data.server.autoconfiguration.api_prefix_env', 'PYMESEC_API_PREFIX');
    }
}
