<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

        $this->post('/plugins/assets', $payload)->assertFound();
        $this->post('/plugins/assets/asset-erp-prod', $payload)->assertFound();
    }

    public function test_the_asset_shell_screen_hides_transitions_for_view_only_access(): void
    {
        $this->get('/app?menu=plugin.asset-catalog.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-viewer&asset_id=asset-erp-prod')
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

    public function test_the_notifications_management_routes_require_manage_permission(): void
    {
        $viewerPayload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'core.notifications',
            'email_enabled' => '1',
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => '2525',
            'smtp_encryption' => 'tls',
            'from_address' => 'mailer@pymesec.test',
            'recipient_principal_id' => 'principal-org-a',
        ];

        $this->post('/core/notifications/settings', $viewerPayload)
            ->assertForbidden();

        $this->post('/core/notifications/test-email', $viewerPayload)
            ->assertForbidden();

        $this->post('/core/notifications/templates', [
            ...$viewerPayload,
            'notification_type' => 'plugin.evidence-management.review-due',
            'title_template' => '[Reminder] {{notification_title}}',
            'body_template' => '{{notification_body}}',
        ])->assertForbidden();

        $this->post('/core/notifications/settings', [
            ...$viewerPayload,
            'principal_id' => 'principal-admin',
            'from_name' => 'PymeSec Mailer',
        ])->assertFound();

        $this->post('/core/object-access/assignments', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'core.object-access',
            'subject_key' => 'asset::asset-erp-prod',
            'actor_id' => 'actor-it-services',
            'assignment_type' => 'reviewer',
        ])->assertForbidden();
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
        $this->post('/plugins/controls/frameworks/framework-gdpr/adoption', [
            ...$payload,
            'scope_id' => 'scope-eu',
            'status' => 'active',
            'adopted_at' => '2026-03-01',
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
        $this->post('/plugins/controls/frameworks/framework-gdpr/adoption', [
            ...$payload,
            'scope_id' => 'scope-eu',
            'status' => 'active',
            'adopted_at' => '2026-03-01',
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
            'area' => 'governance',
            'version_label' => 'v0',
            'statement' => 'Viewer should not create policies.',
        ])->assertForbidden();

        $this->post('/plugins/policies/policy-access-governance', [
            ...$payload,
            'title' => 'Viewer update',
            'area' => 'governance',
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
            'area' => 'governance',
            'version_label' => 'v1',
            'statement' => 'Operator can create policies.',
        ])->assertFound();

        $this->post('/plugins/policies/policy-access-governance', [
            ...$payload,
            'title' => 'Operator updated policy',
            'area' => 'governance',
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

        $this->post('/plugins/continuity/services/continuity-service-customer-support/dependencies', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'depends_on_service_id' => 'continuity-service-backup-recovery',
            'dependency_kind' => 'critical',
            'recovery_notes' => 'Viewer should not add dependencies.',
        ])->assertForbidden();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/artifacts', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'label' => 'Viewer recovery attempt',
            'artifact_type' => 'recovery-plan',
            'artifact' => UploadedFile::fake()->createWithContent('viewer-recovery.txt', 'viewer'),
        ])->assertForbidden();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/exercises', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'exercise_date' => now()->toDateString(),
            'exercise_type' => 'tabletop',
            'scenario_summary' => 'Viewer should not log exercises.',
            'outcome' => 'partial',
        ])->assertForbidden();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/executions', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'executed_on' => now()->toDateString(),
            'execution_type' => 'recovery-drill',
            'status' => 'passed',
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

        $this->post('/plugins/continuity/services/continuity-service-customer-support/dependencies', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.root',
            'depends_on_service_id' => 'continuity-service-backup-recovery',
            'dependency_kind' => 'critical',
            'recovery_notes' => 'Operator can add continuity dependencies.',
        ])->assertFound();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/artifacts', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'label' => 'Operator recovery evidence',
            'artifact_type' => 'recovery-plan',
            'artifact' => UploadedFile::fake()->createWithContent('operator-recovery.txt', 'operator'),
        ])->assertFound();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/exercises', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'exercise_date' => now()->toDateString(),
            'exercise_type' => 'tabletop',
            'scenario_summary' => 'Operator can log exercises.',
            'outcome' => 'pass',
        ])->assertFound();

        $this->post('/plugins/continuity/plans/continuity-plan-support-fallback/executions', [
            ...$payload,
            'menu' => 'plugin.continuity-bcm.plans',
            'executed_on' => now()->toDateString(),
            'execution_type' => 'recovery-drill',
            'status' => 'passed',
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

    public function test_the_assessment_routes_require_manage_permission(): void
    {
        DB::table('functional_assignments')->insert([
            'id' => 'assignment-route-auth-assessment-owner',
            'functional_actor_id' => 'actor-ava-mason',
            'domain_object_type' => 'assessment',
            'domain_object_id' => 'assessment-q2-access-resilience',
            'assignment_type' => 'owner',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'metadata' => json_encode(['source' => 'test'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'menu' => 'plugin.assessments-audits.root',
            'membership_id' => 'membership-org-a-viewer',
        ];

        $this->post('/plugins/assessments', [
            ...$payload,
            'title' => 'Viewer assessment',
            'summary' => 'Viewer should not create assessments.',
            'framework_id' => 'framework-gdpr',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-15',
            'status' => 'draft',
        ])->assertForbidden();

        $this->post('/plugins/assessments/assessment-q2-access-resilience', [
            ...$payload,
            'title' => 'Viewer assessment update',
            'summary' => 'Viewer should not update assessments.',
            'framework_id' => 'framework-gdpr',
            'starts_on' => DB::table('assessment_campaigns')->where('id', 'assessment-q2-access-resilience')->value('starts_on'),
            'ends_on' => DB::table('assessment_campaigns')->where('id', 'assessment-q2-access-resilience')->value('ends_on'),
            'status' => DB::table('assessment_campaigns')->where('id', 'assessment-q2-access-resilience')->value('status'),
            'control_ids' => ['control-access-review'],
        ])->assertForbidden();

        $this->post('/plugins/assessments/assessment-q2-access-resilience/reviews/control-access-review', [
            ...$payload,
            'result' => 'pass',
        ])->assertForbidden();

        $this->post('/plugins/assessments/assessment-q2-access-resilience/reviews/control-access-review/artifacts', [
            ...$payload,
            'label' => 'Viewer workpaper',
            'artifact_type' => 'workpaper',
            'artifact' => UploadedFile::fake()->createWithContent('viewer-assessment.txt', 'viewer'),
        ])->assertForbidden();

        $this->post('/plugins/assessments/assessment-q2-access-resilience/reviews/control-access-review/findings', [
            ...$payload,
            'title' => 'Viewer finding',
            'severity' => 'medium',
            'description' => 'Viewer should not create findings from assessments.',
        ])->assertForbidden();

        $this->post('/plugins/assessments/assessment-q2-access-resilience/transitions/activate', $payload)
            ->assertForbidden();

        $this->post('/plugins/assessments/assessment-q2-access-resilience/owners/assignment-route-auth-assessment-owner/remove', $payload)
            ->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/assessments', [
            ...$payload,
            'title' => 'Operator assessment',
            'summary' => 'Operator can create assessments.',
            'framework_id' => 'framework-gdpr',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-15',
            'status' => 'draft',
        ])->assertFound();

        $this->post('/plugins/assessments/assessment-q2-access-resilience', [
            ...$payload,
            'title' => 'Operator assessment update',
            'summary' => 'Operator can update assessments.',
            'framework_id' => 'framework-gdpr',
            'starts_on' => DB::table('assessment_campaigns')->where('id', 'assessment-q2-access-resilience')->value('starts_on'),
            'ends_on' => DB::table('assessment_campaigns')->where('id', 'assessment-q2-access-resilience')->value('ends_on'),
            'status' => DB::table('assessment_campaigns')->where('id', 'assessment-q2-access-resilience')->value('status'),
            'control_ids' => ['control-access-review'],
        ])->assertFound();

        $this->post('/plugins/assessments/assessment-q2-access-resilience/reviews/control-access-review', [
            ...$payload,
            'result' => 'pass',
        ])->assertFound();

        $this->post('/plugins/assessments/assessment-q2-access-resilience/reviews/control-access-review/artifacts', [
            ...$payload,
            'label' => 'Operator workpaper',
            'artifact_type' => 'workpaper',
            'artifact' => UploadedFile::fake()->createWithContent('operator-assessment.txt', 'operator'),
        ])->assertFound();

        $this->post('/plugins/assessments/assessment-q2-access-resilience/reviews/control-access-review/findings', [
            ...$payload,
            'title' => 'Operator finding',
            'severity' => 'medium',
            'description' => 'Operator can create findings from assessments.',
        ])->assertFound();

        $this->post('/plugins/assessments/assessment-q2-access-resilience/transitions/activate', $payload)
            ->assertFound();

        $this->post('/plugins/assessments/assessment-q2-access-resilience/owners/assignment-route-auth-assessment-owner/remove', $payload)
            ->assertFound();
    }

    public function test_the_evidence_routes_require_the_expected_permissions(): void
    {
        $viewerQuery = '?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-viewer';

        $this->get('/plugins/evidence/evidence-access-review-pack/download'.$viewerQuery)
            ->assertNotFound();

        $this->get('/plugins/evidence/evidence-access-review-pack/preview'.$viewerQuery)
            ->assertNotFound();

        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'menu' => 'plugin.evidence-management.root',
            'membership_id' => 'membership-org-a-viewer',
        ];

        $this->post('/plugins/evidence/evidence-access-review-pack/reminders/review-due', $payload)
            ->assertForbidden();

        $payload['membership_id'] = 'membership-org-a-hello';

        $this->post('/plugins/evidence/evidence-access-review-pack/reminders/review-due', $payload)
            ->assertFound();
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

    public function test_the_plugin_lifecycle_routes_require_platform_permission(): void
    {
        $viewerPayload = [
            'principal_id' => 'principal-org-a',
            'locale' => 'en',
            'menu' => 'core.plugins',
        ];

        $this->post('/core/plugins/hello-world/disable', $viewerPayload)
            ->assertForbidden();

        $this->post('/core/plugins/hello-world/enable', $viewerPayload)
            ->assertForbidden();

        $this->post('/core/plugins/hello-world/disable', [
            ...$viewerPayload,
            'principal_id' => 'principal-admin',
        ])->assertFound();
    }

    public function test_the_reference_data_routes_require_platform_permission(): void
    {
        $this->get('/core/reference-data?principal_id=principal-org-a&organization_id=org-a')
            ->assertForbidden();

        $viewerPayload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'catalog_key' => 'risks.categories',
            'locale' => 'en',
            'menu' => 'core.reference-data',
        ];

        $this->post('/core/reference-data/entries', [
            ...$viewerPayload,
            'option_key' => 'viewer-created-category',
            'label' => 'Viewer created category',
            'description' => 'Should be blocked.',
            'sort_order' => 210,
        ])->assertForbidden();

        $this->post('/core/reference-data/entries', [
            ...$viewerPayload,
            'principal_id' => 'principal-admin',
            'option_key' => 'admin-created-category',
            'label' => 'Admin created category',
            'description' => 'Used to verify archive authorization.',
            'sort_order' => 220,
        ])->assertFound();

        $entryId = DB::table('reference_catalog_entries')
            ->where('organization_id', 'org-a')
            ->where('catalog_key', 'risks.categories')
            ->where('option_key', 'admin-created-category')
            ->value('id');

        $this->assertIsString($entryId);

        $this->post(sprintf('/core/reference-data/entries/%s/archive', $entryId), $viewerPayload)
            ->assertForbidden();

        $this->get('/core/reference-data?principal_id=principal-admin&organization_id=org-a')
            ->assertOk();
    }

    public function test_the_tenancy_routes_require_platform_permission(): void
    {
        $this->get('/core/tenancy?principal_id=principal-org-a&organization_id=org-a')
            ->assertForbidden();

        $viewerPayload = [
            'principal_id' => 'principal-org-a',
            'locale' => 'en',
            'menu' => 'core.tenancy',
        ];

        $this->post('/core/tenancy/organizations', [
            ...$viewerPayload,
            'name' => 'Viewer Org',
            'slug' => 'viewer-org',
            'default_locale' => 'en',
            'default_timezone' => 'Europe/Madrid',
        ])->assertForbidden();

        $this->post('/core/tenancy/organizations/org-a/archive', $viewerPayload)
            ->assertForbidden();

        $this->post('/core/tenancy/scopes', [
            ...$viewerPayload,
            'organization_id' => 'org-a',
            'name' => 'Viewer Scope',
            'slug' => 'viewer-scope',
            'description' => 'Should be blocked.',
        ])->assertForbidden();

        $this->get('/core/tenancy?principal_id=principal-admin&organization_id=org-a')
            ->assertOk();
    }

    public function test_the_functional_actor_routes_require_the_expected_permissions(): void
    {
        $this->get('/core/functional-actors?principal_id=principal-org-a&organization_id=org-a')
            ->assertForbidden();

        $viewerPayload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'core.functional-actors',
        ];

        $this->post('/core/functional-actors', [
            ...$viewerPayload,
            'display_name' => 'Viewer Team',
            'kind' => 'team',
        ])->assertForbidden();

        $this->post('/core/functional-actors/links', [
            ...$viewerPayload,
            'actor_id' => 'actor-it-services',
            'subject_principal_id' => 'principal-org-a',
        ])->assertForbidden();

        $this->post('/core/functional-actors/assignments', [
            ...$viewerPayload,
            'actor_id' => 'actor-it-services',
            'subject_key' => 'asset::asset-erp-prod',
            'assignment_type' => 'reviewer',
        ])->assertForbidden();

        $createResponse = $this->post('/core/functional-actors', [
            ...$viewerPayload,
            'principal_id' => 'principal-admin',
            'display_name' => 'Admin Team',
            'kind' => 'team',
        ]);

        $createResponse->assertFound();
        $this->assertStringContainsString('/app?', (string) $createResponse->headers->get('Location'));
        $this->assertStringContainsString('menu=core.functional-actors', (string) $createResponse->headers->get('Location'));
    }

    public function test_the_identity_local_manage_routes_require_the_expected_permissions(): void
    {
        $viewerPayload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-viewer',
        ];

        $this->post('/plugins/identity/users', [
            ...$viewerPayload,
            'menu' => 'plugin.identity-local.users',
            'display_name' => 'Viewer User',
            'username' => 'viewer.user',
            'email' => 'viewer.user@northwind.test',
            'job_title' => 'Blocked user',
            'magic_link_enabled' => '1',
        ])->assertForbidden();

        $this->post('/plugins/identity/memberships', [
            ...$viewerPayload,
            'menu' => 'plugin.identity-local.memberships',
            'subject_principal_id' => 'principal-org-a',
            'role_keys' => ['asset-viewer'],
            'scope_ids' => ['scope-eu'],
        ])->assertForbidden();

        $this->post('/plugins/identity/users/import/upload', [
            ...$viewerPayload,
            'menu' => 'plugin.identity-local.users',
            'import_file' => UploadedFile::fake()->createWithContent('people.csv', "Name,Email\nViewer User,viewer.user@northwind.test"),
        ])->assertForbidden();

        $userCreateResponse = $this->post('/plugins/identity/users', [
            ...$viewerPayload,
            'principal_id' => 'principal-org-a',
            'membership_id' => 'membership-org-a-hello',
            'menu' => 'plugin.identity-local.users',
            'display_name' => 'Managed User',
            'username' => 'managed.user',
            'email' => 'managed.user@northwind.test',
            'job_title' => 'Identity operator',
            'magic_link_enabled' => '1',
        ]);

        $userCreateResponse->assertFound();
        $this->assertStringContainsString('/admin?', (string) $userCreateResponse->headers->get('Location'));
        $this->assertStringContainsString('menu=plugin.identity-local.users', (string) $userCreateResponse->headers->get('Location'));

        $subjectPrincipalId = DB::table('identity_local_users')
            ->where('email', 'managed.user@northwind.test')
            ->value('principal_id');

        $this->assertIsString($subjectPrincipalId);

        $membershipCreateResponse = $this->post('/plugins/identity/memberships', [
            ...$viewerPayload,
            'principal_id' => 'principal-org-a',
            'membership_id' => 'membership-org-a-hello',
            'menu' => 'plugin.identity-local.memberships',
            'subject_principal_id' => $subjectPrincipalId,
            'role_keys' => ['asset-viewer'],
            'scope_ids' => ['scope-eu'],
        ]);

        $membershipCreateResponse->assertFound();
        $this->assertStringContainsString('/app?', (string) $membershipCreateResponse->headers->get('Location'));
        $this->assertStringContainsString('menu=plugin.identity-local.memberships', (string) $membershipCreateResponse->headers->get('Location'));
    }

    public function test_the_identity_ldap_routes_require_the_expected_permissions(): void
    {
        $this->get('/plugins/identity/ldap?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();

        $viewerPayload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-viewer',
        ];

        $this->post('/plugins/identity/ldap/connection', [
            ...$viewerPayload,
            'name' => 'Viewer LDAP',
            'host' => 'ldap.example.test',
            'port' => 389,
            'base_dn' => 'ou=People,dc=northwind,dc=test',
            'bind_dn' => 'cn=admin,dc=northwind,dc=test',
            'bind_password' => 'admin',
            'login_mode' => 'username',
            'sync_interval_minutes' => 60,
        ])->assertForbidden();

        $this->post('/plugins/identity/ldap/mappings', [
            ...$viewerPayload,
            'ldap_group' => 'cn=it-services,ou=Groups,dc=northwind,dc=test',
            'role_keys' => ['asset-viewer'],
            'scope_ids' => ['scope-it'],
        ])->assertForbidden();

        $this->post('/plugins/identity/ldap/sync', $viewerPayload)
            ->assertForbidden();

        $connectionResponse = $this->post('/plugins/identity/ldap/connection', [
            ...$viewerPayload,
            'principal_id' => 'principal-org-a',
            'membership_id' => 'membership-org-a-hello',
            'name' => 'Managed LDAP',
            'host' => 'ldap.example.test',
            'port' => 389,
            'base_dn' => 'ou=People,dc=northwind,dc=test',
            'bind_dn' => 'cn=admin,dc=northwind,dc=test',
            'bind_password' => 'admin',
            'login_mode' => 'username',
            'sync_interval_minutes' => 60,
        ]);

        $connectionResponse->assertFound();
        $this->assertStringContainsString('/admin?', (string) $connectionResponse->headers->get('Location'));
        $this->assertStringContainsString('menu=plugin.identity-ldap.directory', (string) $connectionResponse->headers->get('Location'));
    }
}
