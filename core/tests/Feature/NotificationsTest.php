<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use PymeSec\Core\Notifications\Contracts\NotificationServiceInterface;
use PymeSec\Core\Notifications\NotificationMailSettingsRepository;
use PymeSec\Core\Notifications\NotificationTemplateRepository;
use PymeSec\Core\Notifications\OutboundNotificationMailer;
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

    public function test_the_notifications_admin_screen_renders_for_platform_admins(): void
    {
        $this->get('/admin?menu=core.notifications&principal_id=principal-admin&organization_id=org-a')
            ->assertOk()
            ->assertSee('core.notifications');
    }

    public function test_notification_mail_settings_can_be_saved_from_the_admin_screen(): void
    {
        $this->post('/core/notifications/settings', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'core.notifications',
            'email_enabled' => '1',
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => '2525',
            'smtp_encryption' => 'tls',
            'smtp_username' => 'mailer-user',
            'smtp_password' => 'super-secret-password',
            'from_address' => 'mailer@pymesec.test',
            'from_name' => 'PymeSec Mailer',
            'reply_to_address' => 'support@pymesec.test',
        ])->assertFound()->assertSessionHas('status');

        $record = DB::table('notification_mail_settings')
            ->where('organization_id', 'org-a')
            ->first();

        $this->assertNotNull($record);
        $this->assertTrue((bool) $record->email_enabled);
        $this->assertSame('smtp.example.test', $record->smtp_host);
        $this->assertSame(2525, (int) $record->smtp_port);
        $this->assertSame('tls', $record->smtp_encryption);
        $this->assertSame('mailer-user', $record->smtp_username);
        $this->assertSame('mailer@pymesec.test', $record->from_address);
        $this->assertNotSame('super-secret-password', $record->smtp_password_encrypted);
        $this->assertSame('principal-admin', $record->updated_by_principal_id);
    }

    public function test_notification_templates_can_be_saved_from_the_admin_screen(): void
    {
        $this->post('/core/notifications/templates', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'core.notifications',
            'notification_type' => 'plugin.evidence-management.review-due',
            'is_active' => '1',
            'title_template' => '[Reminder] {{notification_title}}',
            'body_template' => "Due on {{due_on}}\n\n{{notification_body}}",
        ])->assertFound()->assertSessionHas('status');

        $record = DB::table('notification_templates')
            ->where('organization_id', 'org-a')
            ->where('notification_type', 'plugin.evidence-management.review-due')
            ->first();

        $this->assertNotNull($record);
        $this->assertTrue((bool) $record->is_active);
        $this->assertSame('[Reminder] {{notification_title}}', $record->title_template);
        $this->assertSame("Due on {{due_on}}\n\n{{notification_body}}", $record->body_template);
        $this->assertSame('principal-admin', $record->updated_by_principal_id);
    }

    public function test_notification_templates_are_applied_when_notifications_are_created(): void
    {
        $this->app->make(NotificationTemplateRepository::class)->upsert(
            organizationId: 'org-a',
            notificationType: 'plugin.evidence-management.review-due',
            data: [
                'is_active' => true,
                'title_template' => '[Reminder] {{notification_title}}',
                'body_template' => "Evidence {{evidence_id}} is due on {{due_on}}\n\n{{notification_body}}",
            ],
            updatedByPrincipalId: 'principal-admin',
        );

        $notification = $this->app->make(NotificationServiceInterface::class)->notify(
            type: 'plugin.evidence-management.review-due',
            title: 'Evidence review due soon: Access review pack',
            body: 'Review "Access review pack" before 2026-04-20 to keep the evidence current.',
            principalId: 'principal-org-a',
            functionalActorId: null,
            organizationId: 'org-a',
            scopeId: 'scope-eu',
            sourceEventName: 'plugin.evidence-management.reminder-queued',
            metadata: [
                'evidence_id' => 'evidence-access-review-pack',
                'reminder_type' => 'review-due',
                'due_on' => '2026-04-20',
            ],
            deliverAt: now()->toDateTimeString(),
        );

        $this->assertSame('[Reminder] Evidence review due soon: Access review pack', $notification->title);
        $this->assertStringContainsString('Evidence evidence-access-review-pack is due on 2026-04-20', $notification->body);
        $this->assertSame('plugin.evidence-management.review-due', $notification->metadata['template']['notification_type'] ?? null);

        $record = DB::table('notifications')->where('id', $notification->id)->first();

        $this->assertNotNull($record);
        $this->assertSame('[Reminder] Evidence review due soon: Access review pack', $record->title);
        $this->assertStringContainsString('Evidence evidence-access-review-pack is due on 2026-04-20', (string) $record->body);
    }

    public function test_dispatch_due_sends_email_when_outbound_delivery_is_configured(): void
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

        DB::table('notifications')->insert([
            'id' => 'notification-pending-email',
            'type' => 'core.test.pending',
            'title' => 'Review due',
            'body' => 'This reminder should also go out by email.',
            'status' => 'pending',
            'principal_id' => 'principal-org-a',
            'functional_actor_id' => null,
            'organization_id' => 'org-a',
            'scope_id' => null,
            'source_event_name' => 'core.tests.created',
            'deliver_at' => now()->subMinute(),
            'dispatched_at' => null,
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $mailer = Mockery::mock(OutboundNotificationMailer::class);
        $mailer->shouldReceive('sendNotification')
            ->once()
            ->withArgs(function ($notification, array $settings, string $recipientEmail): bool {
                return $notification->id === 'notification-pending-email'
                    && $settings['smtp_host'] === 'smtp.example.test'
                    && $recipientEmail === 'ava.mason@northwind.test';
            });
        $this->app->instance(OutboundNotificationMailer::class, $mailer);
        $this->app->forgetInstance(NotificationServiceInterface::class);

        $dispatched = $this->app->make(NotificationServiceInterface::class)->dispatchDue();

        $this->assertSame(1, $dispatched);

        $record = DB::table('notifications')->where('id', 'notification-pending-email')->first();

        $this->assertNotNull($record);
        $this->assertSame('dispatched', $record->status);

        $metadata = json_decode((string) $record->metadata, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('sent', $metadata['channels']['email']['status'] ?? null);
        $this->assertSame('principal-org-a', $metadata['channels']['email']['recipient_principal_id'] ?? null);
    }

    public function test_test_email_route_rejects_recipients_from_other_organizations(): void
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

        $this->post('/core/notifications/test-email', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'core.notifications',
            'recipient_principal_id' => 'principal-org-b-ops',
        ])->assertSessionHasErrors(['recipient_principal_id']);
    }

    public function test_test_email_route_sends_a_message_to_a_valid_recipient_in_the_same_organization(): void
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
        $mailer->shouldReceive('sendTestMessage')
            ->once()
            ->withArgs(function (array $settings, string $recipientEmail, string $organizationId): bool {
                return $settings['smtp_host'] === 'smtp.example.test'
                    && $recipientEmail === 'ava.mason@northwind.test'
                    && $organizationId === 'org-a';
            });
        $this->app->instance(OutboundNotificationMailer::class, $mailer);

        $this->post('/core/notifications/test-email', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'core.notifications',
            'recipient_principal_id' => 'principal-org-a',
        ])->assertFound()->assertSessionHas('status');

        $this->assertDatabaseHas('notification_mail_settings', [
            'organization_id' => 'org-a',
        ]);

        $this->assertNotNull(DB::table('notification_mail_settings')->where('organization_id', 'org-a')->value('last_tested_at'));
    }
}
