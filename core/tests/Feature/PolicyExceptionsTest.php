<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
            ->assertSee('Policy areas are business-managed catalog values')
            ->assertSee('Policy list')
            ->assertSee('This list stays focused on area, owner summary, linked controls, review due, state, and Open.')
            ->assertSee('Access Governance Policy')
            ->assertSee('1 exceptions')
            ->assertSee('Add policy')
            ->assertSee('Open');
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
            'area' => 'third-parties',
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
            'area' => 'third-parties',
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
            'owner_actor_id' => 'actor-ava-mason',
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
            ->assertSee('Policy Detail keeps workflow, linked controls, documents, approved exceptions, ownership, and policy maintenance in one workspace.')
            ->assertSee('Policy Detail')
            ->assertSee('Supplier Access Governance Policy')
            ->assertSee('Compliance Office')
            ->assertSee('Legacy supplier bridge approval exception');

        $this->get('/app?menu=plugin.policy-exceptions.exceptions&exception_id=exception-legacy-supplier-bridge-exception&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Exception Detail keeps workflow, evidence, ownership, linked findings, and exception maintenance in one workspace.')
            ->assertSee('Exception Detail')
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

    public function test_policies_and_exceptions_support_multiple_owner_assignments_and_owner_removal(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/policies/policy-access-governance', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.root',
            'title' => 'Access Governance Policy',
            'area' => 'identity',
            'version_label' => 'v1.4',
            'statement' => 'Privileged access must be reviewed quarterly and emergency entitlements must be justified and logged.',
            'linked_control_id' => 'control-access-review',
            'review_due_on' => DB::table('policies')->where('id', 'policy-access-governance')->value('review_due_on'),
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->assertSame(['actor-ava-mason', 'actor-compliance-office'], DB::table('functional_assignments')
            ->where('domain_object_type', 'policy')
            ->where('domain_object_id', 'policy-access-governance')
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->orderBy('functional_actor_id')
            ->pluck('functional_actor_id')
            ->all());

        $this->get('/app?menu=plugin.policy-exceptions.root&policy_id=policy-access-governance&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Owners: 2')
            ->assertSee('Ava Mason')
            ->assertSee('Compliance Office')
            ->assertSee('Identity');

        $policyAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'policy')
            ->where('domain_object_id', 'policy-access-governance')
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-compliance-office')
            ->value('id');

        $this->post("/plugins/policies/policy-access-governance/owners/{$policyAssignmentId}/remove", [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.root',
        ])->assertFound();

        $this->assertSame(['actor-ava-mason'], DB::table('functional_assignments')
            ->where('domain_object_type', 'policy')
            ->where('domain_object_id', 'policy-access-governance')
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->pluck('functional_actor_id')
            ->all());

        $this->post('/plugins/policies/exceptions/exception-break-glass-window', [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.exceptions',
            'title' => 'Extended break-glass review window',
            'rationale' => 'Quarter-end finance close requires a temporary extension of emergency access certification windows.',
            'compensating_control' => 'Daily review by compliance office during the extended window.',
            'linked_finding_id' => 'finding-access-review-gap',
            'expires_on' => DB::table('policy_exceptions')->where('id', 'exception-break-glass-window')->value('expires_on'),
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->assertSame(['actor-ava-mason', 'actor-compliance-office'], DB::table('functional_assignments')
            ->where('domain_object_type', 'policy-exception')
            ->where('domain_object_id', 'exception-break-glass-window')
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->orderBy('functional_actor_id')
            ->pluck('functional_actor_id')
            ->all());

        $this->get('/app?menu=plugin.policy-exceptions.exceptions&exception_id=exception-break-glass-window&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Owners')
            ->assertSee('Ava Mason')
            ->assertSee('Compliance Office');

        $exceptionAssignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'policy-exception')
            ->where('domain_object_id', 'exception-break-glass-window')
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-compliance-office')
            ->value('id');

        $this->post("/plugins/policies/exceptions/exception-break-glass-window/owners/{$exceptionAssignmentId}/remove", [
            ...$payload,
            'menu' => 'plugin.policy-exceptions.exceptions',
        ])->assertFound();

        $this->assertSame(['actor-ava-mason'], DB::table('functional_assignments')
            ->where('domain_object_type', 'policy-exception')
            ->where('domain_object_id', 'exception-break-glass-window')
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->pluck('functional_actor_id')
            ->all());
    }

    public function test_policy_area_uses_the_governed_catalog_and_rejects_unknown_values(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.policy-exceptions.root',
            'membership_id' => 'membership-org-a-hello',
            'title' => 'Policy Area Validation',
            'version_label' => 'v1.0',
            'statement' => 'Area must come from the governed catalog.',
            'linked_control_id' => 'control-access-review',
        ];

        $this->post('/plugins/policies', [
            ...$payload,
            'area' => 'Not A Governed Area',
        ])->assertSessionHasErrors(['area']);

        $this->post('/core/reference-data/entries', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'catalog_key' => 'policies.areas',
            'option_key' => 'supplier-assurance',
            'label' => 'Supplier assurance',
            'description' => 'Managed policy area for supplier governance.',
            'sort_order' => 180,
            'locale' => 'en',
            'menu' => 'core.reference-data',
        ])->assertFound();

        $this->post('/plugins/policies', [
            ...$payload,
            'title' => 'Supplier Assurance Policy',
            'area' => 'supplier-assurance',
        ])->assertFound();

        $this->get('/app?menu=plugin.policy-exceptions.root&policy_id=policy-supplier-assurance-policy&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier assurance');
    }
}
