<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_notifications_endpoint_requires_platform_permission(): void
    {
        $this->post('/plugins/controls/control-access-review/transitions/submit-review', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.root',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $this->get('/core/notifications?principal_id=principal-org-a&organization_id=org-a')
            ->assertForbidden();

        $this->get('/core/notifications?principal_id=principal-admin&organization_id=org-a&status=pending')
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'plugin.controls-catalog.review-requested',
                'status' => 'pending',
            ]);
    }

    public function test_the_notifications_list_command_reports_pending_and_dispatched_notifications(): void
    {
        $this->post('/plugins/controls/control-access-review/transitions/submit-review', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.root',
            'membership_id' => 'membership-org-a-hello',
        ])->assertFound();

        $exitCode = Artisan::call('notifications:list', [
            '--limit' => 10,
            '--organization_id' => 'org-a',
            '--status' => 'pending',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('plugin.controls-catalog.review-requested', Artisan::output());

        $this->artisan('notifications:dispatch-due')->assertExitCode(0);

        $exitCode = Artisan::call('notifications:list', [
            '--limit' => 10,
            '--organization_id' => 'org-a',
            '--status' => 'dispatched',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('dispatched', Artisan::output());
    }
}
