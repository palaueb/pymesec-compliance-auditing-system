<?php

namespace PymeSec\Core\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ContextualReferenceValidator
{
    public function assertActor(?string $actorId, string $organizationId, ?string $scopeId, string $field = 'owner_actor_id'): void
    {
        if (! is_string($actorId) || $actorId === '') {
            return;
        }

        $query = DB::table('functional_actors')
            ->where('id', $actorId)
            ->where('organization_id', $organizationId)
            ->where('is_active', true);

        if (is_string($scopeId) && $scopeId !== '') {
            $query->where(function ($inner) use ($scopeId): void {
                $inner->whereNull('scope_id')->orWhere('scope_id', $scopeId);
            });
        }

        if (! $query->exists()) {
            throw ValidationException::withMessages([
                $field => 'The selected owner actor is invalid for this organization or scope.',
            ]);
        }
    }

    public function assertRecord(
        ?string $recordId,
        string $table,
        string $organizationId,
        ?string $scopeId,
        string $field,
        string $message,
    ): void {
        if (! is_string($recordId) || $recordId === '') {
            return;
        }

        $query = DB::table($table)->where('id', $recordId);

        if (Schema::hasColumn($table, 'organization_id')) {
            $query->where('organization_id', $organizationId);
        }

        if (is_string($scopeId) && $scopeId !== '' && Schema::hasColumn($table, 'scope_id')) {
            $query->where(function ($inner) use ($scopeId): void {
                $inner->whereNull('scope_id')->orWhere('scope_id', $scopeId);
            });
        }

        if (Schema::hasColumn($table, 'is_active')) {
            $query->where('is_active', true);
        }

        if (! $query->exists()) {
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }
    }

    public function assertArrayRecords(
        array $recordIds,
        string $table,
        string $organizationId,
        ?string $scopeId,
        string $field,
        string $message,
    ): void {
        foreach ($recordIds as $recordId) {
            $this->assertRecord(
                is_string($recordId) ? $recordId : null,
                $table,
                $organizationId,
                $scopeId,
                $field,
                $message,
            );
        }
    }

    public function assertDelimitedRecords(
        ?string $recordIds,
        string $table,
        string $organizationId,
        ?string $scopeId,
        string $field,
        string $message,
    ): void {
        if (! is_string($recordIds) || trim($recordIds) === '') {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', $recordIds),
        ), static fn (string $value): bool => $value !== '')));

        $this->assertArrayRecords($ids, $table, $organizationId, $scopeId, $field, $message);
    }
}
