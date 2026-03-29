<?php

namespace PymeSec\Core\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\Notifications\Contracts\NotificationServiceInterface;
use Throwable;

class DatabaseNotificationService implements NotificationServiceInterface
{
    public function __construct(
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
        private readonly NotificationMailSettingsRepository $mailSettings,
        private readonly OutboundNotificationMailer $mailer,
        private readonly NotificationTemplateRepository $templates,
        private readonly NotificationTemplateRenderer $templateRenderer,
    ) {}

    public function notify(
        string $type,
        string $title,
        string $body,
        ?string $principalId = null,
        ?string $functionalActorId = null,
        ?string $organizationId = null,
        ?string $scopeId = null,
        ?string $sourceEventName = null,
        array $metadata = [],
        ?string $deliverAt = null,
    ): NotificationMessage {
        $id = (string) Str::ulid();
        $normalizedDeliverAt = $this->normalizeTimestamp($deliverAt);
        $status = $normalizedDeliverAt === null ? 'dispatched' : 'pending';
        $dispatchedAt = $status === 'dispatched' ? now()->toDateTimeString() : null;
        [$resolvedTitle, $resolvedBody, $resolvedMetadata] = $this->resolveNotificationPresentation(
            type: $type,
            title: $title,
            body: $body,
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            deliverAt: $normalizedDeliverAt,
            metadata: $metadata,
        );

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => $type,
            'title' => $resolvedTitle,
            'body' => $resolvedBody,
            'status' => $status,
            'principal_id' => $principalId,
            'functional_actor_id' => $functionalActorId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'source_event_name' => $sourceEventName,
            'deliver_at' => $normalizedDeliverAt,
            'dispatched_at' => $dispatchedAt,
            'metadata' => $this->encodeJson($resolvedMetadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notification = new NotificationMessage(
            id: $id,
            type: $type,
            title: $resolvedTitle,
            body: $resolvedBody,
            status: $status,
            principalId: $principalId,
            functionalActorId: $functionalActorId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            sourceEventName: $sourceEventName,
            deliverAt: $normalizedDeliverAt,
            dispatchedAt: $dispatchedAt,
            metadata: $resolvedMetadata,
        );

        $this->events->publish(new PublicEvent(
            name: 'core.notifications.created',
            originComponent: 'core',
            organizationId: $organizationId,
            scopeId: $scopeId,
            payload: [
                'notification_id' => $id,
                'type' => $type,
                'status' => $status,
                'principal_id' => $principalId,
            ],
        ));

        return $notification;
    }

    public function latest(int $limit = 50, array $filters = []): array
    {
        $query = DB::table('notifications')->orderByDesc('created_at')->orderByDesc('id');

        foreach ([
            'status',
            'type',
            'principal_id',
            'functional_actor_id',
            'organization_id',
            'scope_id',
            'source_event_name',
        ] as $field) {
            $value = $filters[$field] ?? null;

            if (is_string($value) && $value !== '') {
                $query->where($field, $value);
            }
        }

        return $query
            ->limit(max(1, min($limit, 500)))
            ->get()
            ->map(fn ($record): NotificationMessage => $this->mapNotification($record))
            ->all();
    }

    public function dispatchDue(): int
    {
        $due = DB::table('notifications')
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('deliver_at')->orWhere('deliver_at', '<=', now()->toDateTimeString());
            })
            ->orderBy('deliver_at')
            ->orderBy('id')
            ->get();

        foreach ($due as $record) {
            $metadata = $this->decodeJson($record->metadata ?? null);
            $emailDelivery = $this->deliverEmailIfPossible($record, $metadata);
            $metadata['channels']['email'] = $emailDelivery;

            DB::table('notifications')
                ->where('id', $record->id)
                ->update([
                    'status' => 'dispatched',
                    'dispatched_at' => now(),
                    'metadata' => $this->encodeJson($metadata),
                    'updated_at' => now(),
                ]);

            $this->audit->record(new AuditRecordData(
                eventType: 'core.notifications.dispatched',
                outcome: 'success',
                originComponent: 'core',
                principalId: is_string($record->principal_id) ? $record->principal_id : null,
                organizationId: is_string($record->organization_id) ? $record->organization_id : null,
                scopeId: is_string($record->scope_id) ? $record->scope_id : null,
                targetType: 'notification',
                targetId: (string) $record->id,
                summary: [
                    'type' => (string) $record->type,
                    'email_delivery_status' => $emailDelivery['status'] ?? 'not-attempted',
                ],
                executionOrigin: 'scheduler',
            ));

            $this->events->publish(new PublicEvent(
                name: 'core.notifications.dispatched',
                originComponent: 'core',
                organizationId: is_string($record->organization_id) ? $record->organization_id : null,
                scopeId: is_string($record->scope_id) ? $record->scope_id : null,
                payload: [
                    'notification_id' => (string) $record->id,
                    'type' => (string) $record->type,
                    'principal_id' => is_string($record->principal_id) ? $record->principal_id : null,
                    'email_delivery_status' => $emailDelivery['status'] ?? 'not-attempted',
                ],
            ));
        }

