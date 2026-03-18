<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_evidence_plugin_route_requires_view_permission(): void
    {
        $this->get('/plugins/evidence?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-viewer')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'evidence-access-review-pack',
                'title' => 'Q2 privileged access evidence pack',
                'status' => 'approved',
            ]);

        $this->get('/plugins/evidence?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_evidence_screen_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.evidence-management.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello&evidence_id=evidence-access-review-pack')
            ->assertOk()
            ->assertSee('Evidence Library')
            ->assertSee('Q2 privileged access evidence pack')
            ->assertSee('Quarterly Access Review')
            ->assertSee('Q2 access review pack')
            ->assertSee('Save evidence')
            ->assertSee('Open source record');
    }

    public function test_evidence_records_can_be_created_from_uploads_and_existing_artifacts(): void
    {
        Storage::fake('local');

        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'menu' => 'plugin.evidence-management.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/evidence', [
            ...$payload,
            'title' => 'Supplier onboarding evidence',
            'summary' => 'Collected onboarding approvals and access reviews for the quarter.',
            'evidence_kind' => 'document',
            'status' => 'active',
            'valid_from' => '2026-04-01',
            'valid_until' => '2026-06-30',
            'review_due_on' => '2026-06-15',
            'link_targets' => ['control:control-access-review', 'assessment:assessment-q2-access-resilience'],
            'artifact' => UploadedFile::fake()->createWithContent('supplier-onboarding.pdf', 'supplier evidence'),
        ])->assertFound();

        $uploadedEvidenceId = 'evidence-supplier-onboarding-evidence';
        $uploadedArtifactId = DB::table('evidence_records')
            ->where('id', $uploadedEvidenceId)
            ->value('artifact_id');

        $this->assertIsString($uploadedArtifactId);
        $this->assertNotSame('artifact-access-review-pack', $uploadedArtifactId);

        $this->assertDatabaseHas('evidence_record_links', [
            'evidence_id' => $uploadedEvidenceId,
            'domain_type' => 'control',
            'domain_id' => 'control-access-review',
        ]);

        $this->post('/plugins/evidence', [
            ...$payload,
            'title' => 'Promoted access workpaper',
            'summary' => 'Reuse the signed review pack as a governed evidence record.',
            'evidence_kind' => 'workpaper',
            'status' => 'approved',
            'validated_at' => '2026-04-25',
            'validated_by_principal_id' => 'principal-org-a',
            'validation_notes' => 'Validated during quarterly access campaign wrap-up.',
            'existing_artifact_id' => 'artifact-access-review-pack',
            'link_targets' => ['finding:finding-access-review-gap', 'assessment:assessment-q2-access-resilience'],
        ])->assertFound();

        $this->assertDatabaseHas('evidence_records', [
            'id' => 'evidence-promoted-access-workpaper',
            'artifact_id' => 'artifact-access-review-pack',
            'status' => 'approved',
        ]);

        $this->post('/plugins/evidence/evidence-promoted-access-workpaper', [
            ...$payload,
            'title' => 'Promoted access workpaper',
            'summary' => 'Reuse the signed review pack as a governed evidence record.',
            'evidence_kind' => 'workpaper',
            'status' => 'superseded',
            'valid_from' => '2026-04-01',
            'valid_until' => '2026-07-01',
            'review_due_on' => '2026-06-20',
            'validated_at' => '2026-04-25',
            'validated_by_principal_id' => 'principal-org-a',
            'validation_notes' => 'Superseded by the next quarter evidence pack.',
            'existing_artifact_id' => 'artifact-access-review-pack',
            'link_targets' => ['control:control-access-review'],
        ])->assertFound();

        $this->get('/app?menu=plugin.evidence-management.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello&evidence_id=evidence-promoted-access-workpaper')
            ->assertOk()
            ->assertSee('Promoted access workpaper')
            ->assertSee('superseded')
            ->assertSee('Quarterly Access Review');
    }

    public function test_evidence_manage_routes_require_manage_permission(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'menu' => 'plugin.evidence-management.root',
            'membership_id' => 'membership-org-a-viewer',
            'title' => 'Viewer evidence',
            'summary' => 'Viewer should not create evidence.',
            'evidence_kind' => 'document',
            'status' => 'draft',
            'existing_artifact_id' => 'artifact-access-review-pack',
        ];

        $this->post('/plugins/evidence', $payload)->assertForbidden();
        $this->post('/plugins/evidence/evidence-access-review-pack', $payload)->assertForbidden();
    }

    public function test_artifacts_can_be_promoted_to_evidence_with_inferred_links(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->get('/app?menu=plugin.evidence-management.root&principal_id=principal-org-a&organization_id=org-a&scope_id=scope-eu&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Recent uploads ready for evidence')
            ->assertSee('Access review follow-up notes');

        $this->post('/plugins/evidence/promote/artifact-access-review-follow-up', $payload)->assertFound();

        $evidenceId = DB::table('evidence_records')
            ->where('artifact_id', 'artifact-access-review-follow-up')
            ->value('id');

        $this->assertIsString($evidenceId);

        $this->assertDatabaseHas('evidence_records', [
            'id' => $evidenceId,
            'artifact_id' => 'artifact-access-review-follow-up',
            'status' => 'active',
            'evidence_kind' => 'workpaper',
        ]);

        $this->assertDatabaseHas('evidence_record_links', [
            'evidence_id' => $evidenceId,
            'domain_type' => 'assessment',
            'domain_id' => 'assessment-q2-access-resilience',
        ]);

        $this->assertDatabaseHas('evidence_record_links', [
            'evidence_id' => $evidenceId,
            'domain_type' => 'control',
            'domain_id' => 'control-access-review',
        ]);

        $this->assertDatabaseHas('evidence_record_links', [
            'evidence_id' => $evidenceId,
            'domain_type' => 'finding',
            'domain_id' => 'finding-access-review-gap',
        ]);

        $this->post('/plugins/evidence/promote/artifact-access-review-follow-up', $payload)->assertFound();

        $this->assertSame(1, DB::table('evidence_records')->where('artifact_id', 'artifact-access-review-follow-up')->count());

        $this->get('/app?menu=plugin.evidence-management.root&principal_id=principal-org-a&organization_id=org-a&scope_id=scope-eu&membership_ids[]=membership-org-a-hello&evidence_id='.$evidenceId)
            ->assertOk()
            ->assertSee('Open source record')
            ->assertSee('Quarterly Access Review')
            ->assertSee('Control');
    }

    public function test_evidence_artifacts_can_be_previewed_downloaded_and_reminders_can_be_queued(): void
    {
        Storage::fake('local');

        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'menu' => 'plugin.evidence-management.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/evidence', [
            ...$payload,
            'title' => 'Review notes evidence',
            'summary' => 'Previewable evidence payload.',
            'evidence_kind' => 'document',
            'status' => 'active',
            'review_due_on' => now()->addDays(3)->toDateString(),
            'valid_until' => now()->addDays(5)->toDateString(),
            'artifact' => UploadedFile::fake()->createWithContent('review-notes.txt', 'previewable evidence body'),
        ])->assertFound();

        $evidenceId = 'evidence-review-notes-evidence';

        $this->get('/plugins/evidence/'.$evidenceId.'/preview?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-viewer')
            ->assertOk()
            ->assertSee('previewable evidence body');

        $this->get('/plugins/evidence/'.$evidenceId.'/download?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-viewer')
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->post('/plugins/evidence/'.$evidenceId.'/reminders/review-due', $payload)
            ->assertFound();

        $this->assertDatabaseHas('notifications', [
            'type' => 'plugin.evidence-management.review-due',
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
        ]);

        $this->artisan('notifications:dispatch-due')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'type' => 'plugin.evidence-management.review-due',
            'status' => 'dispatched',
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
        ]);
    }

    public function test_due_evidence_reminders_can_be_queued_from_the_console(): void
    {
        DB::table('evidence_records')
            ->where('id', 'evidence-access-review-pack')
            ->update([
                'review_due_on' => now()->addDays(2)->toDateString(),
                'valid_until' => now()->addDays(4)->toDateString(),
                'review_reminder_sent_at' => null,
                'expiry_reminder_sent_at' => null,
            ]);

        $this->artisan('evidence:queue-reminders --organization_id=org-a --scope_id=scope-eu')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'type' => 'plugin.evidence-management.review-due',
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => 'plugin.evidence-management.expiry-soon',
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
        ]);
    }
}
