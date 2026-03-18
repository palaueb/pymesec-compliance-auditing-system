<?php

namespace PymeSec\Core\ObjectAccess;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Permissions\Contracts\AuthorizationStoreInterface;

class ObjectAccessService
{
    /**
     * @var array<string, bool>
     */
    private array $platformAdminCache = [];

    /**
     * @var array<string, array<int, string>|null>
     */
    private array $visibleIdsCache = [];

    public function __construct(
        private readonly FunctionalActorServiceInterface $actors,
        private readonly AuthorizationStoreInterface $authorizationStore,
    ) {}

    /**
     * @return array<int, string>|null
     */
    public function visibleObjectIds(
        ?string $principalId,
        ?string $organizationId,
        ?string $scopeId,
        string $domainObjectType,
    ): ?array {
        $cacheKey = implode('|', [
            $principalId ?? '',
            $organizationId ?? '',
            $scopeId ?? '',
            $domainObjectType,
        ]);

        if (array_key_exists($cacheKey, $this->visibleIdsCache)) {
            return $this->visibleIdsCache[$cacheKey];
        }

        if (! is_string($principalId) || $principalId === '' || ! is_string($organizationId) || $organizationId === '') {
            return $this->visibleIdsCache[$cacheKey] = null;
        }

        if (! Schema::hasTable('functional_assignments') || ! Schema::hasTable('principal_functional_actor_links')) {
            return $this->visibleIdsCache[$cacheKey] = null;
        }

        if ($this->isPlatformAdmin($principalId)) {
            return $this->visibleIdsCache[$cacheKey] = null;
        }

        $actorIds = array_values(array_filter(array_map(
            static fn ($actor): ?string => is_string($actor->id ?? null) && $actor->id !== '' ? $actor->id : null,
            $this->actors->actorsForPrincipal($principalId, $organizationId),
        )));

        if ($actorIds === []) {
            return $this->visibleIdsCache[$cacheKey] = null;
        }

        $domainAssignments = DB::table('functional_assignments')
            ->where('organization_id', $organizationId)
            ->where('domain_object_type', $domainObjectType)
            ->where('is_active', true)
            ->whereIn('functional_actor_id', $actorIds);

        if (! $domainAssignments->exists()) {
            return $this->visibleIdsCache[$cacheKey] = null;
        }

        $query = DB::table('functional_assignments')
            ->where('organization_id', $organizationId)
            ->where('domain_object_type', $domainObjectType)
            ->where('is_active', true)
            ->whereIn('functional_actor_id', $actorIds)
            ->distinct()
            ->orderBy('domain_object_id');

        if (is_string($scopeId) && $scopeId !== '') {
            $query->where(function ($inner) use ($scopeId): void {
                $inner->whereNull('scope_id')->orWhere('scope_id', $scopeId);
            });
        }

        return $this->visibleIdsCache[$cacheKey] = $query
            ->pluck('domain_object_id')
            ->filter(static fn ($id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();
    }

    public function canAccessObject(
        ?string $principalId,
        ?string $organizationId,
        ?string $scopeId,
        string $domainObjectType,
        string $domainObjectId,
    ): bool {
        $visibleIds = $this->visibleObjectIds($principalId, $organizationId, $scopeId, $domainObjectType);

        if ($visibleIds === null) {
            return true;
        }

        return in_array($domainObjectId, $visibleIds, true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    public function filterRecords(
        array $records,
        string $idKey,
        ?string $principalId,
        ?string $organizationId,
        ?string $scopeId,
        string $domainObjectType,
    ): array {
        $visibleIds = $this->visibleObjectIds($principalId, $organizationId, $scopeId, $domainObjectType);

        if ($visibleIds === null) {
            return $records;
        }

        return array_values(array_filter($records, static function (array $record) use ($idKey, $visibleIds): bool {
            $id = $record[$idKey] ?? null;

            return is_string($id) && in_array($id, $visibleIds, true);
        }));
    }

    private function isPlatformAdmin(string $principalId): bool
    {
        if (array_key_exists($principalId, $this->platformAdminCache)) {
            return $this->platformAdminCache[$principalId];
        }

        foreach ($this->authorizationStore->grantRecords() as $grant) {
            if (($grant['target_type'] ?? null) !== 'principal' || ($grant['target_id'] ?? null) !== $principalId) {
                continue;
            }

            if (($grant['context_type'] ?? null) !== 'platform') {
                continue;
            }

            if (($grant['grant_type'] ?? null) === 'role' && ($grant['value'] ?? null) === 'platform-admin') {
                return $this->platformAdminCache[$principalId] = true;
            }
        }

        return $this->platformAdminCache[$principalId] = false;
    }
}
