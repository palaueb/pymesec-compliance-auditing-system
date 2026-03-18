<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssessmentsAuditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_assessments_plugin_route_requires_view_permission(): void
    {
        $this->get('/plugins/assessments?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'assessment-q2-access-resilience',
                'title' => 'Q2 Access and Resilience Review',
                'status' => 'active',
            ]);

        $this->get('/plugins/assessments?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_assessments_screen_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.assessments-audits.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello&assessment_id=assessment-q2-access-resilience')
            ->assertOk()
            ->assertSee('Assessment Campaigns')
            ->assertSee('Q2 Access and Resilience Review')
            ->assertSee('Quarterly Access Review')
            ->assertSee('Access rights')
            ->assertSee('Access review evidence gap')
            ->assertSee('Update review')
            ->assertSee('Edit assessment details');
    }

    public function test_assessments_can_be_created_and_edited_from_the_shell_runtime(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.assessments-audits.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/assessments', [
            ...$payload,
            'title' => 'Supplier Access Audit',
            'summary' => 'Focused review of supplier onboarding and entitlement controls.',
            'framework_id' => 'framework-iso-27001',
            'scope_id' => 'scope-eu',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-15',
            'status' => 'draft',
            'control_ids' => ['control-access-review', 'control-backup-governance'],
        ])->assertFound();

        $this->get('/app?menu=plugin.assessments-audits.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello&assessment_id=assessment-supplier-access-audit')
            ->assertOk()
            ->assertSee('Supplier Access Audit')
            ->assertSee('Focused review of supplier onboarding and entitlement controls.')
            ->assertSee('Quarterly Access Review')
            ->assertSee('Backup Governance');

        $this->post('/plugins/assessments/assessment-supplier-access-audit', [
            ...$payload,
            'title' => 'Supplier Access Review',
            'summary' => 'Updated review scope for supplier access and governance.',
            'framework_id' => 'framework-iso-27001',
            'scope_id' => 'scope-eu',
            'starts_on' => '2026-06-03',
            'ends_on' => '2026-06-18',
            'status' => 'active',
            'control_ids' => ['control-access-review'],
        ])->assertFound();

        $this->get('/app?menu=plugin.assessments-audits.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello&assessment_id=assessment-supplier-access-audit')
            ->assertOk()
            ->assertSee('Supplier Access Review')
            ->assertSee('Updated review scope for supplier access and governance.')
            ->assertSee('active');
    }

    public function test_assessments_support_reviews_findings_workpapers_and_summary_export(): void
    {
        Storage::fake('local');

        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'menu' => 'plugin.assessments-audits.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/assessments/assessment-q2-access-resilience/reviews/control-access-review', [
            ...$payload,
            'result' => 'fail',
            'test_notes' => 'Control sample incomplete for the reviewed quarter.',
            'conclusion' => 'Evidence package does not support a full positive conclusion.',
            'reviewed_on' => '2026-04-22',
        ])->assertFound();

        $this->post('/plugins/assessments/assessment-q2-access-resilience/reviews/control-backup-governance/findings', [
            ...$payload,
            'title' => 'Restore evidence retention gap',
            'severity' => 'high',
            'description' => 'Restore drill approvals were not retained with the campaign workpapers.',
            'due_on' => '2026-05-10',
        ])->assertFound();

        $this->post('/plugins/assessments/assessment-q2-access-resilience/reviews/control-access-review/artifacts', [
            ...$payload,
            'label' => 'Control sample notes',
            'artifact_type' => 'workpaper',
            'artifact' => UploadedFile::fake()->createWithContent('review-notes.txt', 'sample notes'),
        ])->assertFound();

        $this->get('/app?menu=plugin.assessments-audits.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello&assessment_id=assessment-q2-access-resilience')
            ->assertOk()
            ->assertSee('Control sample incomplete for the reviewed quarter.')
            ->assertSee('Evidence package does not support a full positive conclusion.')
            ->assertSee('Restore evidence retention gap')
            ->assertSee('Control sample notes')
            ->assertSee('review-notes.txt');

        $this->get('/plugins/assessments/assessment-q2-access-resilience/report?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertHeader('content-type', 'text/markdown; charset=UTF-8')
            ->assertSee('Q2 Access and Resilience Review')
            ->assertSee('Restore evidence retention gap')
            ->assertSee('Quarterly Access Review [FAIL]');
    }

    public function test_assessments_support_sign_off_closure_and_export_bundle_formats(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'menu' => 'plugin.assessments-audits.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/assessments/assessment-q2-access-resilience/transitions/sign-off', [
            ...$payload,
            'signed_off_on' => '2026-04-25',
            'signoff_notes' => 'Checklist reviewed and approved for management submission.',
        ])->assertFound();

        $this->post('/plugins/assessments/assessment-q2-access-resilience/transitions/close', [
            ...$payload,
            'closed_on' => '2026-04-28',
            'closure_summary' => 'Assessment closed after confirming remediation ownership.',
        ])->assertFound();

        $this->get('/app?menu=plugin.assessments-audits.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello&assessment_id=assessment-q2-access-resilience')
            ->assertOk()
            ->assertSee('Signed off')
            ->assertSee('Checklist reviewed and approved for management submission.')
            ->assertSee('Assessment closed after confirming remediation ownership.');

        $this->get('/plugins/assessments/assessment-q2-access-resilience/report?format=csv&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee('assessment_id,assessment_title,status')
            ->assertSee('assessment-q2-access-resilience');

        $this->get('/plugins/assessments/assessment-q2-access-resilience/report?format=json&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('assessment.id', 'assessment-q2-access-resilience')
            ->assertJsonPath('assessment.status', 'closed')
            ->assertJsonPath('assessment.signoff_notes', 'Checklist reviewed and approved for management submission.')
            ->assertJsonPath('assessment.closure_summary', 'Assessment closed after confirming remediation ownership.');
    }
}
