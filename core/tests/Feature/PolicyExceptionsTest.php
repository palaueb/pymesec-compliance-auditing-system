<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PolicyExceptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_policy_plugin_route_requires_view_permission(): void
    {
        $this->get('/plugins/policies?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'policy-access-governance',
                'title' => 'Access Governance Policy',
            ]);

        $this->get('/plugins/policies?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_policy_register_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.policy-exceptions.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Policies Register')
            ->assertSee('Access Governance Policy')
            ->assertSee('1 exceptions')
            ->assertSee('Add policy')
            ->assertSee('Edit details');
    }

    public function test_policies_and_exceptions_can_be_created_and_edited_from_the_shell_runtime(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.policy-exceptions.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/policies', [
            ...$payload,
            'title' => 'Supplier Governance Policy',
            'area' => 'Third Parties',
            'version_label' => 'v1.0',
            'statement' => 'Suppliers with privileged integration access must be reviewed every quarter.',
            'linked_control_id' => 'control-access-review',
            'review_due_on' => '2026-04-20',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->post('/plugins/policies/policy-supplier-governance-policy', [
            ...$payload,
            'title' => 'Supplier Access Governance Policy',
            'area' => 'Third Parties',
            'version_label' => 'v1.1',
            'statement' => 'Privileged supplier access must be reviewed quarterly with signed approver evidence.',
            'linked_control_id' => 'control-access-review',
            'review_due_on' => '2026-04-25',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->post('/plugins/policies/policy-supplier-governance-policy/exceptions', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.exceptions',
            'title' => 'Legacy supplier bridge exception',
            'rationale' => 'Legacy integration cannot meet quarterly attestation while bridge migration is active.',
            'compensating_control' => 'Weekly manual access export review.',
            'linked_finding_id' => 'finding-access-review-gap',
            'expires_on' => '2026-04-12',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->post('/plugins/policies/exceptions/exception-legacy-supplier-bridge-exception', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.exceptions',
            'exception_id' => 'exception-legacy-supplier-bridge-exception',
            'title' => 'Legacy supplier bridge approval exception',
            'rationale' => 'Bridge migration remains active until the final supplier connector is retired.',
            'compensating_control' => 'Twice-weekly manual access export review.',
            'linked_finding_id' => 'finding-access-review-gap',
            'expires_on' => '2026-04-15',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->get('/app?menu=plugin.policy-exceptions.root&policy_id=policy-supplier-governance-policy&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier Access Governance Policy')
            ->assertSee('Compliance Office')
            ->assertSee('Legacy supplier bridge approval exception');

        $this->get('/app?menu=plugin.policy-exceptions.exceptions&exception_id=exception-legacy-supplier-bridge-exception&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Legacy supplier bridge approval exception')
            ->assertSee('Twice-weekly manual access export review.')
            ->assertSee('Ava Mason');
    }

    public function test_policy_and_exception_transitions_and_artifacts_render_in_the_shell(): void
    {
        Storage::fake('local');

        $this->post('/plugins/policies/policy-access-governance/transitions/submit-review', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.policy-exceptions.root',
            'policy_id' => 'policy-access-governance',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $this->post('/plugins/policies/exceptions/exception-break-glass-window/transitions/approve', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.policy-exceptions.exceptions',
            'exception_id' => 'exception-break-glass-window',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $this->post('/plugins/policies/policy-access-governance/artifacts', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.policy-exceptions.root',
            'policy_id' => 'policy-access-governance',
            'membership_id' => 'membership-org-a-hello',
            'label' => 'Policy approval memo',
            'artifact_type' => 'document',
            'artifact' => UploadedFile::fake()->createWithContent('policy-memo.pdf', 'memo'),
        ])->assertFound();

        $this->post('/plugins/policies/exceptions/exception-break-glass-window/artifacts', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.policy-exceptions.exceptions',
            'exception_id' => 'exception-break-glass-window',
            'membership_id' => 'membership-org-a-hello',
            'label' => 'Exception sign-off',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('exception-signoff.txt', 'signoff'),
        ])->assertFound();

        $this->get('/app?menu=plugin.policy-exceptions.root&policy_id=policy-access-governance&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('submit-review')
            ->assertSee('Policy approval memo')
            ->assertSee('policy-memo.pdf');

        $this->get('/app?menu=plugin.policy-exceptions.exceptions&exception_id=exception-break-glass-window&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('approve')
            ->assertSee('Exception sign-off')
            ->assertSee('exception-signoff.txt');
    }
}