        return $due->count();
    }

    private function mapNotification(object $record): NotificationMessage
    {
        return new NotificationMessage(
            id: (string) $record->id,
            type: (string) $record->type,
            title: (string) $record->title,
            body: (string) $record->body,
            status: (string) $record->status,
            principalId: is_string($record->principal_id) ? $record->principal_id : null,
            functionalActorId: is_string($record->functional_actor_id) ? $record->functional_actor_id : null,
            organizationId: is_string($record->organization_id) ? $record->organization_id : null,
            scopeId: is_string($record->scope_id) ? $record->scope_id : null,
            sourceEventName: is_string($record->source_event_name) ? $record->source_event_name : null,
            deliverAt: is_string($record->deliver_at) ? $record->deliver_at : null,
            dispatchedAt: is_string($record->dispatched_at) ? $record->dispatched_at : null,
            metadata: $this->decodeJson($record->metadata ?? null),
        );
    }

    private function normalizeTimestamp(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->toDateTimeString();
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function deliverEmailIfPossible(object $record, array $metadata): array
    {
        $organizationId = is_string($record->organization_id ?? null) ? $record->organization_id : null;
        $principalId = is_string($record->principal_id ?? null) ? $record->principal_id : null;

        if (! is_string($organizationId) || $organizationId === '') {
            return $this->emailDeliveryMetadata('skipped', null, 'missing-organization');
        }

        if (! is_string($principalId) || $principalId === '') {
            return $this->emailDeliveryMetadata('skipped', null, 'missing-principal');
        }

        $settings = $this->mailSettings->deliveryConfigForOrganization($organizationId);

        if ($settings === null) {
            return $this->emailDeliveryMetadata('skipped', $principalId, 'email-disabled');
        }

        $recipientEmail = $this->resolvePrincipalEmail($principalId, $organizationId);

        if (! is_string($recipientEmail) || $recipientEmail === '') {
            return $this->emailDeliveryMetadata('skipped', $principalId, 'missing-recipient-email');
        }

        try {
            $this->mailer->sendNotification(new NotificationMessage(
                id: (string) $record->id,
                type: (string) $record->type,
                title: (string) $record->title,
                body: (string) $record->body,
                status: 'dispatched',
                principalId: $principalId,
                functionalActorId: is_string($record->functional_actor_id ?? null) ? $record->functional_actor_id : null,
                organizationId: $organizationId,
                scopeId: is_string($record->scope_id ?? null) ? $record->scope_id : null,
                sourceEventName: is_string($record->source_event_name ?? null) ? $record->source_event_name : null,
                deliverAt: is_string($record->deliver_at ?? null) ? $record->deliver_at : null,
                dispatchedAt: now()->toDateTimeString(),
                metadata: $metadata,
            ), $settings, $recipientEmail);

            return $this->emailDeliveryMetadata('sent', $principalId);
        } catch (Throwable $exception) {
            return $this->emailDeliveryMetadata('failed', $principalId, Str::limit($exception->getMessage(), 120, ''));
        }
    }

    private function resolvePrincipalEmail(string $principalId, string $organizationId): ?string
    {
        $email = DB::table('identity_local_users')
            ->where('principal_id', $principalId)
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->value('email');

        return is_string($email) && trim($email) !== '' ? trim($email) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function emailDeliveryMetadata(string $status, ?string $principalId = null, ?string $reason = null): array
    {
        return array_filter([
            'status' => $status,
            'recipient_principal_id' => $principalId,
            'reason' => $reason,
            'attempted_at' => now()->toDateTimeString(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function resolveNotificationPresentation(
        string $type,
        string $title,
        string $body,
        ?string $principalId,
        ?string $organizationId,
        ?string $scopeId,
        ?string $deliverAt,
        array $metadata,
    ): array {
        if (! is_string($organizationId) || $organizationId === '') {
            return [$title, $body, $metadata];
        }

        $template = $this->templates->activeTemplateForOrganizationAndType($organizationId, $type);

        if ($template === null) {
            return [$title, $body, $metadata];
        }

        $rendered = $this->templateRenderer->render(
            template: $template,
            notificationType: $type,
            title: $title,
            body: $body,
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            deliverAt: $deliverAt,
            metadata: $metadata,
        );

        $metadata['template'] = [
            'template_id' => $template['id'] ?? null,
            'notification_type' => $type,
        ];

        return [
            $rendered['title'],
            $rendered['body'],
            $metadata,
        ];
    }
}
