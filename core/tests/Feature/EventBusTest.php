<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EventBusTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_transitions_publish_public_events_and_plugin_subscribers_can_react(): void
    {
        $this->post('/plugins/assets/asset-erp-prod/transitions/submit-review', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.asset-catalog.root',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $this->get('/core/events?principal_id=principal-admin&name=plugin.asset-catalog.workflows.transitioned')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'plugin.asset-catalog.workflows.transitioned',
                'origin_component' => 'asset-catalog',
                'organization_id' => 'org-a',
            ])
            ->assertJsonFragment([
                'transition_key' => 'submit-review',
                'subject_type' => 'asset',
                'subject_id' => 'asset-erp-prod',
            ]);

        $this->get('/core/events?principal_id=principal-admin&name=plugin.actor-directory.asset-transition.observed')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'plugin.actor-directory.asset-transition.observed',
                'origin_component' => 'actor-directory',
                'organization_id' => 'org-a',
            ])
            ->assertJsonFragment([
                'source_event' => 'plugin.asset-catalog.workflows.transitioned',
                'subject_id' => 'asset-erp-prod',
            ]);
    }

    public function test_the_events_list_command_reports_published_events(): void
    {
        $this->post('/plugins/assets/asset-erp-prod/transitions/submit-review', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.asset-catalog.root',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $exitCode = Artisan::call('events:list', [
            '--limit' => 10,
            '--organization_id' => 'org-a',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Event', Artisan::output());
    }
}
