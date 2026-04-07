<?php

namespace PymeSec\Core\Audit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;

class DatabaseAuditTrail implements AuditTrailInterface
{
    public function record(AuditRecordData $record): AuditRecord
    {
        $id = (string) Str::ulid();
        $createdAt = now()->toDateTimeString();

        DB::table('audit_logs')->insert([
            'id' => $id,
            'event_type' => $record->eventType,
            'outcome' => $record->outcome,
            'origin_component' => $record->originComponent,
            'channel' => $record->channel,
            'author_type' => $record->authorType,
            'author_id' => $record->authorId,
            'principal_id' => $record->principalId,
            'membership_id' => $record->membershipId,
            'organization_id' => $record->organizationId,
            'scope_id' => $record->scopeId,
            'target_type' => $record->targetType,
            'target_id' => $record->targetId,
            'summary' => $this->encodeJson($record->summary),
            'correlation' => $this->encodeJson($record->correlation),
            'execution_origin' => $record->executionOrigin,
            'request_method' => $record->requestMethod,
            'request_path' => $record->requestPath,
            'status_code' => $record->statusCode,
            'created_at' => $createdAt,
        ]);

        return new AuditRecord(
            id: $id,
            eventType: $record->eventType,
            outcome: $record->outcome,
            originComponent: $record->originComponent,
            channel: $record->channel,
            authorType: $record->authorType,
            authorId: $record->authorId,
            principalId: $record->principalId,
            membershipId: $record->membershipId,
            organizationId: $record->organizationId,
            scopeId: $record->scopeId,
            targetType: $record->targetType,
            targetId: $record->targetId,
            summary: $record->summary,
            correlation: $record->correlation,
            executionOrigin: $record->executionOrigin,
            requestMethod: $record->requestMethod,
            requestPath: $record->requestPath,
            statusCode: $record->statusCode,
            createdAt: $createdAt,
        );
    }

    public function latest(int $limit = 50, array $filters = []): array
    {
        $query = DB::table('audit_logs')->orderByDesc('created_at')->orderByDesc('id');

        foreach ([
            'event_type',
            'outcome',
            'origin_component',
            'principal_id',
            'membership_id',
            'organization_id',
            'scope_id',
            'target_type',
            'target_id',
            'execution_origin',
            'channel',
            'author_type',
            'author_id',
            'request_method',
            'status_code',
        ] as $field) {
            $value = $filters[$field] ?? null;

            if ((is_string($value) && $value !== '') || is_int($value)) {
                $query->where($field, $value);
            }
        }

        $createdFrom = $this->normalizeTimestamp($filters['created_from'] ?? null);

        if ($createdFrom !== null) {
            $query->where('created_at', '>=', $createdFrom);
        }

        $createdTo = $this->normalizeTimestamp($filters['created_to'] ?? null);

        if ($createdTo !== null) {
            $query->where('created_at', '<=', $createdTo);
        }

        return $query
            ->limit(max(1, min($limit, 1000)))
            ->get()
            ->map(fn ($record): AuditRecord => new AuditRecord(
                id: (string) $record->id,
                eventType: (string) $record->event_type,
                outcome: (string) $record->outcome,
                originComponent: (string) $record->origin_component,
                channel: is_string($record->channel ?? null) ? $record->channel : null,
                authorType: is_string($record->author_type ?? null) ? $record->author_type : null,
                authorId: is_string($record->author_id ?? null) ? $record->author_id : null,
                principalId: is_string($record->principal_id) ? $record->principal_id : null,
                membershipId: is_string($record->membership_id) ? $record->membership_id : null,
                organizationId: is_string($record->organization_id) ? $record->organization_id : null,
                scopeId: is_string($record->scope_id) ? $record->scope_id : null,
                targetType: is_string($record->target_type) ? $record->target_type : null,
                targetId: is_string($record->target_id) ? $record->target_id : null,
                summary: $this->decodeJson($record->summary),
                correlation: $this->decodeJson($record->correlation),
                executionOrigin: is_string($record->execution_origin) ? $record->execution_origin : null,
                requestMethod: is_string($record->request_method ?? null) ? $record->request_method : null,
                requestPath: is_string($record->request_path ?? null) ? $record->request_path : null,
                statusCode: is_numeric($record->status_code ?? null) ? (int) $record->status_code : null,
                createdAt: (string) $record->created_at,
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
    private function decodeJson(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
