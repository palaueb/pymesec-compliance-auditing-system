<?php

namespace PymeSec\Plugins\ThirdPartyRisk;

use Illuminate\Support\Str;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Notifications\NotificationMailSettingsRepository;
use PymeSec\Core\Notifications\OutboundNotificationMailer;
use Throwable;

class ExternalReviewInvitationDeliveryService
{
    public function __construct(
        private readonly NotificationMailSettingsRepository $mailSettings,
        private readonly OutboundNotificationMailer $mailer,
        private readonly AuditTrailInterface $audit,
    ) {}

    /**
     * @param  array<string, string>  $vendor
     * @param  array<string, string>  $review
     * @param  array<string, string>  $link
     * @return array{status: string, error: ?string}
     */
    public function send(array $vendor, array $review, array $link, string $portalUrl, ?string $principalId = null): array
    {
        $settings = $this->mailSettings->deliveryConfigForOrganization($review['organization_id']);

        if ($settings === null) {
            $this->audit->record(new AuditRecordData(
                eventType: 'plugin.third-party-risk.external-link.delivery-skipped',
                outcome: 'failure',
                originComponent: 'third-party-risk',
                principalId: $principalId,
                organizationId: $review['organization_id'],
                scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
                targetType: 'vendor_review_external_link',
                targetId: $link['id'],
                summary: [
                    'reason' => 'email-not-configured',
                    'review_id' => $review['id'],
                    'contact_email' => $link['contact_email'],
                ],
                executionOrigin: 'third-party-risk',
            ));

            return [
                'status' => 'not-configured',
                'error' => 'Outbound email delivery is not configured for this organization.',
            ];
        }

        $subject = sprintf('Vendor review request: %s', $vendor['legal_name']);
        $body = implode("\n\n", array_filter([
            sprintf('Hello%s,', $link['contact_name'] !== '' ? ' '.$link['contact_name'] : ''),
            sprintf('You have been invited to contribute to the vendor review "%s" for %s.', $review['title'], $vendor['legal_name']),
            $vendor['service_summary'] !== '' ? 'Service context: '.$vendor['service_summary'] : null,
            $this->permissionSummary($link),
            $link['expires_at'] !== '' ? 'Access expires: '.$link['expires_at'] : 'Access expires: no expiry set',
            'Open the secure review portal using this link:',
            $portalUrl,
            'If you were not expecting this request, contact the sender before uploading any information.',
        ]));

        try {
            $this->mailer->sendDirectMessage(
                settings: $settings,
                recipientEmail: $link['contact_email'],
                subject: $subject,
                body: $body,
            );
        } catch (Throwable $throwable) {
            $message = Str::limit(trim($throwable->getMessage()), 500, '');

            $this->audit->record(new AuditRecordData(
                eventType: 'plugin.third-party-risk.external-link.delivery-failed',
                outcome: 'failure',
                originComponent: 'third-party-risk',
                principalId: $principalId,
                organizationId: $review['organization_id'],
                scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
                targetType: 'vendor_review_external_link',
                targetId: $link['id'],
                summary: [
                    'review_id' => $review['id'],
                    'contact_email' => $link['contact_email'],
                    'error' => $message,
                ],
                executionOrigin: 'third-party-risk',
            ));

            return [
                'status' => 'failed',
                'error' => $message !== '' ? $message : 'Outbound email delivery failed.',
            ];
        }

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.third-party-risk.external-link.delivered',
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: $principalId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'vendor_review_external_link',
            targetId: $link['id'],
            summary: [
                'review_id' => $review['id'],
                'contact_email' => $link['contact_email'],
            ],
            executionOrigin: 'third-party-risk',
        ));

        return [
            'status' => 'sent',
            'error' => null,
        ];
    }

    /**
     * @param  array<string, string>  $link
     */
    private function permissionSummary(array $link): string
    {
        $capabilities = [];

        if ($link['can_answer_questionnaire'] === '1') {
            $capabilities[] = 'answer questionnaire items';
        }

        if ($link['can_upload_artifacts'] === '1') {
            $capabilities[] = 'upload evidence';
        }

        if ($capabilities === []) {
            $capabilities[] = 'review the shared request';
        }

        return 'Shared capabilities: '.implode(' and ', $capabilities).'.';
    }
}
