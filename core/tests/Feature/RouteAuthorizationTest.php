<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_asset_plugin_route_requires_view_permission(): void
    {
        $this->get('/plugins/assets?principal_id=principal-org-a&organization_id=org-a')
            ->assertFound();

        $this->get('/plugins/assets?principal_id=principal-org-a&organization_id=org-missing')
            ->assertForbidden();
    }

    public function test_the_asset_transition_route_requires_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.asset-catalog.root',
            'membership_id' => 'membership-org-a-viewer',
        ];

        $this->post('/plugins/assets/asset-erp-prod/transitions/submit-review', $payload)
            ->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/assets/asset-erp-prod/transitions/submit-review', $payload)
            ->assertFound();
    }

    public function test_the_asset_create_and_update_routes_require_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.asset-catalog.root',
            'membership_id' => 'membership-org-a-viewer',
            'name' => 'Viewer asset',
            'type' => 'application',
            'criticality' => 'high',
            'classification' => 'internal',
        ];

        $this->post('/plugins/assets', $payload)->assertForbidden();
        $this->post('/plugins/assets/asset-erp-prod', $payload)->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';
        $payload['owner_label'] = 'Compliance Office';

        $this->post('/plugins/assets', $payload)->assertFound();
        $this->post('/plugins/assets/asset-erp-prod', $payload)->assertFound();
    }

    public function test_the_asset_shell_screen_hides_transitions_for_view_only_access(): void
    {
        $this->get('/app?menu=plugin.asset-catalog.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-viewer')
            ->assertOk()
            ->assertSee('View-only access')
            ->assertDontSee('Submit Review');
    }

    public function test_the_actor_directory_route_requires_view_permission(): void
    {
        $this->get('/plugins/actors?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk();

        $this->get('/plugins/actors?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_controls_artifact_upload_route_requires_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.root',
            'membership_id' => 'membership-org-a-viewer',
            'label' => 'Viewer attempt',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('viewer.txt', 'viewer'),
        ];

        $this->post('/plugins/controls/control-access-review/artifacts', $payload)
            ->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/controls/control-access-review/artifacts', $payload)
            ->assertFound();
    }

    public function test_the_controls_create_and_update_routes_require_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.root',
            'membership_id' => 'membership-org-a-viewer',
            'name' => 'Viewer Control',
            'framework_id' => 'framework-iso-27001',
            'domain' => 'Identity',
            'evidence' => 'Viewer should not create',
        ];

        $this->post('/plugins/controls', $payload)->assertForbidden();
        $this->post('/plugins/controls/control-access-review', $payload)->assertForbidden();
        $this->post('/plugins/controls/frameworks', [
            ...$payload,
            'code' => 'CIS-1',
            'name' => 'CIS Safeguards',
            'description' => 'Viewer should not add frameworks.',
        ])->assertForbidden();
        $this->post('/plugins/controls/requirements', [
            ...$payload,
            'framework_id' => 'framework-iso-27001',
            'code' => 'A.5.18',
            'title' => 'Access rights',
            'description' => 'Viewer should not add requirements.',
        ])->assertForbidden();
        $this->post('/plugins/controls/control-access-review/requirements', [
            ...$payload,
            'requirement_id' => 'requirement-iso-a-5-18',
            'coverage' => 'full',
            'notes' => 'Viewer should not link requirements.',
        ])->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/controls', $payload)->assertFound();
        $this->post('/plugins/controls/control-access-review', $payload)->assertFound();
        $this->post('/plugins/controls/frameworks', [
            ...$payload,
            'code' => 'CIS-1',
            'name' => 'CIS Safeguards',
            'description' => 'Operator can add frameworks.',
        ])->assertFound();
        $this->post('/plugins/controls/requirements', [
            ...$payload,
            'framework_id' => 'framework-iso-27001',
            'code' => 'A.5.19',
            'title' => 'Information security in supplier relationships',
            'description' => 'Operator can add requirements.',
        ])->assertFound();
        $this->post('/plugins/controls/control-access-review/requirements', [
            ...$payload,
            'requirement_id' => 'requirement-iso-a-5-18',
            'coverage' => 'full',
            'notes' => 'Operator can link requirements.',
        ])->assertFound();
    }

    public function test_the_risk_transition_and_artifact_routes_require_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.risk-management.root',
            'membership_id' => 'membership-org-a-viewer',
        ];

        $this->post('/plugins/risks/risk-access-drift/transitions/start-assessment', $payload)
            ->assertForbidden();

        $this->post('/plugins/risks/risk-access-drift/artifacts', [
            ...$payload,
            'label' => 'Viewer risk attempt',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('viewer-risk.txt', 'viewer'),
        ])->assertForbidden();

        $this->post('/plugins/risks', [
            ...$payload,
            'title' => 'Viewer risk',
            'category' => 'Ops',
            'inherent_score' => 10,
            'residual_score' => 5,
            'treatment' => 'Blocked',
        ])->assertForbidden();

        $this->post('/plugins/risks/risk-access-drift', [
            ...$payload,
            'title' => 'Viewer risk',
            'category' => 'Ops',
            'inherent_score' => 10,
            'residual_score' => 5,
            'treatment' => 'Blocked',
        ])->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/risks/risk-access-drift/transitions/start-assessment', $payload)
            ->assertFound();

        $this->post('/plugins/risks/risk-access-drift/artifacts', [
            ...$payload,
            'label' => 'Operator risk evidence',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('operator-risk.txt', 'operator'),
        ])->assertFound();

        $this->post('/plugins/risks', [
            ...$payload,
            'title' => 'Operator risk',
            'category' => 'Ops',
            'inherent_score' => 10,
            'residual_score' => 5,
            'treatment' => 'Allowed',
        ])->assertFound();

        $this->post('/plugins/risks/risk-access-drift', [
            ...$payload,
            'title' => 'Operator updated risk',
            'category' => 'Ops',
            'inherent_score' => 11,
            'residual_score' => 6,
            'treatment' => 'Allowed update',
        ])->assertFound();
    }

    public function test_the_findings_routes_require_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.findings-remediation.root',
            'membership_id' => 'membership-org-a-viewer',
        ];

        $this->post('/plugins/findings/finding-access-review-gap/transitions/triage', $payload)
            ->assertForbidden();

        $this->post('/plugins/findings/finding-access-review-gap/artifacts', [
            ...$payload,
            'label' => 'Viewer finding attempt',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('viewer-finding.txt', 'viewer'),
        ])->assertForbidden();

        $this->post('/plugins/findings', [
            ...$payload,
            'title' => 'Viewer finding',
            'severity' => 'medium',
            'description' => 'Viewer should not create findings.',
        ])->assertForbidden();

        $this->post('/plugins/findings/finding-access-review-gap', [
            ...$payload,
            'title' => 'Viewer update',
            'severity' => 'medium',
            'description' => 'Viewer should not update findings.',
        ])->assertForbidden();

        $this->post('/plugins/findings/finding-access-review-gap/actions', [
            ...$payload,
            'title' => 'Viewer action',
            'status' => 'planned',
        ])->assertForbidden();

        $this->post('/plugins/findings/actions/action-access-review-pack', [
            ...$payload,
            'title' => 'Viewer action update',
            'status' => 'planned',
        ])->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/findings/finding-access-review-gap/transitions/triage', $payload)
            ->assertFound();

        $this->post('/plugins/findings/finding-access-review-gap/artifacts', [
            ...$payload,
            'label' => 'Operator finding evidence',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('operator-finding.txt', 'operator'),
        ])->assertFound();

        $this->post('/plugins/findings', [
            ...$payload,
            'title' => 'Operator finding',
            'severity' => 'high',
            'description' => 'Operator can create findings.',
        ])->assertFound();

        $this->post('/plugins/findings/finding-access-review-gap', [
            ...$payload,
            'title' => 'Operator updated finding',
            'severity' => 'critical',
            'description' => 'Operator can update findings.',
        ])->assertFound();

        $this->post('/plugins/findings/finding-access-review-gap/actions', [
            ...$payload,
            'title' => 'Operator action',
            'status' => 'planned',
        ])->assertFound();

        $this->post('/plugins/findings/actions/action-access-review-pack', [
            ...$payload,
            'title' => 'Operator updated action',
            'status' => 'done',
        ])->assertFound();
    }

    public function test_the_policy_routes_require_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.policy-exceptions.root',
            'membership_id' => 'membership-org-a-viewer',
        ];

        $this->post('/plugins/policies/policy-access-governance/transitions/submit-review', $payload)
            ->assertForbidden();

        $this->post('/plugins/policies/exceptions/exception-break-glass-window/transitions/approve', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.exceptions',
        ])->assertForbidden();

        $this->post('/plugins/policies/policy-access-governance/artifacts', [
            ...$payload,
            'label' => 'Viewer policy attempt',
            'artifact_type' => 'document',
            'artifact' => UploadedFile::fake()->createWithContent('viewer-policy.txt', 'viewer'),
        ])->assertForbidden();

        $this->post('/plugins/policies/exceptions/exception-break-glass-window/artifacts', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.exceptions',
            'label' => 'Viewer exception attempt',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('viewer-exception.txt', 'viewer'),
        ])->assertForbidden();

        $this->post('/plugins/policies', [
            ...$payload,
            'title' => 'Viewer policy',
            'area' => 'Governance',
            'version_label' => 'v0',
            'statement' => 'Viewer should not create policies.',
        ])->assertForbidden();

        $this->post('/plugins/policies/policy-access-governance', [
            ...$payload,
            'title' => 'Viewer update',
            'area' => 'Governance',
            'version_label' => 'v0',
            'statement' => 'Viewer should not update policies.',
        ])->assertForbidden();

        $this->post('/plugins/policies/policy-access-governance/exceptions', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.exceptions',
            'title' => 'Viewer exception',
            'rationale' => 'Viewer should not create exceptions.',
        ])->assertForbidden();

        $this->post('/plugins/policies/exceptions/exception-break-glass-window', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.exceptions',
            'title' => 'Viewer exception update',
            'rationale' => 'Viewer should not update exceptions.',
        ])->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/policies/policy-access-governance/transitions/submit-review', $payload)
            ->assertFound();

        $this->post('/plugins/policies/exceptions/exception-break-glass-window/transitions/approve', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.exceptions',
        ])->assertFound();

        $this->post('/plugins/policies/policy-access-governance/artifacts', [
            ...$payload,
            'label' => 'Operator policy evidence',
            'artifact_type' => 'document',
            'artifact' => UploadedFile::fake()->createWithContent('operator-policy.txt', 'operator'),
        ])->assertFound();

        $this->post('/plugins/policies/exceptions/exception-break-glass-window/artifacts', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.exceptions',
            'label' => 'Operator exception evidence',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('operator-exception.txt', 'operator'),
        ])->assertFound();

        $this->post('/plugins/policies', [
            ...$payload,
            'title' => 'Operator policy',
            'area' => 'Governance',
            'version_label' => 'v1',
            'statement' => 'Operator can create policies.',
        ])->assertFound();

        $this->post('/plugins/policies/policy-access-governance', [
            ...$payload,
            'title' => 'Operator updated policy',
            'area' => 'Governance',
            'version_label' => 'v2',
            'statement' => 'Operator can update policies.',
        ])->assertFound();

        $this->post('/plugins/policies/policy-access-governance/exceptions', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.exceptions',
            'title' => 'Operator exception',
            'rationale' => 'Operator can create exceptions.',
        ])->assertFound();

        $this->post('/plugins/policies/exceptions/exception-break-glass-window', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.exceptions',
            'title' => 'Operator updated exception',
            'rationale' => 'Operator can update exceptions.',
        ])->assertFound();
    }

    public function test_the_privacy_routes_require_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-viewer',
        ];

        $this->post('/plugins/privacy/data-flows/data-flow-customer-support-handoff/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.root',
        ])->assertForbidden();

        $this->post('/plugins/privacy/activities/processing-activity-customer-support-operations/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.activities',
        ])->assertForbidden();

        $this->post('/plugins/privacy/data-flows/data-flow-customer-support-handoff/artifacts', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.root',
            'label' => 'Viewer privacy attempt',
            'artifact_type' => 'record',
            'artifact' => UploadedFile::fake()->createWithContent('viewer-privacy.txt', 'viewer'),
        ])->assertForbidden();

        $this->post('/plugins/privacy/activities/processing-activity-customer-support-operations/artifacts', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.activities',
            'label' => 'Viewer privacy activity attempt',
            'artifact_type' => 'record',
            'artifact' => UploadedFile::fake()->createWithContent('viewer-privacy-activity.txt', 'viewer'),
        ])->assertForbidden();

        $this->post('/plugins/privacy/data-flows', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.root',
            'title' => 'Viewer flow',
            'source' => 'A',
            'destination' => 'B',
            'data_category_summary' => 'Viewer should not create privacy flows.',
            'transfer_type' => 'internal',
        ])->assertForbidden();

        $this->post('/plugins/privacy/data-flows/data-flow-customer-support-handoff', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.root',
            'title' => 'Viewer flow update',
            'source' => 'A',
            'destination' => 'B',
            'data_category_summary' => 'Viewer should not update privacy flows.',
            'transfer_type' => 'internal',
        ])->assertForbidden();

        $this->post('/plugins/privacy/activities', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.activities',
            'title' => 'Viewer activity',
            'purpose' => 'Viewer should not create activities.',
            'lawful_basis' => 'contract',
        ])->assertForbidden();

        $this->post('/plugins/privacy/activities/processing-activity-customer-support-operations', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.activities',
            'title' => 'Viewer activity update',
            'purpose' => 'Viewer should not update activities.',
            'lawful_basis' => 'contract',
        ])->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/privacy/data-flows/data-flow-customer-support-handoff/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.root',
        ])->assertFound();

        $this->post('/plugins/privacy/activities/processing-activity-customer-support-operations/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.activities',
        ])->assertFound();

        $this->post('/plugins/privacy/data-flows/data-flow-customer-support-handoff/artifacts', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.root',
            'label' => 'Operator privacy record',
            'artifact_type' => 'record',
            'artifact' => UploadedFile::fake()->createWithContent('operator-privacy.txt', 'operator'),
        ])->assertFound();

        $this->post('/plugins/privacy/activities/processing-activity-customer-support-operations/artifacts', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.activities',
            'label' => 'Operator privacy activity record',
            'artifact_type' => 'record',
            'artifact' => UploadedFile::fake()->createWithContent('operator-privacy-activity.txt', 'operator'),
        ])->assertFound();

        $this->post('/plugins/privacy/data-flows', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.root',
            'title' => 'Operator flow',
            'source' => 'A',
            'destination' => 'B',
            'data_category_summary' => 'Operator can create privacy flows.',
            'transfer_type' => 'internal',
        ])->assertFound();

        $this->post('/plugins/privacy/data-flows/data-flow-customer-support-handoff', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.root',
            'title' => 'Operator updated flow',
            'source' => 'A',
            'destination' => 'B',
            'data_category_summary' => 'Operator can update privacy flows.',
            'transfer_type' => 'internal',
        ])->assertFound();

        $this->post('/plugins/privacy/activities', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.activities',
            'title' => 'Operator activity',
            'purpose' => 'Operator can create activities.',
            'lawful_basis' => 'contract',
        ])->assertFound();

        $this->post('/plugins/privacy/activities/processing-activity-customer-support-operations', [
            ...$payload,
            'menu' => 'plugin.data-flows-privacy.activities',
            'title' => 'Operator updated activity',
            'purpose' => 'Operator can update activities.',
            'lawful_basis' => 'contract',
        ])->assertFound();
    }

    public function test_the_continuity_routes_require_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-viewer',
        ];

        $this->post('/plugins/continuity/services/continuity-service-customer-support/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
        ])->assertForbidden();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
        ])->assertForbidden();

        $this->post('/plugins/continuity/services/continuity-service-customer-support/artifacts', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'label' => 'Viewer continuity attempt',
            'artifact_type' => 'continuity-record',
            'artifact' => UploadedFile::fake()->createWithContent('viewer-continuity.txt', 'viewer'),
        ])->assertForbidden();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/artifacts', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'label' => 'Viewer recovery attempt',
            'artifact_type' => 'recovery-plan',
            'artifact' => UploadedFile::fake()->createWithContent('viewer-recovery.txt', 'viewer'),
        ])->assertForbidden();

        $this->post('/plugins/continuity/services', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'title' => 'Viewer continuity service',
            'impact_tier' => 'high',
            'recovery_time_objective_hours' => 12,
            'recovery_point_objective_hours' => 4,
        ])->assertForbidden();

        $this->post('/plugins/continuity/services/continuity-service-customer-support', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'title' => 'Viewer continuity update',
            'impact_tier' => 'high',
            'recovery_time_objective_hours' => 12,
            'recovery_point_objective_hours' => 4,
        ])->assertForbidden();

        $this->post('/plugins/continuity/services/continuity-service-customer-support/plans', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'title' => 'Viewer recovery plan',
            'strategy_summary' => 'Viewer should not create plans.',
        ])->assertForbidden();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'title' => 'Viewer recovery update',
            'strategy_summary' => 'Viewer should not update plans.',
        ])->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/continuity/services/continuity-service-customer-support/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
        ])->assertFound();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/transitions/submit-review', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
        ])->assertFound();

        $this->post('/plugins/continuity/services/continuity-service-customer-support/artifacts', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'label' => 'Operator continuity record',
            'artifact_type' => 'continuity-record',
            'artifact' => UploadedFile::fake()->createWithContent('operator-continuity.txt', 'operator'),
        ])->assertFound();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/artifacts', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'label' => 'Operator recovery evidence',
            'artifact_type' => 'recovery-plan',
            'artifact' => UploadedFile::fake()->createWithContent('operator-recovery.txt', 'operator'),
        ])->assertFound();

        $this->post('/plugins/continuity/services', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'title' => 'Operator continuity service',
            'impact_tier' => 'critical',
            'recovery_time_objective_hours' => 6,
            'recovery_point_objective_hours' => 2,
        ])->assertFound();

        $this->post('/plugins/continuity/services/continuity-service-customer-support', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'title' => 'Operator continuity update',
            'impact_tier' => 'critical',
            'recovery_time_objective_hours' => 5,
            'recovery_point_objective_hours' => 2,
        ])->assertFound();

        $this->post('/plugins/continuity/services/continuity-service-customer-support/plans', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'title' => 'Operator recovery plan',
            'strategy_summary' => 'Operator can create continuity plans.',
            'organization_id' => 'org-a',
        ])->assertFound();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'title' => 'Operator recovery update',
            'strategy_summary' => 'Operator can update continuity plans.',
        ])->assertFound();
    }

    public function test_the_core_role_routes_require_platform_permission(): void
    {
        $this->get('/core/roles?principal_id=principal-org-a')
            ->assertForbidden();

        $payload = [
            'principal_id' => 'principal-org-a',
            'locale' => 'en',
            'menu' => 'core.roles',
        ];

        $this->post('/core/roles', [
            ...$payload,
            'key' => 'viewer-role',
            'label' => 'Viewer role',
        ])->assertForbidden();

        $this->post('/core/roles/grants', [
            ...$payload,
            'target_type' => 'principal',
            'target_id' => 'principal-org-a',
            'grant_type' => 'role',
            'value' => 'platform-admin',
            'context_type' => 'platform',
        ])->assertForbidden();

        $this->get('/core/roles?principal_id=principal-admin')
            ->assertOk();
    }
}
