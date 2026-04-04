<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutomationCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_automation_catalog_route_requires_view_permission(): void
    {
        $this->get('/plugins/automation-catalog?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'automation-pack-aws-config-baseline',
                'pack_key' => 'connector.aws.config-baseline',
            ]);

        $this->get('/plugins/automation-catalog?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_automation_catalog_screen_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.automation-catalog.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Automation Catalog')
            ->assertSee('Automation packs define installable compliance automations.')
            ->assertSee('Automation pack catalog')
            ->assertSee('AWS Config Baseline Collector')
            ->assertSee('Entra ID Joiner-Mover-Leaver Sync')
            ->assertSee('Register automation pack');

        $this->get('/app?menu=plugin.automation-catalog.root&pack_id=automation-pack-entra-joiner-mover-leaver&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Automation pack')
            ->assertSee('connector.microsoft.entra-jml')
            ->assertSee('Degraded')
            ->assertSee('Rate limit from upstream directory API on full sync.');
    }

    public function test_automation_packs_can_be_registered_and_lifecycle_managed(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'menu' => 'plugin.automation-catalog.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/automation-catalog', [
            ...$payload,
            'pack_key' => 'connector.google.workspace-baseline',
            'name' => 'Google Workspace Baseline Collector',
            'summary' => 'Collects admin baseline controls for account governance evidence refresh.',
            'version' => '0.1.0',
            'provider_type' => 'community',
            'source_ref' => 'https://github.com/pymesec/automation-pack-google-workspace-baseline',
            'provenance_type' => 'git',
        ])->assertFound();

        $packId = (string) DB::table('automation_packs')
            ->where('organization_id', 'org-a')
            ->where('scope_id', 'scope-eu')
            ->where('pack_key', 'connector.google.workspace-baseline')
            ->value('id');

        $this->assertNotSame('', $packId);
        $this->assertDatabaseHas('automation_packs', [
            'id' => $packId,
            'lifecycle_state' => 'discovered',
            'is_installed' => false,
            'is_enabled' => false,
        ]);

        $this->post("/plugins/automation-catalog/{$packId}/install", $payload)->assertFound();
        $this->post("/plugins/automation-catalog/{$packId}/enable", $payload)->assertFound();
        $this->post("/plugins/automation-catalog/{$packId}/health", [
            ...$payload,
            'health_state' => 'failing',
            'last_failure_reason' => 'Connector token rejected by upstream API.',
        ])->assertFound();
        $this->post("/plugins/automation-catalog/{$packId}/disable", $payload)->assertFound();

        $this->assertDatabaseHas('automation_packs', [
            'id' => $packId,
            'lifecycle_state' => 'disabled',
            'is_installed' => true,
            'is_enabled' => false,
            'health_state' => 'failing',
            'last_failure_reason' => 'Connector token rejected by upstream API.',
        ]);
    }

    public function test_output_mappings_can_apply_evidence_refresh_and_workflow_transition(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'menu' => 'plugin.automation-catalog.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/output-mappings', [
            ...$payload,
            'mapping_label' => 'Control review automation trigger',
            'mapping_kind' => 'workflow-transition',
            'target_subject_type' => 'control',
            'target_subject_id' => 'control-access-review',
            'workflow_key' => 'plugin.controls-catalog.control-lifecycle',
            'transition_key' => 'submit-review',
            'is_active' => '1',
        ])->assertFound();

        $workflowMappingId = (string) DB::table('automation_pack_output_mappings')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('mapping_label', 'Control review automation trigger')
            ->value('id');

        $this->assertNotSame('', $workflowMappingId);

        $this->post("/plugins/automation-catalog/automation-pack-aws-config-baseline/output-mappings/{$workflowMappingId}/apply", $payload)
            ->assertFound();

        $this->assertDatabaseHas('workflow_instances', [
            'workflow_key' => 'plugin.controls-catalog.control-lifecycle',
            'subject_type' => 'control',
            'subject_id' => 'control-access-review',
            'organization_id' => 'org-a',
            'current_state' => 'review',
        ]);

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/output-mappings/automation-output-map-aws-evidence-refresh/apply', [
            ...$payload,
            'evidence_kind' => 'report',
            'output_file' => UploadedFile::fake()->create('aws-output.txt', 12, 'text/plain'),
        ])->assertFound();

        $artifactId = (string) DB::table('artifacts')
            ->where('owner_component', 'automation-catalog')
            ->where('subject_type', 'control')
            ->where('subject_id', 'control-access-review')
            ->orderByDesc('created_at')
            ->value('id');

        $this->assertNotSame('', $artifactId);
        $this->assertDatabaseHas('evidence_records', [
            'organization_id' => 'org-a',
            'artifact_id' => $artifactId,
        ]);
        $this->assertDatabaseHas('evidence_record_links', [
            'evidence_id' => (string) DB::table('evidence_records')->where('artifact_id', $artifactId)->value('id'),
            'domain_type' => 'control',
            'domain_id' => 'control-access-review',
        ]);
        $this->assertDatabaseHas('automation_pack_output_mappings', [
            'id' => 'automation-output-map-aws-evidence-refresh',
            'last_status' => 'success',
        ]);
    }

    public function test_external_repository_can_be_registered_and_refreshed_with_valid_signature(): void
    {
        if (! function_exists('openssl_sign')) {
            $this->markTestSkipped('OpenSSL extension is required for repository signature tests.');
        }

        [$privateKeyPem, $publicKeyPem] = $this->buildKeyPair();

        $repositoryJson = json_encode([
            'repository' => [
                'id' => 'pymesec-community',
                'name' => 'PymeSec Community',
            ],
            'packs' => [
                [
                    'id' => 'utility.hello-world',
                    'name' => 'Hello World',
                    'description' => 'Simple test pack for external repository integration tests.',
                    'latest_version' => '1.0.1',
                    'versions' => [
                        [
                            'version' => '1.0.1',
                            'artifact_url' => 'utility.hello-world/utility.hello-world-latest.zip',
                            'artifact_signature_url' => 'utility.hello-world/utility.hello-world-latest.zip.sign',
                            'artifact_sha256' => 'aaaabbbbccccddddeeeeffff0000111122223333444455556666777788889999',
                            'pack_manifest_url' => 'utility.hello-world/pack.json',
                            'capabilities' => ['evidence-refresh'],
                            'permissions_requested' => ['network:https://api.example.org'],
                        ],
                        [
                            'version' => '1.0.0',
                            'artifact_url' => 'utility.hello-world/utility.hello-world-1.0.0.zip',
                            'artifact_signature_url' => 'utility.hello-world/utility.hello-world-1.0.0.zip.sign',
                            'artifact_sha256' => '1111222233334444555566667777888899990000aaaabbbbccccddddeeeeffff',
                            'pack_manifest_url' => 'utility.hello-world/pack.json',
                            'capabilities' => ['evidence-refresh'],
                            'permissions_requested' => ['network:https://api.example.org'],
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $signature = '';
        openssl_sign($repositoryJson, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);
        $repositorySignature = base64_encode($signature);

        Http::fake([
            'https://packages.example.org/deploy/repository.json' => Http::response($repositoryJson, 200),
            'https://packages.example.org/deploy/repository.sign' => Http::response($repositorySignature, 200),
        ]);

        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'menu' => 'plugin.automation-catalog.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/automation-catalog/repositories', [
            ...$payload,
            'label' => 'PymeSec Community',
            'repository_url' => 'https://packages.example.org/deploy/repository.json',
            'repository_sign_url' => 'https://packages.example.org/deploy/repository.sign',
            'public_key_pem' => $publicKeyPem,
            'trust_tier' => 'community-reviewed',
            'is_enabled' => '1',
        ])->assertFound();

        $repositoryId = (string) DB::table('automation_pack_repositories')
            ->where('organization_id', 'org-a')
            ->where('scope_id', 'scope-eu')
            ->where('repository_url', 'https://packages.example.org/deploy/repository.json')
            ->value('id');

        $this->assertNotSame('', $repositoryId);

        $this->post("/plugins/automation-catalog/repositories/{$repositoryId}/refresh", $payload)
            ->assertFound();

        $this->assertDatabaseHas('automation_pack_releases', [
            'repository_id' => $repositoryId,
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'pack_key' => 'utility.hello-world',
            'version' => '1.0.1',
            'is_latest' => true,
        ]);
        $this->assertDatabaseHas('automation_pack_releases', [
            'repository_id' => $repositoryId,
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'pack_key' => 'utility.hello-world',
            'version' => '1.0.0',
            'is_latest' => false,
        ]);
        $this->assertDatabaseHas('automation_packs', [
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'pack_key' => 'utility.hello-world',
            'name' => 'Hello World',
            'version' => '1.0.1',
            'provenance_type' => 'marketplace',
            'lifecycle_state' => 'discovered',
            'is_installed' => false,
            'is_enabled' => false,
        ]);
        $this->assertDatabaseHas('automation_pack_repositories', [
            'id' => $repositoryId,
            'last_status' => 'success',
        ]);
    }

    public function test_external_repository_refresh_fails_when_signature_is_invalid(): void
    {
        if (! function_exists('openssl_sign')) {
            $this->markTestSkipped('OpenSSL extension is required for repository signature tests.');
        }

        [, $publicKeyPem] = $this->buildKeyPair();

        $repositoryJson = json_encode([
            'repository' => [
                'id' => 'pymesec-community',
                'name' => 'PymeSec Community',
            ],
            'packs' => [
                [
                    'id' => 'utility.hello-world',
                    'name' => 'Hello World',
                    'latest_version' => '1.0.0',
                    'versions' => [
                        [
                            'version' => '1.0.0',
                            'artifact_url' => 'utility.hello-world/utility.hello-world-latest.zip',
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        Http::fake([
            'https://packages.invalid.example/deploy/repository.json' => Http::response($repositoryJson, 200),
            'https://packages.invalid.example/deploy/repository.sign' => Http::response(base64_encode('invalid-signature'), 200),
        ]);

        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'menu' => 'plugin.automation-catalog.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/automation-catalog/repositories', [
            ...$payload,
            'label' => 'Invalid signature repo',
            'repository_url' => 'https://packages.invalid.example/deploy/repository.json',
            'repository_sign_url' => 'https://packages.invalid.example/deploy/repository.sign',
            'public_key_pem' => $publicKeyPem,
            'trust_tier' => 'untrusted',
            'is_enabled' => '1',
        ])->assertFound();

        $repositoryId = (string) DB::table('automation_pack_repositories')
            ->where('organization_id', 'org-a')
            ->where('scope_id', 'scope-eu')
            ->where('repository_url', 'https://packages.invalid.example/deploy/repository.json')
            ->value('id');

        $this->assertNotSame('', $repositoryId);

        $this->post("/plugins/automation-catalog/repositories/{$repositoryId}/refresh", $payload)
            ->assertFound();

        $this->assertDatabaseHas('automation_pack_repositories', [
            'id' => $repositoryId,
            'last_status' => 'failed',
        ]);
        $this->assertSame(0, DB::table('automation_pack_releases')->where('repository_id', $repositoryId)->count());
    }

    /**
     * @return array{string, string}
     */
    private function buildKeyPair(): array
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $this->assertNotFalse($key);

        $privateKeyPem = '';
        openssl_pkey_export($key, $privateKeyPem);
        $this->assertNotSame('', $privateKeyPem);

        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);

        /** @var string $publicKeyPem */
        $publicKeyPem = $details['key'];
        $this->assertNotSame('', $publicKeyPem);

        return [$privateKeyPem, $publicKeyPem];
    }
}
