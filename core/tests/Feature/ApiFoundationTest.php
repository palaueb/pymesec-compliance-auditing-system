<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_authentication_context(): void
    {
        $this->getJson('/api/v1/meta/capabilities')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'authentication_failed');
    }

    public function test_api_capabilities_and_lookups_work_for_authenticated_principal(): void
    {
        $query = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_ids' => ['membership-org-a-hello'],
        ];

        $this->getJson('/api/v1/meta/capabilities?'.http_build_query($query))
            ->assertOk()
            ->assertJsonPath('data.principal_id', 'principal-org-a')
            ->assertJsonPath('data.organization_id', 'org-a');

        $this->getJson('/api/v1/lookups/reference-catalogs?'.http_build_query($query))
            ->assertOk()
            ->assertJsonFragment(['key' => 'assets.types']);

        $this->getJson('/api/v1/lookups/reference-catalogs/assets.types/options?'.http_build_query($query))
            ->assertOk()
            ->assertJsonPath('data.catalog_key', 'assets.types')
            ->assertJsonFragment(['id' => 'application', 'label' => 'Application']);
    }

    public function test_asset_api_uses_governed_lookup_values_for_writes(): void
    {
        $base = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->postJson('/api/v1/assets', [
            ...$base,
            'name' => 'Invalid asset',
            'type' => 'invalid-type',
            'criticality' => 'high',
            'classification' => 'internal',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $create = $this->postJson('/api/v1/assets', [
            ...$base,
            'name' => 'API Asset',
            'type' => 'application',
            'criticality' => 'high',
            'classification' => 'internal',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk()
            ->assertJsonPath('data.name', 'API Asset');

        $assetId = (string) $create->json('data.id');

        $this->patchJson('/api/v1/assets/'.$assetId, [
            ...$base,
            'name' => 'API Asset Updated',
            'type' => 'application',
            'criticality' => 'high',
            'classification' => 'bad-classification',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->patchJson('/api/v1/assets/'.$assetId, [
            ...$base,
            'name' => 'API Asset Updated',
            'type' => 'application',
            'criticality' => 'medium',
            'classification' => 'restricted',
        ])->assertOk()
            ->assertJsonPath('data.name', 'API Asset Updated');
    }

    public function test_risk_api_uses_governed_lookup_values_for_writes(): void
    {
        $base = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->postJson('/api/v1/risks', [
            ...$base,
            'title' => 'Invalid risk',
            'category' => 'invalid-category',
            'inherent_score' => 40,
            'residual_score' => 20,
            'treatment' => 'Example treatment',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $create = $this->postJson('/api/v1/risks', [
            ...$base,
            'title' => 'API Risk',
            'category' => 'operations',
            'inherent_score' => 40,
            'residual_score' => 20,
            'treatment' => 'Example treatment',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk()
            ->assertJsonPath('data.title', 'API Risk');

        $riskId = (string) $create->json('data.id');

        $this->patchJson('/api/v1/risks/'.$riskId, [
            ...$base,
            'title' => 'API Risk Updated',
            'category' => 'operations',
            'inherent_score' => 38,
            'residual_score' => 18,
            'treatment' => 'Updated treatment',
        ])->assertOk()
            ->assertJsonPath('data.title', 'API Risk Updated');
    }

    public function test_findings_api_uses_governed_lookup_values_for_writes(): void
    {
        $base = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->postJson('/api/v1/findings', [
            ...$base,
            'title' => 'Invalid finding',
            'severity' => 'invalid-severity',
            'description' => 'Invalid severity payload',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $create = $this->postJson('/api/v1/findings', [
            ...$base,
            'title' => 'API Finding',
            'severity' => 'high',
            'description' => 'Finding created via API',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk()
            ->assertJsonPath('data.title', 'API Finding');

        $findingId = (string) $create->json('data.id');

        $this->patchJson('/api/v1/findings/'.$findingId, [
            ...$base,
            'title' => 'API Finding Updated',
            'severity' => 'critical',
            'description' => 'Updated finding description',
        ])->assertOk()
            ->assertJsonPath('data.title', 'API Finding Updated')
            ->assertJsonPath('data.severity', 'critical');
    }

    public function test_controls_and_assessments_api_use_contract_rules_and_authz(): void
    {
        $base = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
        ];

        $controlCreate = $this->postJson('/api/v1/controls', [
            ...$base,
            'name' => 'API Control',
            'framework' => 'Internal Security',
            'domain' => 'identity',
            'evidence' => 'Control evidence notes',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk()
            ->assertJsonPath('data.name', 'API Control');

        $controlId = (string) $controlCreate->json('data.id');

        $this->patchJson('/api/v1/controls/'.$controlId, [
            ...$base,
            'name' => 'API Control Updated',
            'framework' => 'Internal Security',
            'domain' => 'identity',
            'evidence' => 'Updated control evidence',
        ])->assertOk()
            ->assertJsonPath('data.name', 'API Control Updated');

        $assessmentCreate = $this->postJson('/api/v1/assessments', [
            ...$base,
            'title' => 'API Assessment',
            'summary' => 'Assessment created via API',
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'draft',
            'control_ids' => [$controlId],
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk()
            ->assertJsonPath('data.title', 'API Assessment');

        $assessmentId = (string) $assessmentCreate->json('data.id');

        $this->patchJson('/api/v1/assessments/'.$assessmentId, [
            ...$base,
            'title' => 'API Assessment Updated',
            'summary' => 'Assessment updated via API',
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'active',
            'control_ids' => [$controlId],
        ])->assertOk()
            ->assertJsonPath('data.title', 'API Assessment Updated')
            ->assertJsonPath('data.status', 'active');

        $this->patchJson('/api/v1/assessments/'.$assessmentId.'/reviews/'.$controlId, [
            ...$base,
            'result' => 'invalid-result',
            'test_notes' => 'Invalid review result',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->patchJson('/api/v1/assessments/'.$assessmentId.'/reviews/'.$controlId, [
            ...$base,
            'result' => 'pass',
            'test_notes' => 'Control test passed',
            'conclusion' => 'Ready for sign-off',
            'reviewed_on' => '2026-04-10',
        ])->assertOk()
            ->assertJsonPath('data.result', 'pass');
    }

    public function test_remediation_actions_api_use_contract_rules_and_governed_values(): void
    {
        $base = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
        ];

        $finding = $this->postJson('/api/v1/findings', [
            ...$base,
            'title' => 'Finding for actions',
            'severity' => 'medium',
            'description' => 'Used to test action API',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk();

        $findingId = (string) $finding->json('data.id');

        $this->postJson('/api/v1/findings/'.$findingId.'/actions', [
            ...$base,
            'title' => 'Invalid action',
            'status' => 'invalid-status',
            'notes' => 'Should fail',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $createAction = $this->postJson('/api/v1/findings/'.$findingId.'/actions', [
            ...$base,
            'title' => 'Investigate root cause',
            'status' => 'planned',
            'notes' => 'First response action',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Investigate root cause');

        $actionId = (string) $createAction->json('data.id');

        $this->patchJson('/api/v1/remediation-actions/'.$actionId, [
            ...$base,
            'title' => 'Investigate and fix root cause',
            'status' => 'in-progress',
            'notes' => 'Execution started',
        ])->assertOk()
            ->assertJsonPath('data.status', 'in-progress');
    }

    public function test_openapi_endpoint_and_generation_command_work(): void
    {
        $response = $this->get('/openapi.json')
            ->assertOk()
            ->assertSee('coreGetCapabilities')
            ->assertSee('assetCatalogListAssets')
            ->assertSee('riskManagementListRisks')
            ->assertSee('controlsCatalogListControls')
            ->assertSee('assessmentsAuditsListAssessments')
            ->assertSee('assessmentsAuditsUpdateAssessmentReview')
            ->assertSee('findingsRemediationListFindings')
            ->assertSee('findingsRemediationCreateAction')
            ->assertSee('findingsRemediationUpdateAction');

        $openApi = $response->json();
        $this->assertIsArray($openApi);
        $this->assertSame(
            'object',
            data_get($openApi, 'paths./assets.post.requestBody.content.application/json.schema.type'),
        );
        $this->assertSame(
            'assets.types',
            data_get($openApi, 'paths./assets.post.requestBody.content.application/json.schema.properties.type.x-governed-catalog'),
        );
        $this->assertContains(
            'name',
            data_get($openApi, 'paths./assets.post.requestBody.content.application/json.schema.required', []),
        );
        $this->assertContains(
            'array',
            (array) data_get($openApi, 'paths./assessments.post.requestBody.content.application/json.schema.properties.control_ids.type'),
        );
        $this->assertSame(
            'string',
            data_get($openApi, 'paths./assessments.post.requestBody.content.application/json.schema.properties.control_ids.items.type'),
        );
        $this->assertSame(
            'assessments.status',
            data_get($openApi, 'paths./assessments.post.requestBody.content.application/json.schema.properties.status.x-governed-catalog'),
        );
        $this->assertSame(
            'findings.severity',
            data_get($openApi, 'paths./findings.post.requestBody.content.application/json.schema.properties.severity.x-governed-catalog'),
        );
        $this->assertSame(
            'findings.remediation_status',
            data_get($openApi, 'paths./findings/{findingId}/actions.post.requestBody.content.application/json.schema.properties.status.x-governed-catalog'),
        );
        $this->assertSame(
            'assessments.review_result',
            data_get($openApi, 'paths./assessments/{assessmentId}/reviews/{controlId}.patch.requestBody.content.application/json.schema.properties.result.x-governed-catalog'),
        );

        $output = base_path('storage/framework/testing/openapi.test.json');
        File::delete($output);

        $this->artisan('openapi:generate', ['--output' => $output])
            ->assertExitCode(0);

        $this->assertFileExists($output);
        $this->assertStringContainsString('assetCatalogListAssets', (string) File::get($output));
    }

    public function test_every_api_v1_route_declares_required_openapi_metadata(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');

        foreach ($router->getRoutes() as $route) {
            $uri = $route->uri();

            if (! is_string($uri) || ! str_starts_with($uri, 'api/v1')) {
                continue;
            }

            $metadata = $route->defaults['_openapi'] ?? null;
            $this->assertIsArray($metadata, sprintf('Route [%s] must define _openapi metadata.', $uri));

            foreach (['operation_id', 'tags', 'summary', 'responses'] as $requiredKey) {
                $this->assertArrayHasKey(
                    $requiredKey,
                    $metadata,
                    sprintf('Route [%s] is missing required _openapi key [%s].', $uri, $requiredKey),
                );
            }

            $routeMethods = array_map('strtoupper', $route->methods());
            $hasWriteMethod = count(array_intersect($routeMethods, ['POST', 'PUT', 'PATCH'])) > 0;

            if ($hasWriteMethod) {
                $hasRequestContract = (
                    is_array($metadata['request_body'] ?? null)
                    || is_array($metadata['request_rules'] ?? null)
                    || (is_string($metadata['request_form_request'] ?? null) && $metadata['request_form_request'] !== '')
                );

                $this->assertTrue(
                    $hasRequestContract,
                    sprintf('Write route [%s] must declare request_body, request_rules, or request_form_request.', $uri),
                );
            }
        }
    }

    public function test_every_api_v1_route_operation_is_present_in_generated_openapi_paths(): void
    {
        $openApi = $this->get('/openapi.json')->assertOk()->json();
        $paths = is_array($openApi['paths'] ?? null) ? $openApi['paths'] : [];

        /** @var Router $router */
        $router = $this->app->make('router');

        foreach ($router->getRoutes() as $route) {
            $uri = $route->uri();

            if (! is_string($uri) || ! str_starts_with($uri, 'api/v1')) {
                continue;
            }

            $path = $this->normalizeApiRoutePath($uri);

            foreach ($route->methods() as $method) {
                $normalizedMethod = strtolower((string) $method);

                if (in_array($normalizedMethod, ['head', 'options'], true)) {
                    continue;
                }

                $this->assertArrayHasKey(
                    $path,
                    $paths,
                    sprintf('OpenAPI document is missing route path [%s %s].', strtoupper($normalizedMethod), $path),
                );

                $this->assertArrayHasKey(
                    $normalizedMethod,
                    $paths[$path],
                    sprintf('OpenAPI document is missing operation [%s %s].', strtoupper($normalizedMethod), $path),
                );
            }
        }
    }

    public function test_product_web_write_routes_have_corresponding_api_operations_in_openapi(): void
    {
        $openApi = $this->get('/openapi.json')->assertOk()->json();
        $operationIds = $this->collectOpenApiOperationIds($openApi);

        /** @var Router $router */
        $router = $this->app->make('router');

        $parityMatrix = [
            'plugin.asset-catalog.store' => 'assetCatalogCreateAsset',
            'plugin.asset-catalog.update' => 'assetCatalogUpdateAsset',
            'plugin.risk-management.store' => 'riskManagementCreateRisk',
            'plugin.risk-management.update' => 'riskManagementUpdateRisk',
            'plugin.controls-catalog.store' => 'controlsCatalogCreateControl',
            'plugin.controls-catalog.update' => 'controlsCatalogUpdateControl',
            'plugin.assessments-audits.store' => 'assessmentsAuditsCreateAssessment',
            'plugin.assessments-audits.update' => 'assessmentsAuditsUpdateAssessment',
            'plugin.assessments-audits.reviews.update' => 'assessmentsAuditsUpdateAssessmentReview',
            'plugin.findings-remediation.store' => 'findingsRemediationCreateFinding',
            'plugin.findings-remediation.update' => 'findingsRemediationUpdateFinding',
            'plugin.findings-remediation.actions.store' => 'findingsRemediationCreateAction',
            'plugin.findings-remediation.actions.update' => 'findingsRemediationUpdateAction',
        ];

        foreach ($parityMatrix as $webRouteName => $apiOperationId) {
            $this->assertNotNull(
                $router->getRoutes()->getByName($webRouteName),
                sprintf('WEB route [%s] must exist for parity checks.', $webRouteName),
            );

            $this->assertContains(
                $apiOperationId,
                $operationIds,
                sprintf('WEB route [%s] is missing API parity operation [%s].', $webRouteName, $apiOperationId),
            );
        }
    }

    public function test_write_contract_relation_fields_are_mapped_to_lookup_sources_in_openapi(): void
    {
        $openApi = $this->get('/openapi.json')->assertOk()->json();
        $paths = is_array($openApi['paths'] ?? null) ? $openApi['paths'] : [];

        foreach ($paths as $path => $operations) {
            if (! is_string($path) || ! is_array($operations)) {
                continue;
            }

            foreach ($operations as $method => $operation) {
                if (! is_string($method) || ! in_array(strtolower($method), ['post', 'put', 'patch'], true) || ! is_array($operation)) {
                    continue;
                }

                $properties = data_get($operation, 'requestBody.content.application/json.schema.properties', []);
                if (! is_array($properties)) {
                    continue;
                }

                $lookupFields = is_array($operation['x-lookup-fields'] ?? null) ? $operation['x-lookup-fields'] : [];

                foreach ($properties as $field => $schema) {
                    if (! is_string($field) || ! is_array($schema)) {
                        continue;
                    }

                    if (
                        in_array($field, ['organization_id', 'scope_id', 'principal_id', 'membership_id', 'membership_ids'], true)
                        || ! $this->fieldRequiresLookupSource($field)
                        || is_string($schema['x-governed-catalog'] ?? null)
                    ) {
                        continue;
                    }

                    $lookupSource = data_get($lookupFields, $field.'.source');

                    $this->assertIsString(
                        $lookupSource,
                        sprintf('OpenAPI operation [%s %s] must declare x-lookup-fields source for [%s].', strtoupper((string) $method), $path, $field),
                    );
                    $this->assertNotSame('', trim((string) $lookupSource));

                    $lookupPath = $this->normalizeLookupSourceToOpenApiPath((string) $lookupSource);
                    $this->assertArrayHasKey(
                        $lookupPath,
                        $paths,
                        sprintf('Lookup source [%s] referenced by [%s %s] was not found in OpenAPI paths.', $lookupSource, strtoupper((string) $method), $path),
                    );
                    $this->assertArrayHasKey(
                        'get',
                        $paths[$lookupPath],
                        sprintf('Lookup source [%s] must reference a GET endpoint.', $lookupSource),
                    );
                }
            }
        }
    }

    public function test_lookup_option_endpoints_cover_dynamic_write_fields(): void
    {
        $query = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'membership_ids' => ['membership-org-a-hello'],
        ];

        $this->getJson('/api/v1/lookups/actors/options?'.http_build_query($query))
            ->assertOk()
            ->assertJsonFragment(['id' => 'actor-ava-mason']);

        $this->getJson('/api/v1/lookups/frameworks/options?'.http_build_query($query))
            ->assertOk()
            ->assertJsonFragment(['id' => 'framework-iso-27001']);

        $this->getJson('/api/v1/lookups/controls/options?'.http_build_query($query))
            ->assertOk()
            ->assertJsonFragment(['id' => 'control-access-review']);

        $this->getJson('/api/v1/lookups/risks/options?'.http_build_query($query))
            ->assertOk()
            ->assertJsonFragment(['id' => 'risk-access-drift']);
    }

    /**
     * @param  array<string, mixed>  $openApi
     * @return array<int, string>
     */
    private function collectOpenApiOperationIds(array $openApi): array
    {
        $operationIds = [];
        $paths = is_array($openApi['paths'] ?? null) ? $openApi['paths'] : [];

        foreach ($paths as $operations) {
            if (! is_array($operations)) {
                continue;
            }

            foreach ($operations as $operation) {
                if (! is_array($operation)) {
                    continue;
                }

                $operationId = $operation['operationId'] ?? null;
                if (is_string($operationId) && $operationId !== '') {
                    $operationIds[] = $operationId;
                }
            }
        }

        return array_values(array_unique($operationIds));
    }

    private function normalizeApiRoutePath(string $uri): string
    {
        $withoutPrefix = substr($uri, strlen('api/v1'));
        $path = '/'.ltrim(is_string($withoutPrefix) ? $withoutPrefix : '', '/');

        return $path === '//' ? '/' : $path;
    }

    private function normalizeLookupSourceToOpenApiPath(string $source): string
    {
        if (str_starts_with($source, '/api/v1')) {
            $source = substr($source, strlen('/api/v1'));
        }

        $path = '/'.ltrim($source, '/');

        return $path === '//' ? '/' : $path;
    }

    private function fieldRequiresLookupSource(string $field): bool
    {
        return str_ends_with($field, '_id') || str_ends_with($field, '_ids');
    }
}
