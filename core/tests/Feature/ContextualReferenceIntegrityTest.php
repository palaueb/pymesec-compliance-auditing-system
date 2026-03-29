<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextualReferenceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_assets_reject_owner_actor_assignments_outside_the_selected_scope(): void
    {
        $this->from('/app?menu=plugin.asset-catalog.root')
            ->post('/plugins/assets', [
                ...$this->operatorPayload('plugin.asset-catalog.root'),
                'name' => 'Scoped owner mismatch asset',
                'type' => 'application',
                'criticality' => 'high',
                'classification' => 'internal',
                'scope_id' => 'scope-eu',
                'owner_actor_id' => 'actor-it-services',
            ])
            ->assertRedirect('/app?menu=plugin.asset-catalog.root')
            ->assertSessionHasErrors(['owner_actor_id']);
    }

    public function test_risks_reject_cross_context_asset_and_control_links(): void
    {
        $this->from('/app?menu=plugin.risk-management.root')
            ->post('/plugins/risks', [
                ...$this->operatorPayload('plugin.risk-management.root'),
                'title' => 'Cross context supplier risk',
                'category' => 'third-party',
                'inherent_score' => 22,
                'residual_score' => 11,
                'linked_asset_id' => 'asset-route-planner',
                'linked_control_id' => 'control-access-review',
                'treatment' => 'Should fail on asset context.',
                'scope_id' => 'scope-eu',
            ])
            ->assertRedirect('/app?menu=plugin.risk-management.root')
            ->assertSessionHasErrors(['linked_asset_id']);

        $this->from('/app?menu=plugin.risk-management.root')
            ->post('/plugins/risks', [
                ...$this->operatorPayload('plugin.risk-management.root'),
                'title' => 'Cross context control risk',
                'category' => 'third-party',
                'inherent_score' => 22,
                'residual_score' => 11,
                'linked_asset_id' => 'asset-erp-prod',
                'linked_control_id' => 'control-backup-governance',
                'treatment' => 'Should fail on control scope.',
                'scope_id' => 'scope-eu',
            ])
            ->assertRedirect('/app?menu=plugin.risk-management.root')
            ->assertSessionHasErrors(['linked_control_id']);
    }

    public function test_findings_reject_cross_context_control_and_risk_links(): void
    {
        $this->from('/app?menu=plugin.findings-remediation.root')
            ->post('/plugins/findings', [
                ...$this->operatorPayload('plugin.findings-remediation.root'),
                'title' => 'Cross context finding',
                'severity' => 'high',
                'description' => 'Invalid linked records should be rejected.',
                'linked_control_id' => 'control-backup-governance',
                'linked_risk_id' => 'risk-access-drift',
                'scope_id' => 'scope-eu',
            ])
            ->assertRedirect('/app?menu=plugin.findings-remediation.root')
            ->assertSessionHasErrors(['linked_control_id']);

        $this->from('/app?menu=plugin.findings-remediation.root')
            ->post('/plugins/findings', [
                ...$this->operatorPayload('plugin.findings-remediation.root'),
                'title' => 'Cross context finding risk',
                'severity' => 'high',
                'description' => 'Invalid linked risk should be rejected.',
                'linked_control_id' => 'control-access-review',
                'linked_risk_id' => 'risk-backup-assurance',
                'scope_id' => 'scope-eu',
            ])
            ->assertRedirect('/app?menu=plugin.findings-remediation.root')
            ->assertSessionHasErrors(['linked_risk_id']);
    }

    public function test_privacy_data_flows_reject_cross_context_asset_and_risk_links(): void
    {
        $this->from('/app?menu=plugin.data-flows-privacy.root')
            ->post('/plugins/privacy/data-flows', [
                ...$this->operatorPayload('plugin.data-flows-privacy.root'),
                'title' => 'Cross context flow',
                'source' => 'A',
                'destination' => 'B',
                'data_category_summary' => 'Test data',
                'transfer_type' => 'vendor',
                'scope_id' => 'scope-eu',
                'linked_asset_id' => 'asset-route-planner',
                'linked_risk_id' => 'risk-access-drift',
            ])
            ->assertRedirect('/app?menu=plugin.data-flows-privacy.root')
            ->assertSessionHasErrors(['linked_asset_id']);

        $this->from('/app?menu=plugin.data-flows-privacy.root')
            ->post('/plugins/privacy/data-flows', [
                ...$this->operatorPayload('plugin.data-flows-privacy.root'),
                'title' => 'Cross context flow risk',
                'source' => 'A',
                'destination' => 'B',
                'data_category_summary' => 'Test data',
                'transfer_type' => 'vendor',
                'scope_id' => 'scope-eu',
                'linked_asset_id' => 'asset-erp-prod',
                'linked_risk_id' => 'risk-backup-assurance',
            ])
            ->assertRedirect('/app?menu=plugin.data-flows-privacy.root')
            ->assertSessionHasErrors(['linked_risk_id']);
    }

    public function test_privacy_processing_activities_reject_cross_context_reference_lists(): void
    {
        $this->from('/app?menu=plugin.data-flows-privacy.activities')
            ->post('/plugins/privacy/activities', [
                ...$this->operatorPayload('plugin.data-flows-privacy.activities'),
                'title' => 'Cross context activity',
                'purpose' => 'Test purpose',
                'lawful_basis' => 'contract',
                'scope_id' => 'scope-eu',
                'linked_data_flow_ids' => 'data-flow-backup-vendor-transfer',
                'linked_risk_ids' => 'risk-access-drift',
                'linked_policy_id' => 'policy-access-governance',
                'linked_finding_id' => 'finding-access-review-gap',
            ])
            ->assertRedirect('/app?menu=plugin.data-flows-privacy.activities')
            ->assertSessionHasErrors(['linked_data_flow_ids']);

        $this->from('/app?menu=plugin.data-flows-privacy.activities')
            ->post('/plugins/privacy/activities', [
                ...$this->operatorPayload('plugin.data-flows-privacy.activities'),
                'title' => 'Cross context activity risk list',
                'purpose' => 'Test purpose',
                'lawful_basis' => 'contract',
                'scope_id' => 'scope-eu',
                'linked_data_flow_ids' => 'data-flow-customer-support-handoff',
                'linked_risk_ids' => 'risk-backup-assurance',
                'linked_policy_id' => 'policy-access-governance',
                'linked_finding_id' => 'finding-access-review-gap',
            ])
            ->assertRedirect('/app?menu=plugin.data-flows-privacy.activities')
            ->assertSessionHasErrors(['linked_risk_ids']);

        $this->from('/app?menu=plugin.data-flows-privacy.activities')
            ->post('/plugins/privacy/activities', [
                ...$this->operatorPayload('plugin.data-flows-privacy.activities'),
                'title' => 'Cross context activity policy',
                'purpose' => 'Test purpose',
                'lawful_basis' => 'contract',
                'scope_id' => 'scope-eu',
                'linked_data_flow_ids' => 'data-flow-customer-support-handoff',
                'linked_risk_ids' => 'risk-access-drift',
                'linked_policy_id' => 'policy-backup-assurance',
                'linked_finding_id' => 'finding-access-review-gap',
            ])
            ->assertRedirect('/app?menu=plugin.data-flows-privacy.activities')
            ->assertSessionHasErrors(['linked_policy_id']);

        $this->from('/app?menu=plugin.data-flows-privacy.activities')
            ->post('/plugins/privacy/activities', [
                ...$this->operatorPayload('plugin.data-flows-privacy.activities'),
                'title' => 'Cross context activity finding',
                'purpose' => 'Test purpose',
                'lawful_basis' => 'contract',
                'scope_id' => 'scope-eu',
                'linked_data_flow_ids' => 'data-flow-customer-support-handoff',
                'linked_risk_ids' => 'risk-access-drift',
                'linked_policy_id' => 'policy-access-governance',
                'linked_finding_id' => 'finding-backup-test-gap',
            ])
            ->assertRedirect('/app?menu=plugin.data-flows-privacy.activities')
            ->assertSessionHasErrors(['linked_finding_id']);
    }

    public function test_continuity_services_and_dependencies_reject_cross_organization_links(): void
    {
        $this->from('/app?menu=plugin.continuity-bcm.root')
            ->post('/plugins/continuity/services', [
                ...$this->operatorPayload('plugin.continuity-bcm.root'),
                'title' => 'Cross context service',
                'impact_tier' => 'critical',
                'recovery_time_objective_hours' => 6,
                'recovery_point_objective_hours' => 2,
                'linked_asset_id' => 'asset-route-planner',
                'linked_risk_id' => 'risk-access-drift',
                'scope_id' => 'scope-eu',
            ])
            ->assertRedirect('/app?menu=plugin.continuity-bcm.root')
            ->assertSessionHasErrors(['linked_asset_id']);

        $this->from('/app?menu=plugin.continuity-bcm.root')
            ->post('/plugins/continuity/services', [
                ...$this->operatorPayload('plugin.continuity-bcm.root'),
                'title' => 'Cross context service risk',
                'impact_tier' => 'critical',
                'recovery_time_objective_hours' => 6,
                'recovery_point_objective_hours' => 2,
                'linked_asset_id' => 'asset-erp-prod',
                'linked_risk_id' => 'risk-backup-assurance',
                'scope_id' => 'scope-eu',
            ])
            ->assertRedirect('/app?menu=plugin.continuity-bcm.root')
            ->assertSessionHasErrors(['linked_risk_id']);

        $this->from('/app?menu=plugin.continuity-bcm.root')
            ->post('/plugins/continuity/services/continuity-service-customer-support/dependencies', [
                ...$this->operatorPayload('plugin.continuity-bcm.root'),
                'depends_on_service_id' => 'continuity-service-route-control',
                'dependency_kind' => 'critical',
                'scope_id' => 'scope-eu',
            ])
            ->assertRedirect('/app?menu=plugin.continuity-bcm.root')
            ->assertSessionHasErrors(['depends_on_service_id']);
    }

    public function test_continuity_plans_reject_cross_context_policy_and_finding_links(): void
    {
        $this->from('/app?menu=plugin.continuity-bcm.plans')
            ->post('/plugins/continuity/services/continuity-service-customer-support/plans', [
                ...$this->operatorPayload('plugin.continuity-bcm.plans'),
                'title' => 'Cross context plan',
                'strategy_summary' => 'Test strategy',
                'test_due_on' => '2026-06-01',
                'linked_policy_id' => 'policy-backup-assurance',
                'linked_finding_id' => 'finding-access-review-gap',
                'scope_id' => 'scope-eu',
            ])
            ->assertRedirect('/app?menu=plugin.continuity-bcm.plans')
            ->assertSessionHasErrors(['linked_policy_id']);

        $this->from('/app?menu=plugin.continuity-bcm.plans')
            ->post('/plugins/continuity/services/continuity-service-customer-support/plans', [
                ...$this->operatorPayload('plugin.continuity-bcm.plans'),
                'title' => 'Cross context plan finding',
                'strategy_summary' => 'Test strategy',
                'test_due_on' => '2026-06-01',
                'linked_policy_id' => 'policy-access-governance',
                'linked_finding_id' => 'finding-backup-test-gap',
                'scope_id' => 'scope-eu',
            ])
            ->assertRedirect('/app?menu=plugin.continuity-bcm.plans')
            ->assertSessionHasErrors(['linked_finding_id']);
    }

    public function test_policies_and_exceptions_reject_cross_context_links(): void
    {
        $this->from('/app?menu=plugin.policy-exceptions.root')
            ->post('/plugins/policies', [
                ...$this->operatorPayload('plugin.policy-exceptions.root'),
                'title' => 'Cross context policy',
                'area' => 'Identity',
                'version_label' => 'v1.0',
                'statement' => 'Test statement.',
                'linked_control_id' => 'control-backup-governance',
                'scope_id' => 'scope-eu',
            ])
            ->assertRedirect('/app?menu=plugin.policy-exceptions.root')
            ->assertSessionHasErrors(['linked_control_id']);

        $this->from('/app?menu=plugin.policy-exceptions.exceptions')
            ->post('/plugins/policies/policy-access-governance/exceptions', [
                ...$this->operatorPayload('plugin.policy-exceptions.exceptions'),
                'title' => 'Cross context exception',
                'rationale' => 'Test rationale.',
                'linked_finding_id' => 'finding-backup-test-gap',
                'scope_id' => 'scope-eu',
            ])
            ->assertRedirect('/app?menu=plugin.policy-exceptions.exceptions')
            ->assertSessionHasErrors(['linked_finding_id']);
    }

    public function test_assessments_reject_control_lists_outside_the_selected_organization(): void
    {
        $this->from('/app?menu=plugin.assessments-audits.root')
            ->post('/plugins/assessments', [
                ...$this->operatorPayload('plugin.assessments-audits.root'),
                'title' => 'Cross context assessment',
                'summary' => 'Test summary',
                'framework_id' => 'framework-iso-27001',
                'scope_id' => 'scope-eu',
                'starts_on' => '2026-06-01',
                'ends_on' => '2026-06-15',
                'status' => 'draft',
                'control_ids' => ['control-route-integrity'],
            ])
            ->assertRedirect('/app?menu=plugin.assessments-audits.root')
            ->assertSessionHasErrors(['control_ids']);
    }

    /**
     * @return array<string, string>
     */
    private function operatorPayload(string $menu): array
    {
        return [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => $menu,
            'membership_id' => 'membership-org-a-hello',
        ];
    }
}
