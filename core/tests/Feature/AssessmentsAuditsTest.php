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
        $this->get('/app?menu=plugin.assessments-audits.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Assessment Campaigns')
            ->assertSee('Q2 Access and Resilience Review')
            ->assertSee('Quarterly Access Review')
            ->assertSee('Access rights')
            ->assertSee('Access review evidence gap')
            ->assertSee('Review checklist')
            ->assertSee('Create assessment');
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

        $this->get('/app?menu=plugin.assessments-audits.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
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

        $this->get('/app?menu=plugin.assessments-audits.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
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

        $this->get('/app?menu=plugin.assessments-audits.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
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
}
