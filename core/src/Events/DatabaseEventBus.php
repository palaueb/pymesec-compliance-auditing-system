<?php

namespace PymeSec\Core\Events;

use Closure;
use Illuminate\Support\Facades\DB;
use PymeSec\Core\Events\Contracts\EventBusInterface;

class DatabaseEventBus implements EventBusInterface
{
    /**
     * @var array<string, array<int, Closure(PublicEvent): void>>
     */
    private array $listeners = [];

    public function subscribe(string $eventName, Closure $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    public function publish(PublicEvent $event): PublicEvent
    {
        $publishedAt = now()->toDateTimeString();

        DB::table('public_events')->insert([
            'name' => $event->name,
            'origin_component' => $event->originComponent,
            'organization_id' => $event->organizationId,
            'scope_id' => $event->scopeId,
            'payload' => $this->encodeJson($event->payload),
            'published_at' => $publishedAt,
        ]);

        $published = new PublicEvent(
            name: $event->name,
            originComponent: $event->originComponent,
            payload: $event->payload,
            organizationId: $event->organizationId,
            scopeId: $event->scopeId,
            publishedAt: $publishedAt,
        );

        foreach ($this->listeners[$event->name] ?? [] as $listener) {
            try {
                $listener($published);
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        }

        return $published;
    }

    public function latest(int $limit = 50, array $filters = []): array
    {
        $query = DB::table('public_events')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        foreach ([
            'name',
            'origin_component',
            'organization_id',
            'scope_id',
        ] as $field) {
            $value = $filters[$field] ?? null;

            if (is_string($value) && $value !== '') {
                $query->where($field, $value);
            }
        }

        return $query
            ->limit(max(1, min($limit, 500)))
            ->get()
            ->map(static fn ($record): PublicEvent => new PublicEvent(
                name: (string) $record->name,
                originComponent: (string) $record->origin_component,
                payload: self::decodeJson($record->payload ?? null),
                organizationId: is_string($record->organization_id) ? $record->organization_id : null,
                scopeId: is_string($record->scope_id) ? $record->scope_id : null,
                publishedAt: (string) $record->published_at,
            ))
            ->all();
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
    private static function decodeJson(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
