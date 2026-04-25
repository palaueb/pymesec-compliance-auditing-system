<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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

    public function test_core_domain_lifecycle_owner_and_artifact_api_endpoints_work(): void
    {
        Storage::fake('local');

        $base = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
        ];

        $assetCreate = $this->postJson('/api/v1/assets', [
            ...$base,
            'name' => 'API Lifecycle Asset',
            'type' => 'application',
            'criticality' => 'high',
            'classification' => 'internal',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk();
        $assetId = (string) $assetCreate->json('data.id');

        $this->postJson("/api/v1/assets/{$assetId}/transitions/submit-review", [
            ...$base,
            'scope_id' => 'scope-eu',
        ])->assertOk()->assertJsonPath('data.transition', 'submit-review');

        $assetOwnerAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'asset')
            ->where('domain_object_id', $assetId)
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-ava-mason')
            ->where('is_active', true)
            ->value('id');
        $this->assertNotSame('', $assetOwnerAssignmentId);

        $this->patchJson("/api/v1/assets/{$assetId}/owners/{$assetOwnerAssignmentId}/remove", $base)
            ->assertOk()
            ->assertJsonPath('data.removed', true);

        $riskCreate = $this->postJson('/api/v1/risks', [
            ...$base,
            'title' => 'API Lifecycle Risk',
            'category' => 'operations',
            'inherent_score' => 45,
            'residual_score' => 20,
            'treatment' => 'Reduce onboarding exceptions',
            'scope_id' => 'scope-eu',
            'linked_asset_id' => $assetId,
            'linked_control_id' => 'control-access-review',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk();
        $riskId = (string) $riskCreate->json('data.id');

        $this->postJson("/api/v1/risks/{$riskId}/transitions/start-assessment", [
            ...$base,
            'scope_id' => 'scope-eu',
        ])->assertOk()->assertJsonPath('data.transition', 'start-assessment');

        $this->post("/api/v1/risks/{$riskId}/artifacts", [
            ...$base,
            'label' => 'Risk evidence bundle',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('risk-evidence.pdf', 'risk evidence'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.subject_type', 'risk')
            ->assertJsonPath('data.subject_id', $riskId);

        $riskOwnerAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'risk')
            ->where('domain_object_id', $riskId)
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-ava-mason')
            ->where('is_active', true)
            ->value('id');
        $this->assertNotSame('', $riskOwnerAssignmentId);

        $this->patchJson("/api/v1/risks/{$riskId}/owners/{$riskOwnerAssignmentId}/remove", $base)
            ->assertOk()
            ->assertJsonPath('data.removed', true);

        $controlCreate = $this->postJson('/api/v1/controls', [
            ...$base,
            'name' => 'API Lifecycle Control',
            'framework' => 'Internal Security',
            'domain' => 'identity',
            'evidence' => 'Control evidence notes',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk();
        $controlId = (string) $controlCreate->json('data.id');

        $this->postJson("/api/v1/controls/{$controlId}/transitions/submit-review", [
            ...$base,
            'scope_id' => 'scope-eu',
        ])->assertOk()->assertJsonPath('data.transition', 'submit-review');

        $this->post("/api/v1/controls/{$controlId}/artifacts", [
            ...$base,
            'label' => 'Control evidence bundle',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('control-evidence.pdf', 'control evidence'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.subject_type', 'control')
            ->assertJsonPath('data.subject_id', $controlId);

        $controlOwnerAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'control')
            ->where('domain_object_id', $controlId)
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-ava-mason')
            ->where('is_active', true)
            ->value('id');
        $this->assertNotSame('', $controlOwnerAssignmentId);

        $this->patchJson("/api/v1/controls/{$controlId}/owners/{$controlOwnerAssignmentId}/remove", $base)
            ->assertOk()
            ->assertJsonPath('data.removed', true);

        $findingCreate = $this->postJson('/api/v1/findings', [
            ...$base,
            'title' => 'API Lifecycle Finding',
            'severity' => 'high',
            'description' => 'Finding for lifecycle test',
            'scope_id' => 'scope-eu',
            'linked_control_id' => $controlId,
            'linked_risk_id' => $riskId,
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk();
        $findingId = (string) $findingCreate->json('data.id');

        $this->postJson("/api/v1/findings/{$findingId}/transitions/triage", [
            ...$base,
            'scope_id' => 'scope-eu',
        ])->assertOk()->assertJsonPath('data.transition', 'triage');

        $this->post("/api/v1/findings/{$findingId}/artifacts", [
            ...$base,
            'label' => 'Finding evidence bundle',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('finding-evidence.pdf', 'finding evidence'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.subject_type', 'finding')
            ->assertJsonPath('data.subject_id', $findingId);

        $findingOwnerAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'finding')
            ->where('domain_object_id', $findingId)
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-ava-mason')
            ->where('is_active', true)
            ->value('id');
        $this->assertNotSame('', $findingOwnerAssignmentId);

        $this->patchJson("/api/v1/findings/{$findingId}/owners/{$findingOwnerAssignmentId}/remove", $base)
            ->assertOk()
            ->assertJsonPath('data.removed', true);

        $actionCreate = $this->postJson("/api/v1/findings/{$findingId}/actions", [
            ...$base,
            'title' => 'API Lifecycle Action',
            'status' => 'planned',
            'notes' => 'Action for owner-removal test',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertOk();
        $actionId = (string) $actionCreate->json('data.id');

        $actionOwnerAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'remediation-action')
            ->where('domain_object_id', $actionId)
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-compliance-office')
            ->where('is_active', true)
            ->value('id');
        $this->assertNotSame('', $actionOwnerAssignmentId);

        $this->patchJson("/api/v1/findings/actions/{$actionId}/owners/{$actionOwnerAssignmentId}/remove", $base)
            ->assertOk()
            ->assertJsonPath('data.removed', true);
    }

    public function test_assessment_lifecycle_owner_artifact_and_review_finding_api_endpoints_work(): void
    {
        Storage::fake('local');

        $base = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
            'scope_id' => 'scope-eu',
        ];

        $controlCreate = $this->postJson('/api/v1/controls', [
            ...$base,
            'name' => 'API Assessment Lifecycle Control',
            'framework' => 'Internal Security',
            'domain' => 'identity',
            'evidence' => 'Control evidence for assessment lifecycle',
        ])->assertOk();
        $controlId = (string) $controlCreate->json('data.id');

        $assessmentCreate = $this->postJson('/api/v1/assessments', [
            ...$base,
            'title' => 'API Assessment Lifecycle',
            'summary' => 'Assessment lifecycle API parity test',
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'draft',
            'control_ids' => [$controlId],
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk();
        $assessmentId = (string) $assessmentCreate->json('data.id');

        $this->patchJson("/api/v1/assessments/{$assessmentId}/reviews/{$controlId}", [
            ...$base,
            'result' => 'fail',
            'test_notes' => 'Control failed validation for lifecycle test',
            'conclusion' => 'Escalate to remediation',
            'reviewed_on' => '2026-04-10',
        ])->assertOk()
            ->assertJsonPath('data.result', 'fail');

        $this->post("/api/v1/assessments/{$assessmentId}/reviews/{$controlId}/artifacts", [
            ...$base,
            'label' => 'Assessment review workpaper',
            'artifact_type' => 'workpaper',
            'artifact' => UploadedFile::fake()->createWithContent('assessment-review.pdf', 'assessment review evidence'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.subject_type', 'assessment-review');

        $findingResponse = $this->postJson("/api/v1/assessments/{$assessmentId}/reviews/{$controlId}/findings", [
            ...$base,
            'title' => 'Assessment review generated finding',
            'severity' => 'high',
            'description' => 'Finding raised directly from failed review',
            'due_on' => '2026-05-15',
        ])->assertOk();
        $findingId = (string) $findingResponse->json('data.id');
        $this->assertNotSame('', $findingId);

        $reviewLinkedFindingId = (string) DB::table('assessment_control_reviews')
            ->where('assessment_id', $assessmentId)
            ->where('control_id', $controlId)
            ->value('linked_finding_id');
        $this->assertSame($findingId, $reviewLinkedFindingId);

        $this->postJson("/api/v1/assessments/{$assessmentId}/transitions/activate", $base)
            ->assertOk()
            ->assertJsonPath('data.transition', 'activate')
            ->assertJsonPath('data.status', 'active');

        $ownerAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'assessment')
            ->where('domain_object_id', $assessmentId)
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-ava-mason')
            ->where('is_active', true)
            ->value('id');
        $this->assertNotSame('', $ownerAssignmentId);

        $this->patchJson("/api/v1/assessments/{$assessmentId}/owners/{$ownerAssignmentId}/remove", $base)
            ->assertOk()
            ->assertJsonPath('data.removed', true);
    }

    public function test_policy_exception_lifecycle_owner_artifact_and_transition_api_endpoints_work(): void
    {
        Storage::fake('local');

        $base = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
            'scope_id' => 'scope-eu',
        ];

        $controlCreate = $this->postJson('/api/v1/controls', [
            ...$base,
            'name' => 'API Policy Linked Control',
            'framework' => 'Internal Security',
            'domain' => 'identity',
            'evidence' => 'Control evidence for policy lifecycle test',
        ])->assertOk();
        $controlId = (string) $controlCreate->json('data.id');

        $policyCreate = $this->postJson('/api/v1/policies', [
            ...$base,
            'title' => 'API Policy Lifecycle',
            'area' => 'identity',
            'version_label' => 'v1.0',
            'statement' => 'Baseline policy statement for API lifecycle testing',
            'linked_control_id' => $controlId,
            'review_due_on' => '2026-05-10',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk();
        $policyId = (string) $policyCreate->json('data.id');

        $this->patchJson("/api/v1/policies/{$policyId}", [
            ...$base,
            'title' => 'API Policy Lifecycle Updated',
            'area' => 'identity',
            'version_label' => 'v1.1',
            'statement' => 'Updated policy statement from API',
            'linked_control_id' => $controlId,
            'review_due_on' => '2026-05-20',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk()
            ->assertJsonPath('data.title', 'API Policy Lifecycle Updated');

        $this->post("/api/v1/policies/{$policyId}/artifacts", [
            ...$base,
            'label' => 'Policy baseline document',
            'artifact_type' => 'document',
            'artifact' => UploadedFile::fake()->createWithContent('policy.pdf', 'policy document'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.subject_type', 'policy')
            ->assertJsonPath('data.subject_id', $policyId);

        $this->postJson("/api/v1/policies/{$policyId}/transitions/submit-review", $base)
            ->assertOk()
            ->assertJsonPath('data.transition', 'submit-review');

        $policyOwnerAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'policy')
            ->where('domain_object_id', $policyId)
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-ava-mason')
            ->where('is_active', true)
            ->value('id');
        $this->assertNotSame('', $policyOwnerAssignmentId);

        $this->patchJson("/api/v1/policies/{$policyId}/owners/{$policyOwnerAssignmentId}/remove", $base)
            ->assertOk()
            ->assertJsonPath('data.removed', true);

        $findingCreate = $this->postJson('/api/v1/findings', [
            ...$base,
            'title' => 'API Policy Exception Finding',
            'severity' => 'high',
            'description' => 'Finding used as linked record for policy exception API test',
            'linked_control_id' => $controlId,
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertOk();
        $findingId = (string) $findingCreate->json('data.id');

        $exceptionCreate = $this->postJson("/api/v1/policies/{$policyId}/exceptions", [
            ...$base,
            'title' => 'API Policy Exception',
            'rationale' => 'Temporary exception for controlled migration window',
            'compensating_control' => 'Daily privileged access review',
            'linked_finding_id' => $findingId,
            'expires_on' => '2026-06-15',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk();
        $exceptionId = (string) $exceptionCreate->json('data.id');

        $this->patchJson("/api/v1/policies/exceptions/{$exceptionId}", [
            ...$base,
            'title' => 'API Policy Exception Updated',
            'rationale' => 'Temporary exception with updated safeguards',
            'compensating_control' => 'Daily privileged review and alerting',
            'linked_finding_id' => $findingId,
            'expires_on' => '2026-06-20',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk()
            ->assertJsonPath('data.title', 'API Policy Exception Updated');

        $this->post("/api/v1/policies/exceptions/{$exceptionId}/artifacts", [
            ...$base,
            'label' => 'Exception approval evidence',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('exception-evidence.pdf', 'exception evidence'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.subject_type', 'policy-exception')
            ->assertJsonPath('data.subject_id', $exceptionId);

        $this->postJson("/api/v1/policies/exceptions/{$exceptionId}/transitions/approve", $base)
            ->assertOk()
            ->assertJsonPath('data.transition', 'approve');

        $exceptionOwnerAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'policy-exception')
            ->where('domain_object_id', $exceptionId)
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-ava-mason')
            ->where('is_active', true)
            ->value('id');
        $this->assertNotSame('', $exceptionOwnerAssignmentId);

        $this->patchJson("/api/v1/policies/exceptions/{$exceptionId}/owners/{$exceptionOwnerAssignmentId}/remove", $base)
            ->assertOk()
            ->assertJsonPath('data.removed', true);
    }

    public function test_privacy_data_flow_and_processing_activity_api_lifecycle_endpoints_work(): void
    {
        Storage::fake('local');

        $base = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
            'scope_id' => 'scope-eu',
        ];

        $assetCreate = $this->postJson('/api/v1/assets', [
            ...$base,
            'name' => 'API Privacy Linked Asset',
            'type' => 'application',
            'criticality' => 'high',
            'classification' => 'internal',
        ])->assertOk();
        $assetId = (string) $assetCreate->json('data.id');

        $riskCreate = $this->postJson('/api/v1/risks', [
            ...$base,
            'title' => 'API Privacy Linked Risk',
            'category' => 'operations',
            'inherent_score' => 44,
            'residual_score' => 21,
            'treatment' => 'Reduce exposure across handoff systems',
            'linked_asset_id' => $assetId,
        ])->assertOk();
        $riskId = (string) $riskCreate->json('data.id');

        $policyCreate = $this->postJson('/api/v1/policies', [
            ...$base,
            'title' => 'API Privacy Linked Policy',
            'area' => 'identity',
            'version_label' => 'v1.0',
            'statement' => 'Policy used for privacy activity linking',
        ])->assertOk();
        $policyId = (string) $policyCreate->json('data.id');

        $findingCreate = $this->postJson('/api/v1/findings', [
            ...$base,
            'title' => 'API Privacy Linked Finding',
            'severity' => 'medium',
            'description' => 'Finding used for privacy activity linking',
        ])->assertOk();
        $findingId = (string) $findingCreate->json('data.id');

        $flowCreate = $this->postJson('/api/v1/privacy/data-flows', [
            ...$base,
            'title' => 'API Privacy Data Flow',
            'source' => 'CRM',
            'destination' => 'Support tooling',
            'data_category_summary' => 'Customer profile and incident context',
            'transfer_type' => 'vendor',
            'review_due_on' => '2026-06-01',
            'linked_asset_id' => $assetId,
            'linked_risk_id' => $riskId,
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk();
        $flowId = (string) $flowCreate->json('data.id');

        $this->patchJson("/api/v1/privacy/data-flows/{$flowId}", [
            ...$base,
            'title' => 'API Privacy Data Flow Updated',
            'source' => 'CRM',
            'destination' => 'Support tooling and BI',
            'data_category_summary' => 'Customer profile, incident context, and trend aggregates',
            'transfer_type' => 'vendor',
            'review_due_on' => '2026-06-15',
            'linked_asset_id' => $assetId,
            'linked_risk_id' => $riskId,
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk()
            ->assertJsonPath('data.title', 'API Privacy Data Flow Updated');

        $this->post("/api/v1/privacy/data-flows/{$flowId}/artifacts", [
            ...$base,
            'label' => 'Data flow record',
            'artifact_type' => 'record',
            'artifact' => UploadedFile::fake()->createWithContent('privacy-flow.pdf', 'privacy flow evidence'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.subject_type', 'privacy-data-flow')
            ->assertJsonPath('data.subject_id', $flowId);

        $this->postJson("/api/v1/privacy/data-flows/{$flowId}/transitions/submit-review", $base)
            ->assertOk()
            ->assertJsonPath('data.transition', 'submit-review');

        $flowOwnerAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'privacy-data-flow')
            ->where('domain_object_id', $flowId)
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-ava-mason')
            ->where('is_active', true)
            ->value('id');
        $this->assertNotSame('', $flowOwnerAssignmentId);

        $this->patchJson("/api/v1/privacy/data-flows/{$flowId}/owners/{$flowOwnerAssignmentId}/remove", $base)
            ->assertOk()
            ->assertJsonPath('data.removed', true);

        $activityCreate = $this->postJson('/api/v1/privacy/activities', [
            ...$base,
            'title' => 'API Processing Activity',
            'purpose' => 'Handle customer support requests',
            'lawful_basis' => 'contract',
            'linked_data_flow_ids' => $flowId,
            'linked_risk_ids' => $riskId,
            'linked_policy_id' => $policyId,
            'linked_finding_id' => $findingId,
            'review_due_on' => '2026-06-20',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk();
        $activityId = (string) $activityCreate->json('data.id');

        $this->patchJson("/api/v1/privacy/activities/{$activityId}", [
            ...$base,
            'title' => 'API Processing Activity Updated',
            'purpose' => 'Handle customer support requests with expanded reporting',
            'lawful_basis' => 'contract',
            'linked_data_flow_ids' => $flowId,
            'linked_risk_ids' => $riskId,
            'linked_policy_id' => $policyId,
            'linked_finding_id' => $findingId,
            'review_due_on' => '2026-06-30',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk()
            ->assertJsonPath('data.title', 'API Processing Activity Updated');

        $this->post("/api/v1/privacy/activities/{$activityId}/artifacts", [
            ...$base,
            'label' => 'Processing activity record',
            'artifact_type' => 'record',
            'artifact' => UploadedFile::fake()->createWithContent('privacy-activity.pdf', 'privacy activity evidence'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.subject_type', 'privacy-processing-activity')
            ->assertJsonPath('data.subject_id', $activityId);

        $this->postJson("/api/v1/privacy/activities/{$activityId}/transitions/submit-review", $base)
            ->assertOk()
            ->assertJsonPath('data.transition', 'submit-review');

        $activityOwnerAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'privacy-processing-activity')
            ->where('domain_object_id', $activityId)
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-ava-mason')
            ->where('is_active', true)
            ->value('id');
        $this->assertNotSame('', $activityOwnerAssignmentId);

        $this->patchJson("/api/v1/privacy/activities/{$activityId}/owners/{$activityOwnerAssignmentId}/remove", $base)
            ->assertOk()
            ->assertJsonPath('data.removed', true);
    }

    public function test_continuity_service_and_plan_api_lifecycle_endpoints_work(): void
    {
        Storage::fake('local');

        $base = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
            'scope_id' => 'scope-eu',
        ];

        $assetCreate = $this->postJson('/api/v1/assets', [
            ...$base,
            'name' => 'API Continuity Linked Asset',
            'type' => 'application',
            'criticality' => 'high',
            'classification' => 'internal',
        ])->assertOk();
        $assetId = (string) $assetCreate->json('data.id');

        $riskCreate = $this->postJson('/api/v1/risks', [
            ...$base,
            'title' => 'API Continuity Linked Risk',
            'category' => 'operations',
            'inherent_score' => 41,
            'residual_score' => 20,
            'treatment' => 'Reduce restore-time variance',
            'linked_asset_id' => $assetId,
        ])->assertOk();
        $riskId = (string) $riskCreate->json('data.id');

        $policyCreate = $this->postJson('/api/v1/policies', [
            ...$base,
            'title' => 'API Continuity Linked Policy',
            'area' => 'identity',
            'version_label' => 'v1.0',
            'statement' => 'Policy used for continuity plan linking',
        ])->assertOk();
        $policyId = (string) $policyCreate->json('data.id');

        $findingCreate = $this->postJson('/api/v1/findings', [
            ...$base,
            'title' => 'API Continuity Linked Finding',
            'severity' => 'high',
            'description' => 'Finding used for continuity plan linking',
        ])->assertOk();
        $findingId = (string) $findingCreate->json('data.id');

        $serviceCreate = $this->postJson('/api/v1/continuity/services', [
            ...$base,
            'title' => 'API Continuity Service',
            'impact_tier' => 'critical',
            'recovery_time_objective_hours' => 6,
            'recovery_point_objective_hours' => 2,
            'linked_asset_id' => $assetId,
            'linked_risk_id' => $riskId,
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk();
        $serviceId = (string) $serviceCreate->json('data.id');

        $dependencyTargetCreate = $this->postJson('/api/v1/continuity/services', [
            ...$base,
            'title' => 'API Continuity Dependency Service',
            'impact_tier' => 'critical',
            'recovery_time_objective_hours' => 8,
            'recovery_point_objective_hours' => 4,
            'linked_asset_id' => $assetId,
            'linked_risk_id' => $riskId,
        ])->assertOk();
        $dependencyTargetId = (string) $dependencyTargetCreate->json('data.id');

        $this->patchJson("/api/v1/continuity/services/{$serviceId}", [
            ...$base,
            'title' => 'API Continuity Service Updated',
            'impact_tier' => 'critical',
            'recovery_time_objective_hours' => 4,
            'recovery_point_objective_hours' => 1,
            'linked_asset_id' => $assetId,
            'linked_risk_id' => $riskId,
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk()
            ->assertJsonPath('data.title', 'API Continuity Service Updated');

        $this->postJson("/api/v1/continuity/services/{$serviceId}/dependencies", [
            ...$base,
            'depends_on_service_id' => $dependencyTargetId,
            'dependency_kind' => 'critical',
            'recovery_notes' => 'Primary service relies on backup restore orchestration',
        ])->assertOk()
            ->assertJsonPath('data.depends_on_service_id', $dependencyTargetId);

        $this->post("/api/v1/continuity/services/{$serviceId}/artifacts", [
            ...$base,
            'label' => 'Continuity service evidence',
            'artifact_type' => 'continuity-record',
            'artifact' => UploadedFile::fake()->createWithContent('continuity-service.pdf', 'continuity service evidence'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.subject_type', 'continuity-service')
            ->assertJsonPath('data.subject_id', $serviceId);

        $this->postJson("/api/v1/continuity/services/{$serviceId}/transitions/submit-review", $base)
            ->assertOk()
            ->assertJsonPath('data.transition', 'submit-review');

        $serviceOwnerAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'continuity-service')
            ->where('domain_object_id', $serviceId)
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-ava-mason')
            ->where('is_active', true)
            ->value('id');
        $this->assertNotSame('', $serviceOwnerAssignmentId);

        $this->patchJson("/api/v1/continuity/services/{$serviceId}/owners/{$serviceOwnerAssignmentId}/remove", $base)
            ->assertOk()
            ->assertJsonPath('data.removed', true);

        $planCreate = $this->postJson("/api/v1/continuity/services/{$serviceId}/plans", [
            ...$base,
            'title' => 'API Recovery Plan',
            'strategy_summary' => 'Switch to backup pathway and validate controls',
            'test_due_on' => '2026-07-01',
            'linked_policy_id' => $policyId,
            'linked_finding_id' => $findingId,
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk();
        $planId = (string) $planCreate->json('data.id');

        $this->patchJson("/api/v1/continuity/plans/{$planId}", [
            ...$base,
            'title' => 'API Recovery Plan Updated',
            'strategy_summary' => 'Switch to backup pathway and verify operational readiness',
            'test_due_on' => '2026-07-10',
            'linked_policy_id' => $policyId,
            'linked_finding_id' => $findingId,
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertOk()
            ->assertJsonPath('data.title', 'API Recovery Plan Updated');

        $this->postJson("/api/v1/continuity/plans/{$planId}/exercises", [
            ...$base,
            'exercise_date' => '2026-06-01',
            'exercise_type' => 'tabletop',
            'scenario_summary' => 'Cross-team outage escalation drill',
            'outcome' => 'partial',
            'follow_up_summary' => 'Needs deeper failover rehearsal',
        ])->assertOk()
            ->assertJsonPath('data.plan_id', $planId);

        $this->postJson("/api/v1/continuity/plans/{$planId}/executions", [
            ...$base,
            'executed_on' => '2026-06-05',
            'execution_type' => 'recovery-drill',
            'status' => 'passed',
            'participants' => 'Support Leads, Recovery Ops',
            'notes' => 'RTO stayed within target window',
        ])->assertOk()
            ->assertJsonPath('data.plan_id', $planId)
            ->assertJsonPath('data.status', 'passed');

        $this->post("/api/v1/continuity/plans/{$planId}/artifacts", [
            ...$base,
            'label' => 'Recovery plan evidence',
            'artifact_type' => 'recovery-plan',
            'artifact' => UploadedFile::fake()->createWithContent('continuity-plan.pdf', 'continuity plan evidence'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.subject_type', 'continuity-plan')
            ->assertJsonPath('data.subject_id', $planId);

        $this->postJson("/api/v1/continuity/plans/{$planId}/transitions/submit-review", $base)
            ->assertOk()
            ->assertJsonPath('data.transition', 'submit-review');

        $planOwnerAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'continuity-plan')
            ->where('domain_object_id', $planId)
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-ava-mason')
            ->where('is_active', true)
            ->value('id');
        $this->assertNotSame('', $planOwnerAssignmentId);

        $this->patchJson("/api/v1/continuity/plans/{$planId}/owners/{$planOwnerAssignmentId}/remove", $base)
            ->assertOk()
            ->assertJsonPath('data.removed', true);
    }

    public function test_automation_catalog_api_lifecycle_repository_mapping_and_runtime_endpoints_work(): void
    {
        Storage::fake('local');

        $base = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-hello',
            'scope_id' => 'scope-eu',
        ];

        $packCreate = $this->postJson('/api/v1/automation-catalog/packs', [
            ...$base,
            'pack_key' => 'connector.google.workspace-baseline-api',
            'name' => 'Google Workspace Baseline via API',
            'summary' => 'Pack managed through automation catalog API endpoints.',
            'version' => '0.1.0',
            'provider_type' => 'community',
            'source_ref' => 'https://repository.pimesec.com/connector.google.workspace-baseline/',
            'provenance_type' => 'manual',
        ])->assertOk()
            ->assertJsonPath('data.pack_key', 'connector.google.workspace-baseline-api')
            ->assertJsonPath('data.lifecycle_state', 'discovered');
        $packId = (string) $packCreate->json('data.id');
        $this->assertNotSame('', $packId);

        $this->getJson('/api/v1/automation-catalog/packs?'.http_build_query($base))
            ->assertOk()
            ->assertJsonFragment(['id' => $packId]);

        $this->postJson("/api/v1/automation-catalog/packs/{$packId}/install", $base)
            ->assertOk()
            ->assertJsonPath('data.is_installed', '1');

        $this->postJson("/api/v1/automation-catalog/packs/{$packId}/enable", $base)
            ->assertOk()
            ->assertJsonPath('data.is_enabled', '1');

        $this->postJson("/api/v1/automation-catalog/packs/{$packId}/health", [
            ...$base,
            'health_state' => 'failing',
            'last_failure_reason' => 'Connector token rejected by upstream API.',
        ])->assertOk()
            ->assertJsonPath('data.health_state', 'failing');

        $this->postJson("/api/v1/automation-catalog/packs/{$packId}/schedule", [
            ...$base,
            'runtime_schedule_enabled' => true,
            'runtime_schedule_cron' => '0 */6 * * *',
            'runtime_schedule_timezone' => 'Europe/Madrid',
        ])->assertOk()
            ->assertJsonPath('data.runtime_schedule_enabled', '1')
            ->assertJsonPath('data.runtime_schedule_cron', '0 */6 * * *');

        $mappingCreate = $this->postJson("/api/v1/automation-catalog/packs/{$packId}/output-mappings", [
            ...$base,
            'mapping_label' => 'API evidence refresh mapping',
            'mapping_kind' => 'evidence-refresh',
            'target_binding_mode' => 'explicit',
            'target_subject_type' => 'control',
            'target_subject_id' => 'control-access-review',
            'evidence_policy' => 'always',
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.mapping_kind', 'evidence-refresh');
        $mappingId = (string) $mappingCreate->json('data.id');
        $this->assertNotSame('', $mappingId);

        $this->post("/api/v1/automation-catalog/packs/{$packId}/output-mappings/{$mappingId}/apply", [
            ...$base,
            'evidence_kind' => 'report',
            'output_file' => UploadedFile::fake()->create('automation-output.txt', 8, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.status', 'success');

        $this->postJson("/api/v1/automation-catalog/packs/{$packId}/run", $base)
            ->assertOk()
            ->assertJsonPath('data.automation_pack_id', $packId);
        $this->assertDatabaseHas('automation_pack_runs', [
            'automation_pack_id' => $packId,
            'trigger_mode' => 'manual',
        ]);

        $repositoryCreate = $this->postJson('/api/v1/automation-catalog/repositories', [
            ...$base,
            'label' => 'Automation API test repository',
            'repository_url' => 'https://repository.pimesec.com/repository.json',
            'repository_sign_url' => 'https://repository.pimesec.com/repository.json.sign',
            'public_key_pem' => "-----BEGIN PUBLIC KEY-----\nTEST\n-----END PUBLIC KEY-----",
            'trust_tier' => 'community-reviewed',
            'is_enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.label', 'Automation API test repository');
        $repositoryId = (string) $repositoryCreate->json('data.id');
        $this->assertNotSame('', $repositoryId);
        $this->assertDatabaseHas('automation_pack_repositories', [
            'id' => $repositoryId,
            'organization_id' => 'org-a',
        ]);

        $this->getJson('/api/v1/automation-catalog/repositories?'.http_build_query($base))
            ->assertOk()
            ->assertJsonFragment(['id' => $repositoryId]);

        $this->getJson('/api/v1/automation-catalog/lookups/scopes/options?'.http_build_query($base))
            ->assertOk()
            ->assertJsonFragment(['id' => 'scope-eu']);

        $this->postJson("/api/v1/automation-catalog/packs/{$packId}/disable", $base)
            ->assertOk()
            ->assertJsonPath('data.is_enabled', '0');

        $this->postJson("/api/v1/automation-catalog/packs/{$packId}/uninstall", $base)
            ->assertOk()
            ->assertJsonPath('data.uninstalled', true);

        $this->assertDatabaseMissing('automation_packs', ['id' => $packId]);
        $this->assertDatabaseMissing('automation_pack_output_mappings', ['automation_pack_id' => $packId]);
    }

    public function test_openapi_endpoint_and_generation_command_work(): void
    {
        $response = $this->get('/openapi.json')
            ->assertOk()
            ->assertHeader('X-PymeSec-OpenApi-Version', 'v1')
            ->assertHeader('X-PymeSec-OpenApi-Compat', 'minor-compatible')
            ->assertSee('coreGetCapabilities')
            ->assertSee('coreListApiTokens')
            ->assertSee('coreIssueApiToken')
            ->assertSee('coreRotateApiToken')
            ->assertSee('coreRevokeApiToken')
            ->assertSee('functionalActorsArchiveActor')
            ->assertSee('referenceDataListFunctionalActorKindOptions')
            ->assertSee('identityLocalListUsers')
            ->assertSee('thirdPartyRiskListVendors')
            ->assertSee('thirdPartyRiskCreateVendorWithReview')
            ->assertSee('thirdPartyRiskUpdateVendorWithReview')
            ->assertSee('thirdPartyRiskIssueExternalLink')
            ->assertSee('thirdPartyRiskCreateVendorReviewDraft')
            ->assertSee('thirdPartyRiskRemoveVendorReviewOwner')
            ->assertSee('thirdPartyRiskAttachVendorReviewArtifact')
            ->assertSee('thirdPartyRiskAttachQuestionnaireItemArtifact')
            ->assertSee('thirdPartyRiskTransitionReview')
            ->assertSee('assetCatalogListAssets')
            ->assertSee('assetCatalogTransitionAsset')
            ->assertSee('riskManagementListRisks')
            ->assertSee('riskManagementTransitionRisk')
            ->assertSee('controlsCatalogListControls')
            ->assertSee('controlsCatalogTransitionControl')
            ->assertSee('assessmentsAuditsListAssessments')
            ->assertSee('assessmentsAuditsUpdateAssessmentReview')
            ->assertSee('assessmentsAuditsRemoveAssessmentOwner')
            ->assertSee('assessmentsAuditsAttachAssessmentReviewArtifact')
            ->assertSee('assessmentsAuditsCreateReviewFinding')
            ->assertSee('assessmentsAuditsTransitionAssessment')
            ->assertSee('policyExceptionsCreatePolicy')
            ->assertSee('policyExceptionsTransitionException')
            ->assertSee('dataFlowsPrivacyCreateDataFlow')
            ->assertSee('dataFlowsPrivacyTransitionProcessingActivity')
            ->assertSee('continuityBcmCreateService')
            ->assertSee('continuityBcmTransitionPlan')
            ->assertSee('findingsRemediationListFindings')
            ->assertSee('findingsRemediationTransitionFinding')
            ->assertSee('findingsRemediationCreateAction')
            ->assertSee('findingsRemediationUpdateAction')
            ->assertSee('automationCatalogListPacks')
            ->assertSee('automationCatalogCreatePack')
            ->assertSee('automationCatalogRunPack')
            ->assertSee('automationCatalogSaveRepository')
            ->assertSee('automationCatalogCreateOutputMapping')
            ->assertSee('automationCatalogApplyOutputMapping');

        $openApi = $response->json();
        $this->assertIsArray($openApi);
        $this->assertSame('v1', $openApi['x-contract-version'] ?? null);
        $this->assertSame(
            'object',
            data_get($openApi, 'paths./assets.post.requestBody.content.application/json.schema.type'),
        );
        $this->assertSame(
            'assets.types',
            data_get($openApi, 'paths./assets.post.requestBody.content.application/json.schema.properties.type.x-governed-catalog'),
        );
        $this->assertSame(
            '/api/v1/lookups/reference-catalogs/assets.types/options',
            data_get($openApi, 'paths./assets.post.requestBody.content.application/json.schema.properties.type.x-governed-source'),
        );
        $this->assertContains(
            'name',
            data_get($openApi, 'paths./assets.post.requestBody.content.application/json.schema.required', []),
        );
        $this->assertSame(
            '/api/v1/lookups/actors/options',
            data_get($openApi, 'paths./assets.post.x-lookup-fields.owner_actor_id.source'),
        );
        $this->assertContains(
            'employee',
            data_get($openApi, 'paths./core/functional-actors.post.requestBody.content.application/json.schema.properties.kind.enum', []),
        );
        $this->assertSame(
            'object',
            data_get($openApi, 'paths./core/functional-actors.post.requestBody.content.application/json.schema.properties.metadata.type.0'),
        );
        $this->assertSame(
            'functionalActorsArchiveActor',
            data_get($openApi, 'paths./core/functional-actors/{actorId}/archive.post.operationId'),
        );
        $this->assertSame(
            'identityLocalListUsers',
            data_get($openApi, 'paths./identity-local/users.get.operationId'),
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
            '/api/v1/lookups/reference-catalogs/assessments.status/options',
            data_get($openApi, 'paths./assessments.post.requestBody.content.application/json.schema.properties.status.x-governed-source'),
        );
        $this->assertSame(
            'findings.severity',
            data_get($openApi, 'paths./findings.post.requestBody.content.application/json.schema.properties.severity.x-governed-catalog'),
        );
        $this->assertSame(
            '/api/v1/lookups/reference-catalogs/findings.severity/options',
            data_get($openApi, 'paths./findings.post.requestBody.content.application/json.schema.properties.severity.x-governed-source'),
        );
        $this->assertSame(
            'findings.remediation_status',
            data_get($openApi, 'paths./findings/{findingId}/actions.post.requestBody.content.application/json.schema.properties.status.x-governed-catalog'),
        );
        $this->assertSame(
            '/api/v1/lookups/reference-catalogs/findings.remediation_status/options',
            data_get($openApi, 'paths./findings/{findingId}/actions.post.requestBody.content.application/json.schema.properties.status.x-governed-source'),
        );
        $this->assertSame(
            'assessments.review_result',
            data_get($openApi, 'paths./assessments/{assessmentId}/reviews/{controlId}.patch.requestBody.content.application/json.schema.properties.result.x-governed-catalog'),
        );
        $this->assertSame(
            '/api/v1/lookups/reference-catalogs/assessments.review_result/options',
            data_get($openApi, 'paths./assessments/{assessmentId}/reviews/{controlId}.patch.requestBody.content.application/json.schema.properties.result.x-governed-source'),
        );

        $versionedResponse = $this->get('/openapi/v1.json')
            ->assertOk()
            ->assertHeader('X-PymeSec-OpenApi-Version', 'v1')
            ->assertSee('coreIssueApiToken');

        $versionedOpenApi = $versionedResponse->json();
        $this->assertIsArray($versionedOpenApi);
        $this->assertSame('v1', $versionedOpenApi['x-contract-version'] ?? null);
        $this->assertSame(
            data_get($openApi, 'paths./api-tokens.post.operationId'),
            data_get($versionedOpenApi, 'paths./api-tokens.post.operationId'),
        );

        $output = base_path('storage/framework/testing/openapi.test.json');
        File::delete($output);

        $this->artisan('openapi:generate', ['--output' => $output])
            ->assertExitCode(0);

        $this->assertFileExists($output);
        $this->assertStringContainsString('assetCatalogListAssets', (string) File::get($output));

        $publishDir = base_path('storage/framework/testing/openapi.publish');
        File::deleteDirectory($publishDir);

        $this->artisan('openapi:publish', [
            '--output-dir' => 'storage/framework/testing/openapi.publish',
        ])->assertExitCode(0);

        $this->assertFileExists($publishDir.'/openapi.json');
        $this->assertFileExists($publishDir.'/openapi/v1.json');
        $this->assertSame((string) File::get($publishDir.'/openapi/v1.json'), (string) File::get($publishDir.'/openapi.json'));
        $this->assertStringNotContainsString('"x-generated-at"', (string) File::get($publishDir.'/openapi.json'));

        $this->artisan('openapi:publish', [
            '--output-dir' => 'storage/framework/testing/openapi.publish',
            '--check' => true,
        ])->assertExitCode(0);
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
            'core.api-tokens.issue' => 'coreIssueApiToken',
            'core.api-tokens.rotate' => 'coreRotateApiToken',
            'core.api-tokens.revoke' => 'coreRevokeApiToken',
            'core.plugins.enable' => 'coreEnablePlugin',
            'core.plugins.disable' => 'coreDisablePlugin',
            'core.roles.store' => 'coreCreateRole',
            'core.grants.store' => 'coreCreateRoleGrant',
            'core.grants.update' => 'coreUpdateRoleGrant',
            'core.reference-data.entries.store' => 'referenceDataCreateEntry',
            'core.reference-data.entries.update' => 'referenceDataUpdateEntry',
            'core.reference-data.entries.archive' => 'referenceDataArchiveEntry',
            'core.reference-data.entries.activate' => 'referenceDataActivateEntry',
            'core.tenancy.organizations.store' => 'tenancyCreateOrganization',
            'core.tenancy.organizations.update' => 'tenancyUpdateOrganization',
            'core.tenancy.organizations.archive' => 'tenancyArchiveOrganization',
            'core.tenancy.organizations.activate' => 'tenancyActivateOrganization',
            'core.tenancy.scopes.store' => 'tenancyCreateScope',
            'core.tenancy.scopes.update' => 'tenancyUpdateScope',
            'core.tenancy.scopes.archive' => 'tenancyArchiveScope',
            'core.tenancy.scopes.activate' => 'tenancyActivateScope',
            'core.functional-actors.store' => 'functionalActorsCreateActor',
            'core.functional-actors.links.store' => 'functionalActorsLinkPrincipal',
            'core.functional-actors.assignments.store' => 'functionalActorsCreateAssignment',
            'core.object-access.assignments.store' => 'objectAccessCreateAssignment',
            'core.object-access.assignments.deactivate' => 'objectAccessDeactivateAssignment',
            'core.notifications.settings.update' => 'notificationsUpdateMailSettings',
            'core.notifications.test.send' => 'notificationsSendTestEmail',
            'core.notifications.templates.update' => 'notificationsUpdateTemplate',
            'plugin.third-party-risk.store' => 'thirdPartyRiskCreateVendorWithReview',
            'plugin.third-party-risk.update' => 'thirdPartyRiskUpdateVendorWithReview',
            'plugin.third-party-risk.external.links.issue' => 'thirdPartyRiskIssueExternalLink',
            'plugin.third-party-risk.external.links.revoke' => 'thirdPartyRiskRevokeExternalLink',
            'plugin.third-party-risk.external.collaborators.update' => 'thirdPartyRiskUpdateExternalCollaboratorLifecycle',
            'plugin.third-party-risk.collaboration.drafts.store' => 'thirdPartyRiskCreateVendorReviewDraft',
            'plugin.third-party-risk.collaboration.drafts.update' => 'thirdPartyRiskUpdateVendorReviewDraft',
            'plugin.third-party-risk.collaboration.drafts.promote-comment' => 'thirdPartyRiskPromoteVendorReviewDraftToComment',
            'plugin.third-party-risk.collaboration.drafts.promote-request' => 'thirdPartyRiskPromoteVendorReviewDraftToRequest',
            'plugin.third-party-risk.collaboration.comments.store' => 'thirdPartyRiskAddVendorReviewComment',
            'plugin.third-party-risk.collaboration.requests.store' => 'thirdPartyRiskCreateVendorReviewRequest',
            'plugin.third-party-risk.collaboration.requests.update' => 'thirdPartyRiskUpdateVendorReviewRequest',
            'plugin.third-party-risk.brokered-requests.issue' => 'thirdPartyRiskIssueBrokeredRequest',
            'plugin.third-party-risk.brokered-requests.update' => 'thirdPartyRiskUpdateBrokeredRequest',
            'plugin.third-party-risk.owners.destroy' => 'thirdPartyRiskRemoveVendorReviewOwner',
            'plugin.third-party-risk.artifacts.store' => 'thirdPartyRiskAttachVendorReviewArtifact',
            'plugin.third-party-risk.questionnaire-items.store' => 'thirdPartyRiskAddQuestionnaireItem',
            'plugin.third-party-risk.questionnaire-items.update' => 'thirdPartyRiskUpdateQuestionnaireItem',
            'plugin.third-party-risk.questionnaire-items.artifacts.store' => 'thirdPartyRiskAttachQuestionnaireItemArtifact',
            'plugin.third-party-risk.questionnaire-items.review' => 'thirdPartyRiskReviewQuestionnaireItem',
            'plugin.third-party-risk.questionnaire-items.apply-template' => 'thirdPartyRiskApplyQuestionnaireTemplate',
            'plugin.third-party-risk.transition' => 'thirdPartyRiskTransitionReview',
            'plugin.third-party-risk.external.questionnaire-items.update' => 'thirdPartyRiskExternalSubmitQuestionnaireAnswer',
            'plugin.third-party-risk.external.questionnaire-items.artifacts.store' => 'thirdPartyRiskExternalAttachQuestionnaireArtifact',
            'plugin.third-party-risk.external.artifacts.store' => 'thirdPartyRiskExternalAttachReviewArtifact',
            'plugin.automation-catalog.store' => 'automationCatalogCreatePack',
            'plugin.automation-catalog.install' => 'automationCatalogInstallPack',
            'plugin.automation-catalog.enable' => 'automationCatalogEnablePack',
            'plugin.automation-catalog.disable' => 'automationCatalogDisablePack',
            'plugin.automation-catalog.uninstall' => 'automationCatalogUninstallPack',
            'plugin.automation-catalog.health.update' => 'automationCatalogUpdatePackHealth',
            'plugin.automation-catalog.schedule.update' => 'automationCatalogUpdatePackSchedule',
            'plugin.automation-catalog.run' => 'automationCatalogRunPack',
            'plugin.automation-catalog.repositories.store' => 'automationCatalogSaveRepository',
            'plugin.automation-catalog.repositories.install-official' => 'automationCatalogInstallOfficialRepository',
            'plugin.automation-catalog.repositories.refresh' => 'automationCatalogRefreshRepository',
            'plugin.automation-catalog.output-mappings.store' => 'automationCatalogCreateOutputMapping',
            'plugin.automation-catalog.output-mappings.apply' => 'automationCatalogApplyOutputMapping',
            'plugin.asset-catalog.store' => 'assetCatalogCreateAsset',
            'plugin.asset-catalog.update' => 'assetCatalogUpdateAsset',
            'plugin.asset-catalog.owners.destroy' => 'assetCatalogRemoveAssetOwner',
            'plugin.asset-catalog.transition' => 'assetCatalogTransitionAsset',
            'plugin.risk-management.store' => 'riskManagementCreateRisk',
            'plugin.risk-management.update' => 'riskManagementUpdateRisk',
            'plugin.risk-management.owners.destroy' => 'riskManagementRemoveRiskOwner',
            'plugin.risk-management.artifacts.store' => 'riskManagementAttachRiskArtifact',
            'plugin.risk-management.transition' => 'riskManagementTransitionRisk',
            'plugin.controls-catalog.store' => 'controlsCatalogCreateControl',
            'plugin.controls-catalog.update' => 'controlsCatalogUpdateControl',
            'plugin.controls-catalog.owners.destroy' => 'controlsCatalogRemoveControlOwner',
            'plugin.controls-catalog.artifacts.store' => 'controlsCatalogAttachControlArtifact',
            'plugin.controls-catalog.transition' => 'controlsCatalogTransitionControl',
            'plugin.controls-catalog.frameworks.store' => 'controlsCatalogCreateFramework',
            'plugin.controls-catalog.frameworks.adoption.upsert' => 'controlsCatalogUpsertFrameworkAdoption',
            'plugin.controls-catalog.frameworks.onboarding.apply' => 'controlsCatalogApplyFrameworkOnboardingKit',
            'plugin.controls-catalog.requirements.store' => 'controlsCatalogCreateRequirement',
            'plugin.controls-catalog.requirements.attach' => 'controlsCatalogAttachRequirement',
            'plugin.assessments-audits.store' => 'assessmentsAuditsCreateAssessment',
            'plugin.assessments-audits.update' => 'assessmentsAuditsUpdateAssessment',
            'plugin.assessments-audits.owners.destroy' => 'assessmentsAuditsRemoveAssessmentOwner',
            'plugin.assessments-audits.reviews.update' => 'assessmentsAuditsUpdateAssessmentReview',
            'plugin.assessments-audits.reviews.artifacts.store' => 'assessmentsAuditsAttachAssessmentReviewArtifact',
            'plugin.assessments-audits.reviews.findings.store' => 'assessmentsAuditsCreateReviewFinding',
            'plugin.assessments-audits.transition' => 'assessmentsAuditsTransitionAssessment',
            'plugin.policy-exceptions.store' => 'policyExceptionsCreatePolicy',
            'plugin.policy-exceptions.update' => 'policyExceptionsUpdatePolicy',
            'plugin.policy-exceptions.owners.destroy' => 'policyExceptionsRemovePolicyOwner',
            'plugin.policy-exceptions.exceptions.store' => 'policyExceptionsCreateException',
            'plugin.policy-exceptions.exceptions.update' => 'policyExceptionsUpdateException',
            'plugin.policy-exceptions.exceptions.owners.destroy' => 'policyExceptionsRemoveExceptionOwner',
            'plugin.policy-exceptions.artifacts.store' => 'policyExceptionsAttachPolicyArtifact',
            'plugin.policy-exceptions.exceptions.artifacts.store' => 'policyExceptionsAttachExceptionArtifact',
            'plugin.policy-exceptions.transition' => 'policyExceptionsTransitionPolicy',
            'plugin.policy-exceptions.exceptions.transition' => 'policyExceptionsTransitionException',
            'plugin.data-flows-privacy.store' => 'dataFlowsPrivacyCreateDataFlow',
            'plugin.data-flows-privacy.update' => 'dataFlowsPrivacyUpdateDataFlow',
            'plugin.data-flows-privacy.owners.destroy' => 'dataFlowsPrivacyRemoveDataFlowOwner',
            'plugin.data-flows-privacy.artifacts.store' => 'dataFlowsPrivacyAttachDataFlowArtifact',
            'plugin.data-flows-privacy.transition' => 'dataFlowsPrivacyTransitionDataFlow',
            'plugin.data-flows-privacy.activities.store' => 'dataFlowsPrivacyCreateProcessingActivity',
            'plugin.data-flows-privacy.activities.update' => 'dataFlowsPrivacyUpdateProcessingActivity',
            'plugin.data-flows-privacy.activities.owners.destroy' => 'dataFlowsPrivacyRemoveProcessingActivityOwner',
            'plugin.data-flows-privacy.activities.artifacts.store' => 'dataFlowsPrivacyAttachProcessingActivityArtifact',
            'plugin.data-flows-privacy.activities.transition' => 'dataFlowsPrivacyTransitionProcessingActivity',
            'plugin.continuity-bcm.store' => 'continuityBcmCreateService',
            'plugin.continuity-bcm.update' => 'continuityBcmUpdateService',
            'plugin.continuity-bcm.dependencies.store' => 'continuityBcmAddServiceDependency',
            'plugin.continuity-bcm.owners.destroy' => 'continuityBcmRemoveServiceOwner',
            'plugin.continuity-bcm.artifacts.store' => 'continuityBcmAttachServiceArtifact',
            'plugin.continuity-bcm.transition' => 'continuityBcmTransitionService',
            'plugin.continuity-bcm.plans.store' => 'continuityBcmCreatePlan',
            'plugin.continuity-bcm.plans.update' => 'continuityBcmUpdatePlan',
            'plugin.continuity-bcm.plans.exercises.store' => 'continuityBcmRecordPlanExercise',
            'plugin.continuity-bcm.plans.executions.store' => 'continuityBcmRecordPlanExecution',
            'plugin.continuity-bcm.plans.owners.destroy' => 'continuityBcmRemovePlanOwner',
            'plugin.continuity-bcm.plans.artifacts.store' => 'continuityBcmAttachPlanArtifact',
            'plugin.continuity-bcm.plans.transition' => 'continuityBcmTransitionPlan',
            'plugin.findings-remediation.store' => 'findingsRemediationCreateFinding',
            'plugin.findings-remediation.update' => 'findingsRemediationUpdateFinding',
            'plugin.findings-remediation.owners.destroy' => 'findingsRemediationRemoveFindingOwner',
            'plugin.findings-remediation.actions.owners.destroy' => 'findingsRemediationRemoveActionOwner',
            'plugin.findings-remediation.artifacts.store' => 'findingsRemediationAttachFindingArtifact',
            'plugin.findings-remediation.transition' => 'findingsRemediationTransitionFinding',
            'plugin.findings-remediation.actions.store' => 'findingsRemediationCreateAction',
            'plugin.findings-remediation.actions.update' => 'findingsRemediationUpdateAction',
            'plugin.evidence-management.store' => 'evidenceManagementCreateEvidence',
            'plugin.evidence-management.update' => 'evidenceManagementUpdateEvidence',
            'plugin.evidence-management.promote' => 'evidenceManagementPromoteArtifact',
            'plugin.evidence-management.reminders.queue' => 'evidenceManagementQueueReminder',
            'plugin.identity-local.users.store' => 'identityLocalCreateUser',
            'plugin.identity-local.users.update' => 'identityLocalUpdateUser',
            'plugin.identity-local.users.delete' => 'identityLocalDeleteUser',
            'plugin.identity-local.memberships.store' => 'identityLocalCreateMembership',
            'plugin.identity-local.memberships.update' => 'identityLocalUpdateMembership',
            'plugin.identity-ldap.connection.store' => 'identityLdapSaveConnection',
            'plugin.identity-ldap.mappings.store' => 'identityLdapSaveGroupMapping',
            'plugin.identity-ldap.sync.store' => 'identityLdapRunSync',
            'plugin.identity-local.setup.store' => 'identityLocalBootstrapSetup',
            'plugin.identity-local.auth.request' => 'identityLocalAuthRequest',
            'plugin.identity-local.auth.verify.consume' => 'identityLocalAuthVerifyCode',
            'plugin.identity-local.auth.logout' => 'identityLocalAuthLogout',
            'plugin.identity-local.users.import.upload' => 'identityLocalUploadUsersImport',
            'plugin.identity-local.users.import.reset' => 'identityLocalResetUsersImport',
            'plugin.identity-local.users.import.review' => 'identityLocalReviewUsersImport',
            'plugin.identity-local.users.import.commit' => 'identityLocalCommitUsersImport',
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

                foreach ($lookupFields as $field => $lookupDefinition) {
                    if (! is_string($field) || $field === '') {
                        continue;
                    }

                    $this->assertArrayHasKey(
                        $field,
                        $properties,
                        sprintf('OpenAPI operation [%s %s] declares x-lookup-fields for unknown contract field [%s].', strtoupper((string) $method), $path, $field),
                    );

                    $lookupSource = data_get($lookupDefinition, 'source');
                    $this->assertIsString(
                        $lookupSource,
                        sprintf('OpenAPI operation [%s %s] must declare lookup source for [%s].', strtoupper((string) $method), $path, $field),
                    );
                    $this->assertNotSame('', trim((string) $lookupSource));
                }

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

    public function test_lookup_sources_referenced_by_write_contracts_are_runtime_reachable_with_option_shape(): void
    {
        $openApi = $this->get('/openapi.json')->assertOk()->json();
        $paths = is_array($openApi['paths'] ?? null) ? $openApi['paths'] : [];
        $sources = [];

        foreach ($paths as $path => $operations) {
            if (! is_string($path) || ! is_array($operations)) {
                continue;
            }

            foreach ($operations as $method => $operation) {
                if (! is_string($method) || ! in_array(strtolower($method), ['post', 'put', 'patch'], true) || ! is_array($operation)) {
                    continue;
                }

                $lookupFields = is_array($operation['x-lookup-fields'] ?? null) ? $operation['x-lookup-fields'] : [];

                foreach ($lookupFields as $field => $lookupDefinition) {
                    if (! is_string($field) || ! is_array($lookupDefinition)) {
                        continue;
                    }

                    $lookupSource = $lookupDefinition['source'] ?? null;
                    if (is_string($lookupSource) && trim($lookupSource) !== '') {
                        $sources[] = trim($lookupSource);
                    }
                }
            }
        }

        $sources = array_values(array_unique($sources));
        $this->assertNotSame([], $sources, 'Expected at least one x-lookup-fields source referenced by write operations.');

        foreach ($sources as $source) {
            $lookupEndpoint = $this->normalizeLookupSourceToApiUrl($source);
            $query = $this->lookupRequestQueryForSource($lookupEndpoint);
            $response = $this->getJson($lookupEndpoint.'?'.http_build_query($query))->assertOk();

            $rows = $response->json('data');
            $this->assertIsArray($rows, sprintf('Lookup source [%s] must return data as an array.', $lookupEndpoint));

            foreach ($rows as $index => $row) {
                $this->assertIsArray($row, sprintf('Lookup source [%s] returned invalid option row at index [%s].', $lookupEndpoint, (string) $index));
                $this->assertIsString($row['id'] ?? null, sprintf('Lookup source [%s] option row [%s] must include string id.', $lookupEndpoint, (string) $index));
                $this->assertNotSame('', trim((string) ($row['id'] ?? '')), sprintf('Lookup source [%s] option row [%s] id cannot be empty.', $lookupEndpoint, (string) $index));

                if (str_contains($lookupEndpoint, '/lookups/')) {
                    $this->assertIsString($row['label'] ?? null, sprintf('Lookup source [%s] option row [%s] must include string label.', $lookupEndpoint, (string) $index));
                    $this->assertNotSame('', trim((string) ($row['label'] ?? '')), sprintf('Lookup source [%s] option row [%s] label cannot be empty.', $lookupEndpoint, (string) $index));

                    continue;
                }

                $this->assertTrue(
                    $this->hasReadableLookupValue($row),
                    sprintf('Lookup source [%s] option row [%s] must expose a readable text field (label/name/title/display_name).', $lookupEndpoint, (string) $index),
                );
            }
        }
    }

    public function test_governed_catalog_fields_expose_runtime_lookup_sources_in_openapi(): void
    {
        $openApi = $this->get('/openapi.json')->assertOk()->json();
        $paths = is_array($openApi['paths'] ?? null) ? $openApi['paths'] : [];

        $this->assertArrayHasKey(
            '/lookups/reference-catalogs/{catalogKey}/options',
            $paths,
            'OpenAPI must expose the governed catalog options lookup endpoint.',
        );
        $this->assertArrayHasKey(
            'get',
            $paths['/lookups/reference-catalogs/{catalogKey}/options'],
            'OpenAPI governed catalog lookup endpoint must expose GET.',
        );

        $governedCount = 0;

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

                foreach ($properties as $field => $schema) {
                    if (! is_string($field) || ! is_array($schema)) {
                        continue;
                    }

                    $catalogKey = $schema['x-governed-catalog'] ?? null;
                    if (! is_string($catalogKey) || trim($catalogKey) === '') {
                        continue;
                    }

                    $governedCount += 1;
                    $governedSource = $schema['x-governed-source'] ?? null;

                    $this->assertIsString(
                        $governedSource,
                        sprintf('OpenAPI operation [%s %s] governed field [%s] must declare x-governed-source.', strtoupper((string) $method), $path, $field),
                    );
                    $this->assertNotSame('', trim((string) $governedSource));

                    $governedSource = $this->normalizeLookupSourceToApiUrl((string) $governedSource);
                    $this->assertStringStartsWith('/api/v1/lookups/reference-catalogs/', $governedSource);
                    $this->assertStringEndsWith('/options', $governedSource);

                    if (preg_match('#^/api/v1/lookups/reference-catalogs/(.+)/options$#', $governedSource, $matches) === 1) {
                        $this->assertSame(
                            $catalogKey,
                            $matches[1] ?? '',
                            sprintf('OpenAPI operation [%s %s] governed field [%s] must point x-governed-source to catalog key [%s].', strtoupper((string) $method), $path, $field, $catalogKey),
                        );
                    }

                    $sourceOpenApiPath = $this->normalizeGovernedSourceToOpenApiPath($governedSource);
                    $this->assertArrayHasKey(
                        $sourceOpenApiPath,
                        $paths,
                        sprintf('OpenAPI operation [%s %s] governed source path [%s] was not found in OpenAPI paths.', strtoupper((string) $method), $path, $sourceOpenApiPath),
                    );
                    $this->assertArrayHasKey(
                        'get',
                        $paths[$sourceOpenApiPath],
                        sprintf('OpenAPI operation [%s %s] governed source [%s] must reference a GET endpoint.', strtoupper((string) $method), $path, $sourceOpenApiPath),
                    );

                    $response = $this->getJson($governedSource.'?'.http_build_query($this->lookupRequestQueryForSource($governedSource)))
                        ->assertOk();
                    $rows = $response->json('data.options');
                    $this->assertIsArray($rows, sprintf('Governed source [%s] must return options array.', $governedSource));

                    foreach ($rows as $index => $row) {
                        $this->assertIsArray($row, sprintf('Governed source [%s] returned invalid option at index [%s].', $governedSource, (string) $index));
                        $this->assertIsString($row['id'] ?? null, sprintf('Governed source [%s] option [%s] must include string id.', $governedSource, (string) $index));
                        $this->assertNotSame('', trim((string) ($row['id'] ?? '')), sprintf('Governed source [%s] option [%s] id cannot be empty.', $governedSource, (string) $index));
                        $this->assertIsString($row['label'] ?? null, sprintf('Governed source [%s] option [%s] must include string label.', $governedSource, (string) $index));
                        $this->assertNotSame('', trim((string) ($row['label'] ?? '')), sprintf('Governed source [%s] option [%s] label cannot be empty.', $governedSource, (string) $index));
                    }
                }
            }
        }

        $this->assertGreaterThan(
            0,
            $governedCount,
            'Expected at least one governed write field in OpenAPI contracts.',
        );
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

    private function normalizeLookupSourceToApiUrl(string $source): string
    {
        $source = trim($source);
        $path = '/'.ltrim($source, '/');

        if (str_starts_with($path, '/api/v1/')) {
            return $path;
        }

        if (str_starts_with($path, '/v1/')) {
            return '/api'.$path;
        }

        if (str_starts_with($path, '/lookups/')) {
            return '/api/v1'.$path;
        }

        return $path;
    }

    private function normalizeGovernedSourceToOpenApiPath(string $source): string
    {
        if (preg_match('#^/api/v1/lookups/reference-catalogs/.+/options$#', $source) === 1) {
            return '/lookups/reference-catalogs/{catalogKey}/options';
        }

        return $this->normalizeLookupSourceToOpenApiPath($source);
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupRequestQueryForSource(string $source): array
    {
        if (str_starts_with($source, '/api/v1/lookups/principals/options')) {
            return [
                'principal_id' => 'principal-admin',
            ];
        }

        $query = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'membership_ids' => ['membership-org-a-hello'],
        ];

        if (str_starts_with($source, '/api/v1/lookups/vendor-questionnaire-templates/options')) {
            $query['profile_id'] = 'vendor-review-profile-eu-payroll-processor';
        }

        return $query;
    }

    private function fieldRequiresLookupSource(string $field): bool
    {
        return str_ends_with($field, '_id') || str_ends_with($field, '_ids');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasReadableLookupValue(array $row): bool
    {
        foreach (['label', 'name', 'title', 'display_name'] as $key) {
            $value = $row[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }
}
