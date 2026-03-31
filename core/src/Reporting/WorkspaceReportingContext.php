<?php

namespace PymeSec\Core\Reporting;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PymeSec\Core\ObjectAccess\ObjectAccessService;

class WorkspaceReportingContext
{
    public function __construct(
        private readonly ObjectAccessService $objectAccess,
    ) {}

    /**
     * @param  array<int, string>|null  $visibleIds
     */
    public function scopedQuery(
        string $table,
        string $organizationId,
        ?string $scopeId,
        bool $includeOrganizationWideWhenScoped,
        ?array $visibleIds = null,
    ): Builder {
        $query = DB::table($table)->where('organization_id', $organizationId);

        if (Schema::hasColumn($table, 'scope_id') && is_string($scopeId) && $scopeId !== '') {
            if ($includeOrganizationWideWhenScoped) {
                $query->where(function (Builder $scopedQuery) use ($scopeId): void {
                    $scopedQuery->whereNull('scope_id')
                        ->orWhere('scope_id', $scopeId);
                });
            } else {
                $query->where('scope_id', $scopeId);
            }
        }

        return $this->applyVisibleIds($query, $visibleIds);
    }

    /**
     * @return array<string, string>
     */
    public function scopeLabels(string $organizationId): array
    {
        if (! Schema::hasTable('scopes')) {
            return [];
        }

        return DB::table('scopes')
            ->where('organization_id', $organizationId)
            ->pluck('name', 'id')
            ->map(static fn ($name): string => (string) $name)
            ->all();
    }

    /**
     * @return array<int, string>|null
     */
    public function visibleObjectIds(
        ?string $principalId,
        string $organizationId,
        ?string $scopeId,
        string $objectType,
    ): ?array {
        return $this->objectAccess->visibleObjectIds($principalId, $organizationId, $scopeId, $objectType);
    }

    /**
     * @param  array<int, string>|null  $visibleIds
     */
    private function applyVisibleIds(Builder $query, ?array $visibleIds): Builder
    {
        if ($visibleIds === null) {
            return $query;
        }

        if ($visibleIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $visibleIds);
    }
}
