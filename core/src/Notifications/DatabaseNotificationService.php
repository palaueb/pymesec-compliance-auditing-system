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

class DatabaseNotificationService implements NotificationServiceInterface
{
    public function __construct(
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
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

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'status' => $status,
            'principal_id' => $principalId,
            'functional_actor_id' => $functionalActorId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'source_event_name' => $sourceEventName,
            'deliver_at' => $normalizedDeliverAt,
            'dispatched_at' => $dispatchedAt,
            'metadata' => $this->encodeJson($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notification = new NotificationMessage(
            id: $id,
            type: $type,
            title: $title,
            body: $body,
            status: $status,
            principalId: $principalId,
            functionalActorId: $functionalActorId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            sourceEventName: $sourceEventName,
            deliverAt: $normalizedDeliverAt,
            dispatchedAt: $dispatchedAt,
            metadata: $metadata,
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
            DB::table('notifications')
                ->where('id', $record->id)
                ->update([
                    'status' => 'dispatched',
                    'dispatched_at' => now(),
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
}
