<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FindingsRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_findings_plugin_route_requires_view_permission(): void
    {
        $this->get('/plugins/findings?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'finding-access-review-gap',
                'title' => 'Access review evidence gap',
            ]);

        $this->get('/plugins/findings?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_findings_register_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.findings-remediation.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Findings Register')
            ->assertSee('Access review evidence gap')
            ->assertSee('1 remediation actions')
            ->assertSee('Create Finding');
    }

    public function test_findings_can_be_created_edited_and_linked_to_remediation_actions(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.findings-remediation.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/findings', [
            ...$payload,
            'title' => 'Supplier review exception gap',
            'severity' => 'high',
            'description' => 'Exception approvals are not linked to the supplier control review.',
            'linked_control_id' => 'control-access-review',
            'linked_risk_id' => 'risk-access-drift',
            'due_on' => '2026-04-10',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->post('/plugins/findings/finding-supplier-review-exception-gap', [
            ...$payload,
            'title' => 'Supplier review exception traceability gap',
            'severity' => 'critical',
            'description' => 'Exception approvals remain outside the signed supplier review package.',
            'linked_control_id' => 'control-access-review',
            'linked_risk_id' => 'risk-access-drift',
            'due_on' => '2026-04-15',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->post('/plugins/findings/finding-supplier-review-exception-gap/actions', [
            ...$payload,
            'menu' => 'plugin.findings-remediation.board',
            'title' => 'Upload signed exception log',
            'status' => 'planned',
            'notes' => 'Collect approvals and attach them to the finding.',
            'due_on' => '2026-04-08',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->post('/plugins/findings/actions/action-upload-signed-exception-log', [
            ...$payload,
            'menu' => 'plugin.findings-remediation.board',
            'title' => 'Upload signed exception evidence log',
            'status' => 'in-progress',
            'notes' => 'Evidence collection has started.',
            'due_on' => '2026-04-09',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->get('/app?menu=plugin.findings-remediation.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier review exception traceability gap')
            ->assertSee('Compliance Office');

        $this->get('/app?menu=plugin.findings-remediation.board&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Upload signed exception evidence log')
            ->assertSee('Evidence collection has started.')
            ->assertSee('Ava Mason');
    }

    public function test_findings_transition_and_artifact_render_on_the_register(): void
    {
        Storage::fake('local');

        $this->post('/plugins/findings/finding-access-review-gap/transitions/triage', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.findings-remediation.root',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $this->post('/plugins/findings/finding-access-review-gap/artifacts', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.findings-remediation.root',
            'membership_id' => 'membership-org-a-hello',
            'label' => 'Missing approval note',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('approval-gap.txt', 'approval gap'),
        ])->assertFound();

        $this->get('/app?menu=plugin.findings-remediation.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('triage')
            ->assertSee('Missing approval note')
            ->assertSee('approval-gap.txt');
    }
}
