<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ControlsCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_controls_plugin_route_requires_view_permission(): void
    {
        $this->get('/plugins/controls?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'control-access-review',
                'name' => 'Quarterly Access Review',
                'framework_id' => 'framework-iso-27001',
            ]);

        $this->get('/plugins/controls?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_controls_catalog_screen_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.controls-catalog.catalog&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Controls Catalog')
            ->assertSee('Control list')
            ->assertSee('This list stays focused on catalog browsing, framework context, owner summary, and Open.')
            ->assertSee('Quarterly Access Review')
            ->assertDontSee('Backup Governance')
            ->assertSee('Framework adoption')
            ->assertSee('Ava Mason')
            ->assertSee('Create control')
            ->assertSee('Open');

        $this->get('/app?menu=plugin.controls-catalog.catalog&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello&control_id=control-access-review')
            ->assertOk()
            ->assertSee('Controls Catalog')
            ->assertSee('Control Detail keeps requirement mappings, ownership, evidence, workflow transitions, and control editing in one workspace.')
            ->assertSee('Control Detail')
            ->assertSee('Quarterly Access Review')
            ->assertSee('Link requirement')
            ->assertSee('Attach evidence')
            ->assertSee('Edit control details')
            ->assertDontSee('Control list')
            ->assertDontSee('Create control');
    }

    public function test_framework_adoption_screen_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.controls-catalog.framework-adoption&principal_id=principal-org-a&organization_id=org-a&scope_id=scope-eu&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Framework Adoption')
            ->assertSee('ISO 27001')
            ->assertSee('GDPR')
            ->assertSee('Signed mandate document')
            ->assertSee('Onboarding kit')
            ->assertSee('Framework pack updates')
            ->assertSee('Ready now')
            ->assertSee('Needs attention')
            ->assertSee('Readiness snapshot')
            ->assertSee('Reporting presets');
    }

    public function test_framework_adoption_screen_surfaces_framework_readiness_and_latest_report_presets(): void
    {
        $this->get('/app?menu=plugin.controls-catalog.framework-adoption&principal_id=principal-org-a&organization_id=org-a&scope_id=scope-eu&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Q2 Access and Resilience Review')
            ->assertSee('Needs attention')
            ->assertSee('1 pass')
            ->assertSee('1 fail')
            ->assertSee('Open report')
            ->assertSee('Export CSV')
            ->assertSee('Export JSON')
            ->assertSee('Management views')
            ->assertSee('Export bundles')
            ->assertSee('Signed mandate document still missing.')
            ->assertSee('Onboarding');
    }

    public function test_framework_adoption_can_be_managed_with_a_signed_mandate_document(): void
    {
        Storage::fake('local');

        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.framework-adoption',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/controls/frameworks/framework-gdpr/adoption', [
            ...$payload,
            'scope_id' => 'scope-eu',
            'status' => 'active',
            'adopted_at' => '2026-03-01',
            'change_reason' => 'Privacy program approved by leadership.',
            'mandate_document' => UploadedFile::fake()->createWithContent('gdpr-mandate.pdf', 'signed-by-management'),
        ])->assertFound();

        $this->get('/app?menu=plugin.controls-catalog.framework-adoption&principal_id=principal-org-a&organization_id=org-a&scope_id=scope-eu&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('GDPR')
            ->assertSee('active')
            ->assertSee('2026-03-01')
            ->assertSee('principal-org-a')
            ->assertSee('Privacy program approved by leadership.')
            ->assertSee('gdpr-mandate.pdf');
    }

    public function test_framework_adoption_requires_a_signed_mandate_document_before_activation(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.framework-adoption',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->from('/app?menu=plugin.controls-catalog.framework-adoption&principal_id=principal-org-a&organization_id=org-a&scope_id=scope-eu&membership_ids[]=membership-org-a-hello')
            ->post('/plugins/controls/frameworks/framework-gdpr/adoption', [
                ...$payload,
                'scope_id' => 'scope-eu',
                'status' => 'active',
                'adopted_at' => '2026-03-01',
                'change_reason' => 'Leadership requested activation.',
            ])
            ->assertSessionHasErrors('mandate_document');
    }

    public function test_framework_onboarding_kit_creates_starter_controls_policies_and_marks_the_adoption(): void
    {
        $this->post('/plugins/controls/frameworks/framework-gdpr/onboarding/apply', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $this->assertDatabaseHas('controls', [
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'framework_id' => 'framework-gdpr',
            'name' => 'Records of processing activities',
        ]);

        $this->assertDatabaseHas('policies', [
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'title' => 'Personal data protection policy',
        ]);

        $this->assertDatabaseHas('org_framework_adoptions', [
            'organization_id' => 'org-a',
            'framework_id' => 'framework-gdpr',
            'scope_id' => 'scope-eu',
            'starter_pack_version' => '2026.04',
            'starter_pack_applied_by_principal_id' => 'principal-org-a',
        ]);

        $this->get('/app?menu=plugin.controls-catalog.framework-adoption&principal_id=principal-org-a&organization_id=org-a&scope_id=scope-eu&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Records of processing activities')
            ->assertSee('Personal data protection policy')
            ->assertSee('Starter pack applied on');
    }

    public function test_framework_onboarding_kit_can_be_applied_over_the_api(): void
    {
        $response = $this->postJson('/api/v1/controls/frameworks/framework-gdpr/onboarding/apply', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'scope_id' => 'scope-eu',
            'membership_id' => 'membership-org-a-hello',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.adoption.framework_id', 'framework-gdpr')
            ->assertJsonPath('data.adoption.starter_pack_version', '2026.04')
            ->assertJsonPath('data.result.onboarding_version', '2026.04');
    }

    public function test_control_review_transition_creates_due_notification_and_scheduler_dispatches_it(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.catalog',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/controls/control-access-review/transitions/submit-review', $payload)
            ->assertFound();

        $this->get('/core/notifications?principal_id=principal-admin&recipient_principal_id=principal-org-a&organization_id=org-a&status=pending')
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'plugin.controls-catalog.review-requested',
                'status' => 'pending',
                'source_event_name' => 'plugin.controls-catalog.workflows.transitioned',
            ]);

        $exitCode = Artisan::call('notifications:dispatch-due');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Dispatched', Artisan::output());

        $this->get('/core/notifications?principal_id=principal-admin&recipient_principal_id=principal-org-a&organization_id=org-a&status=dispatched')
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'plugin.controls-catalog.review-requested',
                'status' => 'dispatched',
            ]);

        $this->get('/core/events?principal_id=principal-admin&name=core.notifications.dispatched')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'core.notifications.dispatched',
                'origin_component' => 'core',
                'organization_id' => 'org-a',
            ]);
    }

    public function test_controls_review_screen_shows_history_and_notifications(): void
    {
        $this->post('/plugins/controls/control-access-review/transitions/submit-review', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.catalog',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $this->artisan('notifications:dispatch-due')->assertExitCode(0);

        $this->get('/app?menu=plugin.controls-catalog.reviews&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Control Reviews')
            ->assertSee('submit-review')
            ->assertSee('dispatched')
            ->assertSee('Control review requested');
    }

    public function test_controls_catalog_screen_lists_uploaded_artifacts_inside_the_shell(): void
    {
        Storage::fake('local');

        $this->post('/plugins/controls/control-access-review/artifacts', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.catalog',
            'membership_id' => 'membership-org-a-hello',
            'label' => 'Quarterly export',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('quarterly-export.csv', 'row-1'),
        ])->assertFound();

        $this->get('/app?menu=plugin.controls-catalog.catalog&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello&control_id=control-access-review')
            ->assertOk()
            ->assertSee('Quarterly export')
            ->assertSee('quarterly-export.csv');
    }

    public function test_controls_can_be_created_and_edited_from_the_shell_runtime(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.catalog',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/controls', [
            ...$payload,
            'name' => 'Supplier Access Revalidation',
            'framework_id' => 'framework-iso-27001',
            'domain' => 'Third Parties',
            'evidence' => 'Annual supplier entitlement review',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->get('/app?menu=plugin.controls-catalog.catalog&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier Access Revalidation')
            ->assertSee('Annual supplier entitlement review');

        $this->post('/plugins/controls/control-supplier-access-revalidation', [
            ...$payload,
            'name' => 'Supplier Access Recertification',
            'framework_id' => 'framework-iso-27001',
            'domain' => 'Third Parties',
            'evidence' => 'Semi-annual supplier entitlement review',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->get('/app?menu=plugin.controls-catalog.catalog&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier Access Recertification')
            ->assertSee('Semi-annual supplier entitlement review')
            ->assertSee('Compliance Office');
    }

    public function test_controls_support_multiple_owner_assignments_and_owner_removal(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.catalog',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/controls/control-access-review', [
            ...$payload,
            'name' => 'Quarterly Access Review',
            'framework_id' => 'framework-iso-27001',
            'domain' => 'Identity and access',
            'evidence' => 'Signed recertification package.',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->assertSame(2, DB::table('functional_assignments')
            ->where('domain_object_type', 'control')
            ->where('domain_object_id', 'control-access-review')
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->count());

        $this->get('/app?menu=plugin.controls-catalog.catalog&control_id=control-access-review&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Ava Mason')
            ->assertSee('Compliance Office')
            ->assertSee('Remove owner');

        $assignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'control')
            ->where('domain_object_id', 'control-access-review')
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-compliance-office')
            ->value('id');

        $this->post("/plugins/controls/control-access-review/owners/{$assignmentId}/remove", $payload)->assertFound();

        $this->assertFalse((bool) DB::table('functional_assignments')
            ->where('id', $assignmentId)
            ->value('is_active'));
    }

    public function test_frameworks_requirements_and_mappings_can_be_managed_from_the_shell_runtime(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.catalog',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/controls/frameworks', [
            ...$payload,
            'code' => 'CIS-1',
            'name' => 'CIS Safeguards v8',
            'description' => 'Operational control baseline for endpoint and admin hygiene.',
        ])->assertFound();

        $this->post('/plugins/controls/requirements', [
            ...$payload,
            'framework_id' => 'framework-cis-1',
            'code' => 'CIS-1.1',
            'title' => 'Maintain an enterprise asset inventory',
            'description' => 'Keep an accurate record of managed assets and owners.',
        ])->assertFound();

        $this->post('/plugins/controls/control-access-review/requirements', [
            ...$payload,
            'requirement_id' => 'requirement-cis-1-1',
            'coverage' => 'partial',
            'notes' => 'Review ownership and entitlements as part of the quarterly cycle.',
        ])->assertFound();

        $this->get('/app?menu=plugin.controls-catalog.catalog&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello&control_id=control-access-review')
            ->assertOk()
            ->assertSee('CIS Safeguards v8')
            ->assertSee('Maintain an enterprise asset inventory')
            ->assertSee('Partial');
    }
}
