<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
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
            ]);

        $this->get('/plugins/controls?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_controls_catalog_screen_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.controls-catalog.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Controls Catalog')
            ->assertSee('Quarterly Access Review')
            ->assertSee('Backup Governance')
            ->assertSee('Ava Mason')
            ->assertSee('Create Control');
    }

    public function test_control_review_transition_creates_due_notification_and_scheduler_dispatches_it(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.root',
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
            'menu' => 'plugin.controls-catalog.root',
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
            'menu' => 'plugin.controls-catalog.root',
            'membership_id' => 'membership-org-a-hello',
            'label' => 'Quarterly export',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('quarterly-export.csv', 'row-1'),
        ])->assertFound();

        $this->get('/app?menu=plugin.controls-catalog.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
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
            'menu' => 'plugin.controls-catalog.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/controls', [
            ...$payload,
            'name' => 'Supplier Access Revalidation',
            'framework' => 'ISO 27001',
            'domain' => 'Third Parties',
            'evidence' => 'Annual supplier entitlement review',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->get('/app?menu=plugin.controls-catalog.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier Access Revalidation')
            ->assertSee('Annual supplier entitlement review');

        $this->post('/plugins/controls/control-supplier-access-revalidation', [
            ...$payload,
            'name' => 'Supplier Access Recertification',
            'framework' => 'ISO 27001',
            'domain' => 'Third Parties',
            'evidence' => 'Semi-annual supplier entitlement review',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->get('/app?menu=plugin.controls-catalog.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier Access Recertification')
            ->assertSee('Semi-annual supplier entitlement review')
            ->assertSee('Compliance Office');
    }
}
