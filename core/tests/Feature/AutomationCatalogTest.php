<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
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
            ->assertSee('Install your first package repository')
            ->assertSee('Add repository of packs')
            ->assertSee('Register local pack');

        $this->get('/app?menu=plugin.automation-catalog.root&pack_id=automation-pack-entra-joiner-mover-leaver&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Automation pack')
            ->assertSee('connector.microsoft.entra-jml')
            ->assertSee('Degraded')
            ->assertSee('Back to automations')
            ->assertSee('Latest check results')
            ->assertDontSee('Install your first package repository')
            ->assertDontSee('Automation pack catalog')
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
        $this->post("/plugins/automation-catalog/{$packId}/uninstall", $payload)->assertFound();

        $this->assertDatabaseMissing('automation_packs', ['id' => $packId]);
        $this->assertDatabaseMissing('automation_pack_output_mappings', ['automation_pack_id' => $packId]);
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

    public function test_manual_runtime_run_executes_enabled_pack_and_persists_run_history(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'menu' => 'plugin.automation-catalog.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)
            ->assertFound();

        $this->assertDatabaseHas('automation_pack_runs', [
            'automation_pack_id' => 'automation-pack-aws-config-baseline',
            'trigger_mode' => 'manual',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('automation_pack_output_mappings', [
            'id' => 'automation-output-map-aws-control-review',
            'last_status' => 'success',
        ]);
        $this->assertDatabaseHas('automation_pack_output_mappings', [
            'id' => 'automation-output-map-aws-evidence-refresh',
            'last_status' => 'success',
        ]);
        $this->assertDatabaseHas('automation_packs', [
            'id' => 'automation-pack-aws-config-baseline',
            'health_state' => 'healthy',
        ]);
        $this->assertDatabaseHas('automation_check_results', [
            'automation_pack_id' => 'automation-pack-aws-config-baseline',
            'target_subject_type' => 'control',
            'target_subject_id' => 'control-access-review',
            'status' => 'success',
            'outcome' => 'pass',
        ]);
        $this->assertGreaterThanOrEqual(
            1,
            DB::table('artifacts')
                ->where('owner_component', 'automation-catalog')
                ->where('subject_type', 'control')
                ->where('subject_id', 'control-access-review')
                ->count()
        );

        $evidenceCheckResult = DB::table('automation_check_results')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('automation_output_mapping_id', 'automation-output-map-aws-evidence-refresh')
            ->where('target_subject_type', 'control')
            ->where('target_subject_id', 'control-access-review')
            ->where('status', 'success')
            ->orderByDesc('checked_at')
            ->first();
        $this->assertNotNull($evidenceCheckResult);

        $artifactId = is_object($evidenceCheckResult) && is_string($evidenceCheckResult->artifact_id ?? null)
            ? (string) $evidenceCheckResult->artifact_id
            : '';
        $evidenceId = is_object($evidenceCheckResult) && is_string($evidenceCheckResult->evidence_id ?? null)
            ? (string) $evidenceCheckResult->evidence_id
            : '';

        $this->assertNotSame('', $artifactId);
        $this->assertNotSame('', $evidenceId);
        $this->assertDatabaseHas('evidence_records', [
            'id' => $evidenceId,
            'artifact_id' => $artifactId,
            'organization_id' => 'org-a',
        ]);
    }

    public function test_runtime_scope_binding_resolves_asset_and_risk_targets_for_evidence_refresh(): void
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
            'mapping_label' => 'Scope asset runtime evidence',
            'mapping_kind' => 'evidence-refresh',
            'target_binding_mode' => 'scope',
            'target_subject_type' => 'asset',
            'target_scope_id' => 'scope-eu',
            'target_tags' => 'criticality:high,classification:confidential',
            'posture_propagation_policy' => 'status-only',
            'is_active' => '1',
        ])->assertFound();

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/output-mappings', [
            ...$payload,
            'mapping_label' => 'Scope risk runtime evidence',
            'mapping_kind' => 'evidence-refresh',
            'target_binding_mode' => 'scope',
            'target_subject_type' => 'risk',
            'target_scope_id' => 'scope-eu',
            'target_tags' => 'category:Identity',
            'posture_propagation_policy' => 'status-only',
            'is_active' => '1',
        ])->assertFound();

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)
            ->assertFound();

        $this->assertDatabaseHas('automation_pack_output_mappings', [
            'mapping_label' => 'Scope asset runtime evidence',
            'last_status' => 'success',
        ]);
        $this->assertDatabaseHas('automation_pack_output_mappings', [
            'mapping_label' => 'Scope risk runtime evidence',
            'last_status' => 'success',
        ]);
        $this->assertDatabaseHas('automation_check_results', [
            'automation_pack_id' => 'automation-pack-aws-config-baseline',
            'target_subject_type' => 'asset',
            'target_subject_id' => 'asset-erp-prod',
            'status' => 'success',
            'outcome' => 'pass',
        ]);
        $this->assertDatabaseHas('automation_check_results', [
            'automation_pack_id' => 'automation-pack-aws-config-baseline',
            'target_subject_type' => 'risk',
            'target_subject_id' => 'risk-access-drift',
            'status' => 'success',
            'outcome' => 'pass',
        ]);
        $this->assertDatabaseHas('assets', [
            'id' => 'asset-erp-prod',
            'organization_id' => 'org-a',
            'automation_posture' => 'healthy',
        ]);
        $this->assertDatabaseHas('risks', [
            'id' => 'risk-access-drift',
            'organization_id' => 'org-a',
            'automation_posture' => 'healthy',
        ]);
        $this->assertDatabaseHas('evidence_record_links', [
            'domain_type' => 'asset',
            'domain_id' => 'asset-erp-prod',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
        ]);
        $this->assertDatabaseHas('evidence_record_links', [
            'domain_type' => 'risk',
            'domain_id' => 'risk-access-drift',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
        ]);
    }

    public function test_runtime_failed_mapping_propagates_degraded_posture_for_risk_when_policy_is_enabled(): void
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
            'mapping_label' => 'Broken risk workflow transition',
            'mapping_kind' => 'workflow-transition',
            'target_binding_mode' => 'explicit',
            'target_subject_type' => 'risk',
            'target_subject_id' => 'risk-access-drift',
            'workflow_key' => 'plugin.controls-catalog.control-lifecycle',
            'transition_key' => 'transition-that-does-not-exist',
            'posture_propagation_policy' => 'status-only',
            'is_active' => '1',
        ])->assertFound();

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)
            ->assertFound();

        $this->assertDatabaseHas('automation_check_results', [
            'automation_pack_id' => 'automation-pack-aws-config-baseline',
            'target_subject_type' => 'risk',
            'target_subject_id' => 'risk-access-drift',
            'status' => 'failed',
            'outcome' => 'fail',
        ]);
        $this->assertDatabaseHas('risks', [
            'id' => 'risk-access-drift',
            'organization_id' => 'org-a',
            'automation_posture' => 'degraded',
        ]);

        $failedCheckResult = DB::table('automation_check_results')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('target_subject_type', 'risk')
            ->where('target_subject_id', 'risk-access-drift')
            ->where('status', 'failed')
            ->orderByDesc('checked_at')
            ->first();
        $this->assertNotNull($failedCheckResult);

        $failedCheckResultId = is_object($failedCheckResult) && is_string($failedCheckResult->id ?? null)
            ? (string) $failedCheckResult->id
            : '';
        $failedRunId = is_object($failedCheckResult) && is_string($failedCheckResult->automation_pack_run_id ?? null)
            ? (string) $failedCheckResult->automation_pack_run_id
            : '';

        $this->assertNotSame('', $failedCheckResultId);
        $this->assertNotSame('', $failedRunId);
        $this->assertDatabaseHas('risks', [
            'id' => 'risk-access-drift',
            'organization_id' => 'org-a',
            'automation_posture_check_result_id' => $failedCheckResultId,
            'automation_posture_run_id' => $failedRunId,
        ]);
    }

    public function test_on_fail_raise_finding_creates_one_deduplicated_automation_finding(): void
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
            'mapping_label' => 'Raise finding on failed workflow',
            'mapping_kind' => 'workflow-transition',
            'target_binding_mode' => 'explicit',
            'target_subject_type' => 'risk',
            'target_subject_id' => 'risk-access-drift',
            'workflow_key' => 'plugin.controls-catalog.control-lifecycle',
            'transition_key' => 'transition-that-does-not-exist',
            'on_fail_policy' => 'raise-finding',
            'is_active' => '1',
        ])->assertFound();

        $mappingId = (string) DB::table('automation_pack_output_mappings')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('mapping_label', 'Raise finding on failed workflow')
            ->value('id');
        $this->assertNotSame('', $mappingId);

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)
            ->assertFound();
        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)
            ->assertFound();

        $automationFindingCount = DB::table('findings')
            ->where('organization_id', 'org-a')
            ->where('scope_id', 'scope-eu')
            ->where('linked_risk_id', 'risk-access-drift')
            ->where('title', 'like', 'Automation failure · Raise finding on failed workflow%')
            ->count();
        $this->assertSame(1, $automationFindingCount);

        $this->assertDatabaseHas('automation_failure_findings', [
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'automation_pack_id' => 'automation-pack-aws-config-baseline',
            'automation_output_mapping_id' => $mappingId,
            'target_subject_type' => 'risk',
            'target_subject_id' => 'risk-access-drift',
        ]);

        $this->assertDatabaseHas('automation_check_results', [
            'automation_pack_id' => 'automation-pack-aws-config-baseline',
            'automation_output_mapping_id' => $mappingId,
            'target_subject_type' => 'risk',
            'target_subject_id' => 'risk-access-drift',
            'status' => 'failed',
            'finding_id' => (string) DB::table('automation_failure_findings')
                ->where('automation_output_mapping_id', $mappingId)
                ->value('finding_id'),
        ]);
    }

    public function test_on_fail_raise_finding_and_action_creates_one_deduplicated_action(): void
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
            'mapping_label' => 'Raise finding and action on failed workflow',
            'mapping_kind' => 'workflow-transition',
            'target_binding_mode' => 'explicit',
            'target_subject_type' => 'risk',
            'target_subject_id' => 'risk-access-drift',
            'workflow_key' => 'plugin.controls-catalog.control-lifecycle',
            'transition_key' => 'transition-that-does-not-exist',
            'on_fail_policy' => 'raise-finding-and-action',
            'is_active' => '1',
        ])->assertFound();

        $mappingId = (string) DB::table('automation_pack_output_mappings')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('mapping_label', 'Raise finding and action on failed workflow')
            ->value('id');
        $this->assertNotSame('', $mappingId);

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)
            ->assertFound();
        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)
            ->assertFound();

        $finding = DB::table('findings')
            ->where('organization_id', 'org-a')
            ->where('scope_id', 'scope-eu')
            ->where('linked_risk_id', 'risk-access-drift')
            ->where('title', 'like', 'Automation failure · Raise finding and action on failed workflow%')
            ->first();

        $this->assertNotNull($finding);
        $findingId = is_object($finding) && is_string($finding->id) ? (string) $finding->id : '';
        $this->assertNotSame('', $findingId);

        $actionCount = DB::table('remediation_actions')
            ->where('organization_id', 'org-a')
            ->where('scope_id', 'scope-eu')
            ->where('finding_id', $findingId)
            ->where('title', 'like', 'Investigate automation failure · Raise finding and action on failed workflow%')
            ->count();
        $this->assertSame(1, $actionCount);

        $actionId = (string) DB::table('remediation_actions')
            ->where('organization_id', 'org-a')
            ->where('scope_id', 'scope-eu')
            ->where('finding_id', $findingId)
            ->value('id');
        $this->assertNotSame('', $actionId);

        $this->assertDatabaseHas('automation_failure_findings', [
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'automation_pack_id' => 'automation-pack-aws-config-baseline',
            'automation_output_mapping_id' => $mappingId,
            'target_subject_type' => 'risk',
            'target_subject_id' => 'risk-access-drift',
            'finding_id' => $findingId,
            'remediation_action_id' => $actionId,
        ]);
        $this->assertDatabaseHas('automation_check_results', [
            'automation_pack_id' => 'automation-pack-aws-config-baseline',
            'automation_output_mapping_id' => $mappingId,
            'target_subject_type' => 'risk',
            'target_subject_id' => 'risk-access-drift',
            'status' => 'failed',
            'finding_id' => $findingId,
            'remediation_action_id' => $actionId,
        ]);
    }

    public function test_evidence_policy_on_change_delivers_once_and_then_skips_unchanged_payload(): void
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
            'mapping_label' => 'On change evidence mapping',
            'mapping_kind' => 'evidence-refresh',
            'target_binding_mode' => 'explicit',
            'target_subject_type' => 'control',
            'target_subject_id' => 'control-access-review',
            'evidence_policy' => 'on-change',
            'is_active' => '1',
        ])->assertFound();

        $mappingId = (string) DB::table('automation_pack_output_mappings')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('mapping_label', 'On change evidence mapping')
            ->value('id');
        $this->assertNotSame('', $mappingId);

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)->assertFound();
        $this->assertSame(
            1,
            DB::table('artifacts')
                ->where('owner_component', 'automation-catalog')
                ->where('subject_type', 'control')
                ->where('subject_id', 'control-access-review')
                ->where('label', 'On change evidence mapping output')
                ->count()
        );

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)->assertFound();
        $this->assertSame(
            1,
            DB::table('artifacts')
                ->where('owner_component', 'automation-catalog')
                ->where('subject_type', 'control')
                ->where('subject_id', 'control-access-review')
                ->where('label', 'On change evidence mapping output')
                ->count()
        );

        $this->assertDatabaseHas('automation_pack_output_mappings', [
            'id' => $mappingId,
            'last_status' => 'skipped',
        ]);
        $this->assertDatabaseHas('automation_evidence_delivery_states', [
            'automation_output_mapping_id' => $mappingId,
            'target_subject_type' => 'control',
            'target_subject_id' => 'control-access-review',
        ]);
    }

    public function test_evidence_policy_on_fail_skips_delivery_when_outcome_is_pass(): void
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
            'mapping_label' => 'On fail evidence policy mapping',
            'mapping_kind' => 'evidence-refresh',
            'target_binding_mode' => 'explicit',
            'target_subject_type' => 'control',
            'target_subject_id' => 'control-access-review',
            'evidence_policy' => 'on-fail',
            'is_active' => '1',
        ])->assertFound();

        $mappingId = (string) DB::table('automation_pack_output_mappings')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('mapping_label', 'On fail evidence policy mapping')
            ->value('id');
        $this->assertNotSame('', $mappingId);

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)->assertFound();

        $this->assertDatabaseHas('automation_pack_output_mappings', [
            'id' => $mappingId,
            'last_status' => 'skipped',
        ]);
        $this->assertSame(
            0,
            DB::table('artifacts')
                ->where('owner_component', 'automation-catalog')
                ->where('subject_type', 'control')
                ->where('subject_id', 'control-access-review')
                ->where('label', 'On fail evidence policy mapping output')
                ->count()
        );
    }

    public function test_runtime_retry_policy_retries_failed_mapping_and_persists_attempt_metadata(): void
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
            'mapping_label' => 'Retry failed workflow mapping',
            'mapping_kind' => 'workflow-transition',
            'target_binding_mode' => 'explicit',
            'target_subject_type' => 'risk',
            'target_subject_id' => 'risk-access-drift',
            'workflow_key' => 'plugin.controls-catalog.control-lifecycle',
            'transition_key' => 'transition-that-does-not-exist',
            'runtime_retry_max_attempts' => 2,
            'runtime_retry_backoff_ms' => 0,
            'is_active' => '1',
        ])->assertFound();

        $mappingId = (string) DB::table('automation_pack_output_mappings')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('mapping_label', 'Retry failed workflow mapping')
            ->value('id');
        $this->assertNotSame('', $mappingId);

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)->assertFound();

        $checkResult = DB::table('automation_check_results')
            ->where('automation_output_mapping_id', $mappingId)
            ->where('target_subject_type', 'risk')
            ->where('target_subject_id', 'risk-access-drift')
            ->orderByDesc('checked_at')
            ->first();

        $this->assertNotNull($checkResult);
        $this->assertSame('failed', is_object($checkResult) ? (string) $checkResult->status : '');
        $this->assertSame(3, is_object($checkResult) ? (int) $checkResult->attempt_count : 0);
        $this->assertSame(2, is_object($checkResult) ? (int) $checkResult->retry_count : 0);
        $this->assertNotSame('', is_object($checkResult) && is_string($checkResult->idempotency_key ?? null) ? (string) $checkResult->idempotency_key : '');
    }

    public function test_runtime_guardrail_blocks_scope_mapping_when_resolved_targets_exceed_limit(): void
    {
        DB::table('assets')->insert([
            'id' => 'asset-guardrail-extra',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'name' => 'Guardrail Extra Asset',
            'type' => 'application',
            'criticality' => 'medium',
            'classification' => 'internal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
            'mapping_label' => 'Scope guardrail max targets',
            'mapping_kind' => 'evidence-refresh',
            'target_binding_mode' => 'scope',
            'target_subject_type' => 'asset',
            'target_scope_id' => 'scope-eu',
            'runtime_max_targets' => 1,
            'is_active' => '1',
        ])->assertFound();

        $mappingId = (string) DB::table('automation_pack_output_mappings')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('mapping_label', 'Scope guardrail max targets')
            ->value('id');
        $this->assertNotSame('', $mappingId);

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)->assertFound();

        $checkResult = DB::table('automation_check_results')
            ->where('automation_output_mapping_id', $mappingId)
            ->orderByDesc('checked_at')
            ->first();

        $this->assertNotNull($checkResult);
        $this->assertSame('failed', is_object($checkResult) ? (string) $checkResult->status : '');
        $this->assertStringContainsString(
            'Guardrail: resolved targets',
            is_object($checkResult) && is_string($checkResult->message ?? null) ? (string) $checkResult->message : ''
        );
    }

    public function test_runtime_guardrail_blocks_payload_above_mapping_limit(): void
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
            'mapping_label' => 'Payload size guardrail mapping',
            'mapping_kind' => 'evidence-refresh',
            'target_binding_mode' => 'explicit',
            'target_subject_type' => 'control',
            'target_subject_id' => 'control-access-review',
            'runtime_payload_max_kb' => 0,
            'is_active' => '1',
        ])->assertFound();

        $mappingId = (string) DB::table('automation_pack_output_mappings')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('mapping_label', 'Payload size guardrail mapping')
            ->value('id');
        $this->assertNotSame('', $mappingId);

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)->assertFound();

        $checkResult = DB::table('automation_check_results')
            ->where('automation_output_mapping_id', $mappingId)
            ->where('target_subject_type', 'control')
            ->where('target_subject_id', 'control-access-review')
            ->orderByDesc('checked_at')
            ->first();

        $this->assertNotNull($checkResult);
        $this->assertSame('failed', is_object($checkResult) ? (string) $checkResult->status : '');
        $this->assertStringContainsString(
            'Guardrail: payload size',
            is_object($checkResult) && is_string($checkResult->message ?? null) ? (string) $checkResult->message : ''
        );
    }

    public function test_runtime_only_mapping_cannot_be_applied_manually_and_executes_in_runtime(): void
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
            'mapping_label' => 'Runtime only evidence mapping',
            'mapping_kind' => 'evidence-refresh',
            'target_binding_mode' => 'explicit',
            'target_subject_type' => 'control',
            'target_subject_id' => 'control-access-review',
            'execution_mode' => 'runtime-only',
            'is_active' => '1',
        ])->assertFound();

        $mappingId = (string) DB::table('automation_pack_output_mappings')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('mapping_label', 'Runtime only evidence mapping')
            ->value('id');
        $this->assertNotSame('', $mappingId);

        $this->post("/plugins/automation-catalog/automation-pack-aws-config-baseline/output-mappings/{$mappingId}/apply", $payload)
            ->assertFound();

        $this->assertDatabaseHas('automation_pack_output_mappings', [
            'id' => $mappingId,
            'last_status' => 'never',
        ]);

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)
            ->assertFound();

        $this->assertDatabaseHas('automation_pack_output_mappings', [
            'id' => $mappingId,
            'last_status' => 'success',
        ]);
    }

    public function test_manual_only_mapping_is_skipped_by_runtime_and_can_be_applied_manually(): void
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
            'mapping_label' => 'Manual only control transition',
            'mapping_kind' => 'workflow-transition',
            'target_binding_mode' => 'explicit',
            'target_subject_type' => 'control',
            'target_subject_id' => 'control-access-review',
            'workflow_key' => 'plugin.controls-catalog.control-lifecycle',
            'transition_key' => 'approve',
            'execution_mode' => 'manual-only',
            'is_active' => '1',
        ])->assertFound();

        $mappingId = (string) DB::table('automation_pack_output_mappings')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('mapping_label', 'Manual only control transition')
            ->value('id');
        $this->assertNotSame('', $mappingId);

        $this->post('/plugins/automation-catalog/automation-pack-aws-config-baseline/run', $payload)
            ->assertFound();

        $this->assertDatabaseHas('automation_pack_output_mappings', [
            'id' => $mappingId,
            'last_status' => 'never',
        ]);

        $this->post("/plugins/automation-catalog/automation-pack-aws-config-baseline/output-mappings/{$mappingId}/apply", $payload)
            ->assertFound();

        $this->assertDatabaseHas('automation_pack_output_mappings', [
            'id' => $mappingId,
            'last_status' => 'success',
        ]);
    }

    public function test_scheduled_runtime_command_runs_enabled_packs(): void
    {
        $exitCode = Artisan::call('automation:runs', [
            '--organization_id' => 'org-a',
            '--scope_id' => 'scope-eu',
            '--trigger' => 'scheduled',
            '--principal_id' => 'principal-org-a',
            '--membership_id' => 'membership-org-a-hello',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertGreaterThanOrEqual(
            1,
            DB::table('automation_pack_runs')->where('trigger_mode', 'scheduled')->count()
        );
        $this->assertDatabaseHas('automation_pack_runs', [
            'automation_pack_id' => 'automation-pack-aws-config-baseline',
            'trigger_mode' => 'scheduled',
        ]);
    }

    public function test_scheduled_runtime_command_can_respect_per_pack_schedule_policy(): void
    {
        DB::table('automation_packs')
            ->where('id', 'automation-pack-aws-config-baseline')
            ->update([
                'runtime_schedule_enabled' => true,
                'runtime_schedule_cron' => '* * * * *',
                'runtime_schedule_timezone' => 'UTC',
                'runtime_schedule_last_slot' => null,
                'updated_at' => now(),
            ]);

        $beforeCount = DB::table('automation_pack_runs')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('trigger_mode', 'scheduled')
            ->count();

        $exitCode = Artisan::call('automation:runs', [
            '--organization_id' => 'org-a',
            '--scope_id' => 'scope-eu',
            '--trigger' => 'scheduled',
            '--respect_schedule' => '1',
            '--principal_id' => 'principal-org-a',
            '--membership_id' => 'membership-org-a-hello',
        ]);
        $this->assertSame(0, $exitCode);

        $afterFirstCount = DB::table('automation_pack_runs')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('trigger_mode', 'scheduled')
            ->count();

        $this->assertGreaterThan($beforeCount, $afterFirstCount);
        $this->assertDatabaseHas('automation_packs', [
            'id' => 'automation-pack-aws-config-baseline',
            'runtime_schedule_enabled' => true,
            'runtime_schedule_cron' => '* * * * *',
            'runtime_schedule_timezone' => 'UTC',
        ]);
        $lastSlot = (string) DB::table('automation_packs')
            ->where('id', 'automation-pack-aws-config-baseline')
            ->value('runtime_schedule_last_slot');
        $this->assertNotSame('', $lastSlot);

        $exitCode = Artisan::call('automation:runs', [
            '--organization_id' => 'org-a',
            '--scope_id' => 'scope-eu',
            '--trigger' => 'scheduled',
            '--respect_schedule' => '1',
            '--principal_id' => 'principal-org-a',
            '--membership_id' => 'membership-org-a-hello',
        ]);
        $this->assertSame(0, $exitCode);

        $afterSecondCount = DB::table('automation_pack_runs')
            ->where('automation_pack_id', 'automation-pack-aws-config-baseline')
            ->where('trigger_mode', 'scheduled')
            ->count();

        $this->assertSame($afterFirstCount, $afterSecondCount);
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
            'source_ref' => 'https://packages.example.org/deploy/?pack=utility.hello-world',
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

    public function test_external_repository_refresh_accepts_openssh_rsa_public_keys(): void
    {
        if (! function_exists('openssl_sign')) {
            $this->markTestSkipped('OpenSSL extension is required for repository signature tests.');
        }

        [$privateKeyPem, $publicKeyPem] = $this->buildKeyPair();
        $openSshPublicKey = $this->toOpenSshRsaPublicKey($publicKeyPem);
        $openSshPublicKeyWrapped = $this->wrapOpenSshPublicKey($openSshPublicKey);

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
                            'artifact_signature_url' => 'utility.hello-world/utility.hello-world-latest.zip.sign',
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $signature = '';
        openssl_sign($repositoryJson, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);
        $repositorySignature = base64_encode($signature);

        Http::fake([
            'https://packages.openssh.example/deploy/repository.json' => Http::response($repositoryJson, 200),
            'https://packages.openssh.example/deploy/repository.sign' => Http::response($repositorySignature, 200),
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
            'label' => 'OpenSSH key repo',
            'repository_url' => 'https://packages.openssh.example/deploy/repository.json',
            'repository_sign_url' => 'https://packages.openssh.example/deploy/repository.sign',
            'public_key_pem' => $openSshPublicKeyWrapped,
            'trust_tier' => 'community-reviewed',
            'is_enabled' => '1',
        ])->assertFound();

        $repositoryId = (string) DB::table('automation_pack_repositories')
            ->where('organization_id', 'org-a')
            ->where('scope_id', 'scope-eu')
            ->where('repository_url', 'https://packages.openssh.example/deploy/repository.json')
            ->value('id');

        $this->assertNotSame('', $repositoryId);

        $this->post("/plugins/automation-catalog/repositories/{$repositoryId}/refresh", $payload)
            ->assertFound();

        $this->assertDatabaseHas('automation_pack_repositories', [
            'id' => $repositoryId,
            'last_status' => 'success',
        ]);
        $this->assertDatabaseHas('automation_pack_releases', [
            'repository_id' => $repositoryId,
            'pack_key' => 'utility.hello-world',
            'version' => '1.0.0',
        ]);
    }

    public function test_official_repository_can_be_installed_with_one_click_and_refreshed(): void
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
                    'latest_version' => '1.0.1',
                    'versions' => [
                        [
                            'version' => '1.0.1',
                            'artifact_url' => 'utility.hello-world/utility.hello-world-latest.zip',
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $signature = '';
        openssl_sign($repositoryJson, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);
        $repositorySignature = base64_encode($signature);

        Http::fake([
            'https://repository.pimesec.com/repository.json' => Http::response($repositoryJson, 200),
            'https://repository.pimesec.com/repository.json.sign' => Http::response($repositorySignature, 200),
        ]);

        config()->set('plugins.automation_catalog.official_repository', [
            'label' => 'PymeSec Official Repository',
            'url' => 'https://repository.pimesec.com/repository.json',
            'sign_url' => 'https://repository.pimesec.com/repository.json.sign',
            'trust_tier' => 'trusted-first-party',
            'public_key_pem' => $publicKeyPem,
            'public_key_path' => '',
        ]);

        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'menu' => 'plugin.automation-catalog.root',
            'membership_id' => 'membership-org-a-hello',
            'automation_panel' => 'repository-editor',
        ];

        $this->post('/plugins/automation-catalog/repositories/install-official', $payload)
            ->assertFound();

        $repositoryId = (string) DB::table('automation_pack_repositories')
            ->where('organization_id', 'org-a')
            ->where('scope_id', 'scope-eu')
            ->where('repository_url', 'https://repository.pimesec.com/repository.json')
            ->value('id');

        $this->assertNotSame('', $repositoryId);

        $this->assertDatabaseHas('automation_pack_repositories', [
            'id' => $repositoryId,
            'label' => 'PymeSec Official Repository',
            'repository_sign_url' => 'https://repository.pimesec.com/repository.json.sign',
            'trust_tier' => 'trusted-first-party',
            'last_status' => 'success',
        ]);
        $this->assertDatabaseHas('automation_pack_releases', [
            'repository_id' => $repositoryId,
            'pack_key' => 'utility.hello-world',
            'version' => '1.0.1',
            'is_latest' => true,
        ]);
        $this->assertDatabaseHas('automation_packs', [
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'pack_key' => 'utility.hello-world',
            'source_ref' => 'https://repository.pimesec.com/?pack=utility.hello-world',
        ]);
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

    private function toOpenSshRsaPublicKey(string $publicKeyPem): string
    {
        $key = openssl_pkey_get_public($publicKeyPem);
        $this->assertNotFalse($key);

        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);
        $this->assertIsArray($details['rsa'] ?? null);

        /** @var array{n: string, e: string} $rsa */
        $rsa = $details['rsa'];
        $modulus = $this->normalizeMpint((string) $rsa['n']);
        $exponent = $this->normalizeMpint((string) $rsa['e']);

        $blob = $this->sshString('ssh-rsa').$this->sshString($exponent).$this->sshString($modulus);

        return 'ssh-rsa '.base64_encode($blob);
    }

    private function sshString(string $value): string
    {
        return pack('N', strlen($value)).$value;
    }

    private function normalizeMpint(string $value): string
    {
        $normalized = ltrim($value, "\x00");
        if ($normalized === '') {
            return "\x00";
        }

        if ((ord($normalized[0]) & 0x80) !== 0) {
            return "\x00".$normalized;
        }

        return $normalized;
    }

    private function wrapOpenSshPublicKey(string $key): string
    {
        $parts = preg_split('/\s+/', trim($key), 3);
        $this->assertIsArray($parts);
        $this->assertSame('ssh-rsa', $parts[0] ?? null);
        $this->assertNotSame('', $parts[1] ?? '');

        $payload = chunk_split((string) $parts[1], 48, "\n");

        return "ssh-rsa\n".trim($payload);
    }
}
