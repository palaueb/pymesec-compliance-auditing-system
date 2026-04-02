<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PymeSec\Core\Notifications\NotificationMailSettingsRepository;
use PymeSec\Core\Notifications\OutboundNotificationMailer;
use Tests\TestCase;

class ThirdPartyRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_third_party_risk_plugin_route_requires_view_permission(): void
    {
        $this->get('/plugins/vendors?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'vendor-northbridge-payroll',
                'legal_name' => 'Northbridge Payroll Services',
            ]);

        $this->get('/plugins/vendors?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_vendor_review_screen_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.third-party-risk.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Vendor Reviews')
            ->assertSee('Vendor register and current review workspace')
            ->assertSee('Vendor register list')
            ->assertSee('This list stays focused on tier, current review, vendor status, owner summary, and Open.')
            ->assertSee('Northbridge Payroll Services')
            ->assertSee('Add vendor')
            ->assertSee('Open');

        $this->get('/app?menu=plugin.third-party-risk.root&vendor_id=vendor-northbridge-payroll&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Vendor Review keeps intake context, evidence, decision notes, linked internal records, and reviewer ownership in one workspace.')
            ->assertSee('Vendor Review')
            ->assertSee('EU Payroll Processor Review')
            ->assertSee('Payroll Processor Baseline')
            ->assertSee('External collaboration')
            ->assertSee('Collaboration')
            ->assertSee('Comments')
            ->assertSee('Follow-up requests')
            ->assertSee('Kickoff review completed with procurement.')
            ->assertSee('Collect signed emergency access review package')
            ->assertSee('Stage: Remediation')
            ->assertSee('Mention: Compliance Office (team)')
            ->assertSee('Timeline and activity')
            ->assertSee('Review created')
            ->assertSee('2026 payroll onboarding review')
            ->assertSee('Waiting for signed access review package and payroll exception handling evidence.')
            ->assertSee('Questionnaire')
            ->assertSee('Access governance')
            ->assertSee('Incident handling')
            ->assertSee('Do privileged payroll support users receive quarterly access certification?')
            ->assertSee('Library suggestions')
            ->assertSee('Initial notification within 24 hours, detailed update within 72 hours.')
            ->assertSee('Back to vendors')
            ->assertSee('Edit vendor and review')
            ->assertSee('Workflow');
    }

    public function test_vendors_can_be_created_and_edited_from_the_shell_runtime(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/vendors', [
            ...$payload,
            'legal_name' => 'Supplier Review Partner',
            'service_summary' => 'Security questionnaire intake and review support.',
            'tier' => 'high',
            'website' => 'https://supplier-review-partner.test',
            'primary_contact_name' => 'Julia West',
            'primary_contact_email' => 'julia.west@supplier-review-partner.test',
            'scope_id' => 'scope-eu',
            'review_profile_id' => 'vendor-review-profile-eu-payroll-processor',
            'questionnaire_template_id' => 'vendor-questionnaire-template-payroll-baseline',
            'review_title' => '2026 onboarding review',
            'inherent_risk' => 'high',
            'review_summary' => 'Initial due diligence for outsourced questionnaire handling.',
            'decision_notes' => 'Pending evidence upload.',
            'linked_asset_id' => 'asset-erp-prod',
            'linked_control_id' => 'control-access-review',
            'linked_risk_id' => 'risk-access-drift',
            'linked_finding_id' => 'finding-access-review-gap',
            'next_review_due_on' => now()->addDays(60)->toDateString(),
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->get('/app?menu=plugin.third-party-risk.root&vendor_id=vendor-supplier-review-partner&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier Review Partner')
            ->assertSee('Initial due diligence for outsourced questionnaire handling.')
            ->assertSee('Payroll Processor Baseline')
            ->assertSee('Are subprocessor changes subject to approval or prior notification?');

        $this->post('/plugins/vendors/vendor-supplier-review-partner', [
            ...$payload,
            'legal_name' => 'Supplier Review Partner Ltd',
            'service_summary' => 'Security questionnaire intake, evidence review, and follow-up support.',
            'tier' => 'critical',
            'website' => 'https://supplier-review-partner.test',
            'primary_contact_name' => 'Julia West',
            'primary_contact_email' => 'julia.west@supplier-review-partner.test',
            'scope_id' => 'scope-eu',
            'review_id' => 'vendor-review-2026-onboarding-review',
            'review_profile_id' => 'vendor-review-profile-eu-payroll-processor',
            'questionnaire_template_id' => 'vendor-questionnaire-template-payroll-baseline',
            'review_title' => '2026 onboarding review',
            'inherent_risk' => 'critical',
            'review_summary' => 'Expanded due diligence for outsourced questionnaire intake and evidence review.',
            'decision_notes' => 'Escalate privileged access evidence before approval.',
            'linked_asset_id' => 'asset-erp-prod',
            'linked_control_id' => 'control-access-review',
            'linked_risk_id' => 'risk-access-drift',
            'linked_finding_id' => 'finding-access-review-gap',
            'next_review_due_on' => now()->addDays(45)->toDateString(),
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->get('/app?menu=plugin.third-party-risk.root&vendor_id=vendor-supplier-review-partner&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier Review Partner Ltd')
            ->assertSee('Expanded due diligence for outsourced questionnaire intake and evidence review.')
            ->assertSee('Compliance Office');
    }

    public function test_vendor_review_transition_and_artifact_render_on_the_workspace(): void
    {
        Storage::fake('local');

        $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/transitions/start-review', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/artifacts', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
            'label' => 'Payroll package',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('payroll-package.pdf', 'payroll evidence'),
        ])->assertFound();

        $this->get('/app?menu=plugin.third-party-risk.root&vendor_id=vendor-northbridge-payroll&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Payroll package')
            ->assertSee('payroll-package.pdf')
            ->assertSee('start-review');
    }

    public function test_vendor_review_workspace_renders_linked_finding_and_remediation_continuity(): void
    {
        $this->get('/app?menu=plugin.third-party-risk.root&vendor_id=vendor-northbridge-payroll&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Findings and remediation')
            ->assertSee('Access review evidence gap')
            ->assertSee('Publish signed reviewer evidence pack')
            ->assertSee('Open remediation actions: 1')
            ->assertSee('Open linked finding');
    }

    public function test_vendor_review_workspace_supports_collaboration_comments_and_follow_up_requests(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/collaboration/comments', [
            ...$payload,
            'body' => 'Security operations confirmed the final evidence package is due tomorrow.',
        ])->assertFound();

        $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/collaboration/requests', [
            ...$payload,
            'title' => 'Confirm vendor exception package',
            'details' => 'Validate the final emergency access exception package before approval.',
            'status' => 'open',
            'priority' => 'urgent',
            'handoff_state' => 'review',
            'mentioned_actor_ids' => ['actor-ava-mason'],
            'assigned_actor_id' => 'actor-compliance-office',
            'due_on' => now()->addDays(2)->toDateString(),
        ])->assertFound();

        $requestId = (string) DB::table('collaboration_requests')
            ->where('owner_component', 'third-party-risk')
            ->where('subject_type', 'vendor-review')
            ->where('subject_id', 'vendor-review-northbridge-payroll-2026')
            ->where('title', 'Confirm vendor exception package')
            ->value('id');

        $this->assertNotSame('', $requestId);

        $this->post("/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/collaboration/requests/{$requestId}", [
            ...$payload,
            'title' => 'Confirm vendor exception package',
            'details' => 'Validated and waiting for final management sign-off.',
            'status' => 'waiting',
            'priority' => 'urgent',
            'handoff_state' => 'approval',
            'mentioned_actor_ids' => ['actor-ava-mason'],
            'assigned_actor_id' => 'actor-compliance-office',
            'due_on' => now()->addDays(2)->toDateString(),
        ])->assertFound();

        $this->get('/app?menu=plugin.third-party-risk.root&vendor_id=vendor-northbridge-payroll&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Security operations confirmed the final evidence package is due tomorrow.')
            ->assertSee('Confirm vendor exception package')
            ->assertSee('Validated and waiting for final management sign-off.')
            ->assertSee('Owner: Compliance Office')
            ->assertSee('Waiting')
            ->assertSee('Stage: Approval')
            ->assertSee('Mention cue')
            ->assertSee('Mention: Ava Mason (person)')
            ->assertSee('Comment added')
            ->assertSee('Follow-up request waiting');
    }

    public function test_vendor_review_workspace_supports_shared_collaboration_drafts_and_promotion(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/collaboration/drafts', [
            ...$payload,
            'draft_type' => 'comment',
            'body' => 'Draft note for payroll security before publishing the final reviewer update.',
            'mentioned_actor_ids' => ['actor-ava-mason'],
        ])->assertFound();

        $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/collaboration/drafts', [
            ...$payload,
            'draft_type' => 'request',
            'title' => 'Prepare final approval note',
            'details' => 'Keep the approval handoff note ready once the signed evidence pack lands.',
            'priority' => 'high',
            'handoff_state' => 'approval',
            'mentioned_actor_ids' => ['actor-compliance-office'],
            'assigned_actor_id' => 'actor-ava-mason',
            'due_on' => now()->addDays(3)->toDateString(),
        ])->assertFound();

        $requestDraftId = (string) DB::table('collaboration_drafts')
            ->where('owner_component', 'third-party-risk')
            ->where('subject_type', 'vendor-review')
            ->where('subject_id', 'vendor-review-northbridge-payroll-2026')
            ->where('draft_type', 'request')
            ->where('title', 'Prepare final approval note')
            ->value('id');

        $commentDraftId = (string) DB::table('collaboration_drafts')
            ->where('owner_component', 'third-party-risk')
            ->where('subject_type', 'vendor-review')
            ->where('subject_id', 'vendor-review-northbridge-payroll-2026')
            ->where('draft_type', 'comment')
            ->where('body', 'Draft note for payroll security before publishing the final reviewer update.')
            ->value('id');

        $this->assertNotSame('', $requestDraftId);
        $this->assertNotSame('', $commentDraftId);

        $this->post("/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/collaboration/drafts/{$requestDraftId}", [
            ...$payload,
            'draft_type' => 'request',
            'title' => 'Prepare final approval note',
            'details' => 'Keep the approval handoff note ready and include the emergency access evidence summary.',
            'priority' => 'urgent',
            'handoff_state' => 'approval',
            'mentioned_actor_ids' => ['actor-ava-mason'],
            'assigned_actor_id' => 'actor-compliance-office',
            'due_on' => now()->addDays(2)->toDateString(),
        ])->assertFound();

        $this->post("/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/collaboration/drafts/{$commentDraftId}/promote-comment", $payload)->assertFound();
        $this->post("/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/collaboration/drafts/{$requestDraftId}/promote-request", $payload)->assertFound();

        $this->assertDatabaseMissing('collaboration_drafts', ['id' => $commentDraftId]);
        $this->assertDatabaseMissing('collaboration_drafts', ['id' => $requestDraftId]);

        $this->get('/app?menu=plugin.third-party-risk.root&vendor_id=vendor-northbridge-payroll&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Shared drafts')
            ->assertSee('Draft note for payroll security before publishing the final reviewer update.')
            ->assertSee('Prepare final approval note')
            ->assertSee('Keep the approval handoff note ready and include the emergency access evidence summary.')
            ->assertSee('Draft promoted to comment')
            ->assertSee('Draft promoted to follow-up request')
            ->assertSee('Mention: Ava Mason (person)');
    }

    public function test_vendor_review_questionnaire_items_can_be_created_and_updated(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/questionnaire-items', [
            ...$payload,
            'section_title' => 'Evidence handling',
            'prompt' => 'Is MFA required for vendor support access?',
            'response_type' => 'yes-no',
            'response_status' => 'draft',
            'answer_text' => 'Pending confirmation.',
            'follow_up_notes' => 'Ask for the current admin access standard.',
        ])->assertFound();

        $itemId = (string) DB::table('questionnaire_subject_items')
            ->where('owner_component', 'third-party-risk')
            ->where('subject_type', 'vendor-review')
            ->where('subject_id', 'vendor-review-northbridge-payroll-2026')
            ->where('prompt', 'Is MFA required for vendor support access?')
            ->value('id');

        $this->assertNotSame('', $itemId);
        $this->assertSame('Evidence handling', DB::table('questionnaire_subject_items')->where('id', $itemId)->value('section_title'));

        $this->post("/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/questionnaire-items/{$itemId}", [
            ...$payload,
            'section_title' => 'Access governance',
            'prompt' => 'Is MFA required for vendor support access?',
            'response_type' => 'yes-no',
            'response_status' => 'accepted',
            'answer_text' => 'Yes. MFA is required for all privileged vendor support users.',
            'follow_up_notes' => 'Validated against the latest onboarding standard.',
            'save_to_answer_library' => '1',
        ])->assertFound();

        $this->assertSame('Access governance', DB::table('questionnaire_subject_items')->where('id', $itemId)->value('section_title'));
        $this->assertDatabaseHas('questionnaire_answer_library_entries', [
            'owner_component' => 'third-party-risk',
            'subject_type' => 'vendor-review',
            'organization_id' => 'org-a',
            'response_type' => 'yes-no',
            'prompt_text' => 'Is MFA required for vendor support access?',
            'answer_text' => 'Yes. MFA is required for all privileged vendor support users.',
        ]);

        $this->get('/app?menu=plugin.third-party-risk.root&vendor_id=vendor-northbridge-payroll&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Access governance')
            ->assertSee('Is MFA required for vendor support access?')
            ->assertSee('Yes. MFA is required for all privileged vendor support users.')
            ->assertSee('Validated against the latest onboarding standard.')
            ->assertSee('Accepted');
    }

    public function test_questionnaire_template_can_be_applied_without_creating_duplicates(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
            'questionnaire_template_id' => 'vendor-questionnaire-template-payroll-baseline',
        ];

        $this->assertSame(2, DB::table('questionnaire_subject_items')
            ->where('owner_component', 'third-party-risk')
            ->where('subject_type', 'vendor-review')
            ->where('subject_id', 'vendor-review-northbridge-payroll-2026')
            ->count());

        $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/questionnaire-template/apply', $payload)
            ->assertFound();

        $this->assertSame(3, DB::table('questionnaire_subject_items')
            ->where('owner_component', 'third-party-risk')
            ->where('subject_type', 'vendor-review')
            ->where('subject_id', 'vendor-review-northbridge-payroll-2026')
            ->count());

        $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/questionnaire-template/apply', $payload)
            ->assertFound();

        $this->assertSame(3, DB::table('questionnaire_subject_items')
            ->where('owner_component', 'third-party-risk')
            ->where('subject_type', 'vendor-review')
            ->where('subject_id', 'vendor-review-northbridge-payroll-2026')
            ->count());

        $this->get('/app?menu=plugin.third-party-risk.root&vendor_id=vendor-northbridge-payroll&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Subprocessors')
            ->assertSee('Are subprocessor changes subject to approval or prior notification?')
            ->assertSee('Capture the notice period and approval path for subprocessor changes.');
    }

    public function test_external_collaboration_link_can_be_issued_and_used_for_questionnaire_and_artifact_submission(): void
    {
        Storage::fake('local');

        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
            'contact_name' => 'Nina Walsh',
            'contact_email' => 'nina.walsh@northbridge-payroll.test',
            'expires_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'can_answer_questionnaire' => '1',
            'can_upload_artifacts' => '1',
        ];

        $response = $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/external-links', $payload)
            ->assertFound();

        $this->assertDatabaseHas('vendor_review_external_links', [
            'review_id' => 'vendor-review-northbridge-payroll-2026',
            'contact_email' => 'nina.walsh@northbridge-payroll.test',
            'email_delivery_status' => 'manual-only',
        ]);

        $portalUrl = (string) $response->getSession()->get('third_party_risk_external_portal_url');
        $this->assertNotSame('', $portalUrl);
        $portalPath = (string) parse_url($portalUrl, PHP_URL_PATH);
        $token = basename($portalPath);

        $this->get($portalPath)
            ->assertOk()
            ->assertSee('External Review Portal')
            ->assertSee('Northbridge Payroll Services')
            ->assertSee('2026 payroll onboarding review')
            ->assertSee('Access governance')
            ->assertSee('Incident handling')
            ->assertSee('Do privileged payroll support users receive quarterly access certification?');

        $this->post("/external/vendor-review/{$token}/questionnaire-items/vendor-question-northbridge-access-review", [
            'answer_text' => 'yes',
        ])->assertFound();

        $this->assertSame('yes', DB::table('questionnaire_subject_items')
            ->where('id', 'vendor-question-northbridge-access-review')
            ->value('answer_text'));
        $this->assertSame('submitted', DB::table('questionnaire_subject_items')
            ->where('id', 'vendor-question-northbridge-access-review')
            ->value('response_status'));

        $this->post("/external/vendor-review/{$token}/artifacts", [
            'label' => 'Signed access review package',
            'artifact' => UploadedFile::fake()->createWithContent('access-review-package.pdf', 'signed review package'),
        ])->assertFound();

        $this->assertDatabaseHas('artifacts', [
            'subject_type' => 'vendor-review',
            'subject_id' => 'vendor-review-northbridge-payroll-2026',
            'label' => 'Signed access review package',
            'owner_component' => 'third-party-risk',
        ]);

        $this->get('/app?menu=plugin.third-party-risk.root&vendor_id=vendor-northbridge-payroll&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Timeline and activity')
            ->assertSee('External link issued')
            ->assertSee('External evidence uploaded')
            ->assertSee('External questionnaire submitted');
    }

    public function test_questionnaire_items_support_internal_review_and_acceptance_metadata(): void
    {
        $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/questionnaire-items/vendor-question-northbridge-incident-window/review', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
            'response_status' => 'accepted',
            'review_notes' => 'Accepted after checking the contractual incident clauses.',
        ])->assertFound();

        $this->assertSame('accepted', DB::table('questionnaire_subject_items')
            ->where('id', 'vendor-question-northbridge-incident-window')
            ->value('response_status'));
        $this->assertSame('Accepted after checking the contractual incident clauses.', DB::table('questionnaire_subject_items')
            ->where('id', 'vendor-question-northbridge-incident-window')
            ->value('review_notes'));
        $this->assertSame('principal-org-a', DB::table('questionnaire_subject_items')
            ->where('id', 'vendor-question-northbridge-incident-window')
            ->value('reviewed_by_principal_id'));
        $this->assertNotNull(DB::table('questionnaire_subject_items')
            ->where('id', 'vendor-question-northbridge-incident-window')
            ->value('reviewed_at'));

        $this->get('/app?menu=plugin.third-party-risk.root&vendor_id=vendor-northbridge-payroll&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Review response')
            ->assertSee('Review notes: Accepted after checking the contractual incident clauses.')
            ->assertSee('principal-org-a')
            ->assertSee('Accepted');
    }

    public function test_questionnaire_item_attachments_can_be_uploaded_and_promoted_to_evidence(): void
    {
        Storage::fake('local');

        $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/questionnaire-items/vendor-question-northbridge-access-review/artifacts', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
            'label' => 'Signed access review package',
            'artifact' => UploadedFile::fake()->createWithContent('signed-access-review.txt', 'signed review body'),
        ])->assertFound();

        $artifactId = (string) DB::table('artifacts')
            ->where('subject_type', 'questionnaire-subject-item')
            ->where('subject_id', 'vendor-question-northbridge-access-review')
            ->value('id');

        $this->assertNotSame('', $artifactId);

        $this->get('/app?menu=plugin.third-party-risk.root&vendor_id=vendor-northbridge-payroll&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Questionnaire attachment uploaded')
            ->assertSee('Signed access review package')
            ->assertSee('signed-access-review.txt')
            ->assertSee('Promote to evidence');

        $this->post('/plugins/evidence/promote/'.$artifactId, [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $evidenceId = (string) DB::table('evidence_records')->where('artifact_id', $artifactId)->value('id');

        $this->assertNotSame('', $evidenceId);
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
    }

    public function test_vendor_reviews_support_brokered_collection_requests(): void
    {
        $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/brokered-requests', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
            'contact_name' => 'Alicia Brown',
            'contact_email' => 'alicia.brown@northbridge-payroll.test',
            'collection_channel' => 'email',
            'instructions' => 'Collect the latest access certification statement over email.',
        ])->assertFound();

        $requestId = (string) DB::table('questionnaire_brokered_requests')
            ->where('subject_id', 'vendor-review-northbridge-payroll-2026')
            ->where('contact_email', 'alicia.brown@northbridge-payroll.test')
            ->value('id');

        $this->assertNotSame('', $requestId);
        $this->assertSame('queued', DB::table('questionnaire_brokered_requests')->where('id', $requestId)->value('collection_status'));

        $this->post("/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/brokered-requests/{$requestId}", [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
            'collection_status' => 'submitted',
            'broker_notes' => 'Collected by email and ready for reviewer validation.',
        ])->assertFound();

        $this->assertSame('submitted', DB::table('questionnaire_brokered_requests')->where('id', $requestId)->value('collection_status'));
        $this->assertSame('Collected by email and ready for reviewer validation.', DB::table('questionnaire_brokered_requests')->where('id', $requestId)->value('broker_notes'));

        $this->get('/app?menu=plugin.third-party-risk.root&vendor_id=vendor-northbridge-payroll&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Brokered collection')
            ->assertSee('Alicia Brown')
            ->assertSee('Brokered request submitted')
            ->assertSee('Collected by email and ready for reviewer validation.');
    }

    public function test_external_collaboration_link_can_send_email_invitation_when_outbound_delivery_is_configured(): void
    {
        $this->app->make(NotificationMailSettingsRepository::class)->upsert('org-a', [
            'email_enabled' => true,
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 2525,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'mailer-user',
            'smtp_password' => 'super-secret-password',
            'from_address' => 'mailer@pymesec.test',
            'from_name' => 'PymeSec Mailer',
        ], 'principal-admin');

        $mailer = Mockery::mock(OutboundNotificationMailer::class);
        $mailer->shouldReceive('sendDirectMessage')
            ->once()
            ->withArgs(function (array $settings, string $recipientEmail, string $subject, string $body): bool {
                return $settings['smtp_host'] === 'smtp.example.test'
                    && $recipientEmail === 'nina.walsh@northbridge-payroll.test'
                    && str_contains($subject, 'Northbridge Payroll Services')
                    && str_contains($body, '2026 payroll onboarding review');
            });
        $this->app->instance(OutboundNotificationMailer::class, $mailer);

        $response = $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/external-links', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
            'contact_name' => 'Nina Walsh',
            'contact_email' => 'nina.walsh@northbridge-payroll.test',
            'expires_at' => now()->addDay()->format('Y-m-d\\TH:i'),
            'can_answer_questionnaire' => '1',
            'can_upload_artifacts' => '1',
            'send_email_invitation' => '1',
        ])->assertFound();

        $response->assertSessionHas('status', 'External collaboration link issued and email invitation sent.');

        $record = DB::table('vendor_review_external_links')
            ->where('review_id', 'vendor-review-northbridge-payroll-2026')
            ->where('contact_email', 'nina.walsh@northbridge-payroll.test')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('sent', $record->email_delivery_status);
        $this->assertNotNull($record->email_last_attempted_at);
        $this->assertNotNull($record->email_sent_at);
        $this->assertNull($record->email_delivery_error);
    }

    public function test_external_collaboration_link_can_be_revoked(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
            'contact_name' => 'Nina Walsh',
            'contact_email' => 'nina.walsh@northbridge-payroll.test',
            'expires_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'can_answer_questionnaire' => '1',
            'can_upload_artifacts' => '1',
        ];

        $response = $this->post('/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/external-links', $payload)
            ->assertFound();

        $portalUrl = (string) $response->getSession()->get('third_party_risk_external_portal_url');
        $portalPath = (string) parse_url($portalUrl, PHP_URL_PATH);

        $linkId = (string) DB::table('vendor_review_external_links')
            ->where('review_id', 'vendor-review-northbridge-payroll-2026')
            ->where('contact_email', 'nina.walsh@northbridge-payroll.test')
            ->value('id');

        $this->assertNotSame('', $linkId);

        $this->post("/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/external-links/{$linkId}/revoke", [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $this->get($portalPath)->assertNotFound();
    }

    public function test_vendor_register_supports_reassessment_filters(): void
    {
        DB::table('vendor_reviews')
            ->where('id', 'vendor-review-northbridge-payroll-2026')
            ->update([
                'next_review_due_on' => now()->subDays(1)->toDateString(),
            ]);

        DB::table('vendor_reviews')
            ->where('id', 'vendor-review-restore-labs-2026')
            ->update([
                'next_review_due_on' => now()->addDays(5)->toDateString(),
            ]);

        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/vendors/vendor-restore-labs/reviews/vendor-review-restore-labs-2026/transitions/start-review', $payload)
            ->assertFound();
        $this->post('/plugins/vendors/vendor-restore-labs/reviews/vendor-review-restore-labs-2026/transitions/approve', $payload)
            ->assertFound();

        $this->get('/app?menu=plugin.third-party-risk.root&register_filter=decision-pending&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Northbridge Payroll Services')
            ->assertDontSee('Restore Labs Ltd');

        $this->get('/app?menu=plugin.third-party-risk.root&register_filter=due-soon&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Restore Labs Ltd')
            ->assertDontSee('Northbridge Payroll Services');

        $this->get('/app?menu=plugin.third-party-risk.root&register_filter=overdue&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Northbridge Payroll Services')
            ->assertDontSee('Restore Labs Ltd');
    }

    public function test_vendor_reviews_support_multiple_owner_assignments_and_owner_removal(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.third-party-risk.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/vendors/vendor-northbridge-payroll', [
            ...$payload,
            'legal_name' => 'Northbridge Payroll Services',
            'service_summary' => 'Payroll processing and HR support operations for EU employees.',
            'tier' => 'high',
            'website' => 'https://northbridge-payroll.test',
            'primary_contact_name' => 'Nina Walsh',
            'primary_contact_email' => 'nina.walsh@northbridge-payroll.test',
            'scope_id' => 'scope-eu',
            'review_id' => 'vendor-review-northbridge-payroll-2026',
            'review_title' => '2026 payroll onboarding review',
            'inherent_risk' => 'high',
            'review_summary' => 'Initial due diligence for payroll processing, privileged access, and evidence retention coverage.',
            'decision_notes' => 'Waiting for signed access review package and payroll exception handling evidence.',
            'linked_asset_id' => 'asset-erp-prod',
            'linked_control_id' => 'control-access-review',
            'linked_risk_id' => 'risk-access-drift',
            'linked_finding_id' => 'finding-access-review-gap',
            'next_review_due_on' => now()->addDays(90)->toDateString(),
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->assertSame(['actor-ava-mason', 'actor-compliance-office'], DB::table('functional_assignments')
            ->where('domain_object_type', 'vendor-review')
            ->where('domain_object_id', 'vendor-review-northbridge-payroll-2026')
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->orderBy('functional_actor_id')
            ->pluck('functional_actor_id')
            ->all());

        $assignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'vendor-review')
            ->where('domain_object_id', 'vendor-review-northbridge-payroll-2026')
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-compliance-office')
            ->value('id');

        $this->post("/plugins/vendors/vendor-northbridge-payroll/reviews/vendor-review-northbridge-payroll-2026/owners/{$assignmentId}/remove", $payload)
            ->assertFound();

        $this->assertFalse((bool) DB::table('functional_assignments')
            ->where('id', $assignmentId)
            ->value('is_active'));
    }
}
