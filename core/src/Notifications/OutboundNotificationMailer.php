<?php

namespace PymeSec\Core\Notifications;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class OutboundNotificationMailer
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function sendNotification(NotificationMessage $notification, array $settings, string $recipientEmail): void
    {
        $body = trim($notification->title."\n\n".$notification->body);

        $this->sendDirectMessage(
            settings: $settings,
            recipientEmail: $recipientEmail,
            subject: $notification->title,
            body: $body,
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function sendTestMessage(array $settings, string $recipientEmail, string $organizationId): void
    {
        $body = implode("\n\n", [
            'PymeSec outbound notifications test',
            'This message confirms that SMTP delivery is configured from the web administration area.',
            'Organization: '.$organizationId,
        ]);

        $this->sendDirectMessage(
            settings: $settings,
            recipientEmail: $recipientEmail,
            subject: 'PymeSec outbound notifications test',
            body: $body,
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function sendDirectMessage(array $settings, string $recipientEmail, string $subject, string $body): void
    {
        $originalMailerConfig = Config::get('mail.mailers.smtp');
        $manager = app('mail.manager');

        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => (string) ($settings['smtp_host'] ?? ''),
            'port' => (int) ($settings['smtp_port'] ?? 587),
            'encryption' => ($settings['smtp_encryption'] ?? null) ?: null,
            'username' => ($settings['smtp_username'] ?? null) ?: null,
            'password' => ($settings['smtp_password'] ?? null) ?: null,
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ]);

        if (method_exists($manager, 'purge')) {
            $manager->purge('smtp');
        }

        try {
            Mail::mailer('smtp')->raw($body, function (Message $message) use ($settings, $recipientEmail, $subject): void {
                $message->to($recipientEmail)
                    ->subject($subject)
                    ->from((string) ($settings['from_address'] ?? ''), (string) ($settings['from_name'] ?? 'PymeSec'));

                if (is_string($settings['reply_to_address'] ?? null) && $settings['reply_to_address'] !== '') {
                    $message->replyTo((string) $settings['reply_to_address']);
                }
            });
        } finally {
            Config::set('mail.mailers.smtp', $originalMailerConfig);

            if (method_exists($manager, 'purge')) {
                $manager->purge('smtp');
            }
        }
    }
}
