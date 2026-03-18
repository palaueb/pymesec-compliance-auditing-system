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
            ->assertSee('Save evidence');
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
}
