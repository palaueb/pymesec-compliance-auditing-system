<?php

namespace PymeSec\Plugins\AutomationCatalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutomationCatalogRepository
{
    /**
     * @return array<int, array<string, string>>
     */
    public function all(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('automation_packs')
            ->where('organization_id', $organizationId)
            ->orderByRaw('case when is_enabled then 0 when is_installed then 1 else 2 end')
            ->orderBy('name');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where(function ($nested) use ($scopeId): void {
                $nested->where('scope_id', $scopeId)->orWhereNull('scope_id');
            });
        }

        return $query->get()
            ->map(fn ($pack): array => $this->mapPack($pack))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function find(string $packId): ?array
    {
        $pack = DB::table('automation_packs')->where('id', $packId)->first();

        return $pack !== null ? $this->mapPack($pack) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function createPack(array $data): array
    {
        $scopeId = ($data['scope_id'] ?? null) ?: null;
        $packKey = trim((string) ($data['pack_key'] ?? ''));

        $existing = DB::table('automation_packs')
            ->where('organization_id', (string) $data['organization_id'])
            ->when($scopeId !== null, static fn ($query) => $query->where('scope_id', $scopeId))
            ->when($scopeId === null, static fn ($query) => $query->whereNull('scope_id'))
            ->where('pack_key', $packKey)
            ->first();

        if ($existing !== null) {
            DB::table('automation_packs')
                ->where('id', $existing->id)
                ->update([
                    'name' => trim((string) ($data['name'] ?? $existing->name)),
                    'summary' => ($data['summary'] ?? null) ?: null,
                    'version' => ($data['version'] ?? null) ?: null,
                    'provider_type' => $this->normalizeProviderType((string) ($data['provider_type'] ?? $existing->provider_type)),
                    'source_ref' => ($data['source_ref'] ?? null) ?: null,
                    'provenance_type' => $this->normalizeProvenanceType((string) ($data['provenance_type'] ?? $existing->provenance_type)),
                    'owner_principal_id' => ($data['owner_principal_id'] ?? null) ?: null,
                    'updated_at' => now(),
                ]);

            /** @var array<string, string> $pack */
            $pack = $this->find((string) $existing->id);

            return $pack;
        }

        $id = 'automation-pack-'.Str::lower(Str::ulid());

        DB::table('automation_packs')->insert([
            'id' => $id,
            'organization_id' => (string) $data['organization_id'],
            'scope_id' => $scopeId,
            'pack_key' => $packKey,
            'name' => trim((string) ($data['name'] ?? '')),
            'summary' => ($data['summary'] ?? null) ?: null,
            'version' => ($data['version'] ?? null) ?: null,
            'provider_type' => $this->normalizeProviderType((string) ($data['provider_type'] ?? 'community')),
            'source_ref' => ($data['source_ref'] ?? null) ?: null,
            'provenance_type' => $this->normalizeProvenanceType((string) ($data['provenance_type'] ?? 'plugin')),
            'owner_principal_id' => ($data['owner_principal_id'] ?? null) ?: null,
            'lifecycle_state' => 'discovered',
            'is_installed' => false,
            'is_enabled' => false,
            'installed_at' => null,
            'enabled_at' => null,
            'disabled_at' => null,
            'health_state' => 'unknown',
            'last_run_at' => null,
            'last_success_at' => null,
            'last_failure_at' => null,
            'last_failure_reason' => null,
            'last_sync_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $pack */
        $pack = $this->find($id);

        return $pack;
    }

    /**
     * @return array<string, string>|null
     */
    public function installPack(string $packId, ?string $principalId = null): ?array
    {
        $current = $this->find($packId);

        if ($current === null) {
            return null;
        }

        DB::table('automation_packs')
            ->where('id', $packId)
            ->update([
                'owner_principal_id' => $principalId !== null && $principalId !== '' ? $principalId : (($current['owner_principal_id'] ?? '') !== '' ? $current['owner_principal_id'] : null),
                'lifecycle_state' => ($current['is_enabled'] ?? '0') === '1' ? 'enabled' : 'installed',
                'is_installed' => true,
                'installed_at' => ($current['installed_at'] ?? '') !== '' ? $current['installed_at'] : now(),
                'last_sync_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->find($packId);
    }

    /**
     * @return array<string, string>|null
     */
    public function enablePack(string $packId, ?string $principalId = null): ?array
    {
        $current = $this->find($packId);

        if ($current === null) {
            return null;
        }

        DB::table('automation_packs')
            ->where('id', $packId)
            ->update([
                'owner_principal_id' => $principalId !== null && $principalId !== '' ? $principalId : (($current['owner_principal_id'] ?? '') !== '' ? $current['owner_principal_id'] : null),
                'lifecycle_state' => 'enabled',
                'is_installed' => true,
                'is_enabled' => true,
                'installed_at' => ($current['installed_at'] ?? '') !== '' ? $current['installed_at'] : now(),
                'enabled_at' => now(),
                'disabled_at' => null,
                'last_sync_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->find($packId);
    }

    /**
     * @return array<string, string>|null
     */
    public function disablePack(string $packId, ?string $principalId = null): ?array
    {
        $current = $this->find($packId);

        if ($current === null) {
            return null;
        }

        DB::table('automation_packs')
            ->where('id', $packId)
            ->update([
                'owner_principal_id' => $principalId !== null && $principalId !== '' ? $principalId : (($current['owner_principal_id'] ?? '') !== '' ? $current['owner_principal_id'] : null),
                'lifecycle_state' => ($current['is_installed'] ?? '0') === '1' ? 'disabled' : 'discovered',
                'is_enabled' => false,
                'disabled_at' => now(),
                'last_sync_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->find($packId);
    }

    public function uninstallPack(string $packId): bool
    {
        $current = $this->find($packId);

        if ($current === null) {
            return false;
        }

        DB::transaction(function () use ($packId): void {
            DB::table('automation_pack_output_mappings')
                ->where('automation_pack_id', $packId)
                ->delete();

            DB::table('automation_packs')
                ->where('id', $packId)
                ->delete();
        });

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function updateHealth(string $packId, array $data): ?array
    {
        $current = $this->find($packId);

        if ($current === null) {
            return null;
        }

        $healthState = $this->normalizeHealthState((string) ($data['health_state'] ?? $current['health_state']));
        $lastFailureReason = ($data['last_failure_reason'] ?? null) ?: null;
        $lastRunAt = ($data['last_run_at'] ?? null) ?: now();

        DB::table('automation_packs')
            ->where('id', $packId)
            ->update([
                'health_state' => $healthState,
                'last_run_at' => $lastRunAt,
                'last_success_at' => $healthState === 'healthy' ? now() : (($current['last_success_at'] ?? '') !== '' ? $current['last_success_at'] : null),
                'last_failure_at' => in_array($healthState, ['degraded', 'failing'], true) ? now() : (($current['last_failure_at'] ?? '') !== '' ? $current['last_failure_at'] : null),
                'last_failure_reason' => in_array($healthState, ['degraded', 'failing'], true) ? $lastFailureReason : null,
                'last_sync_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->find($packId);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function outputMappings(string $packId): array
    {
        return DB::table('automation_pack_output_mappings')
            ->where('automation_pack_id', $packId)
            ->orderByDesc('updated_at')
            ->orderBy('mapping_label')
            ->get()
            ->map(fn ($mapping): array => $this->mapOutputMapping($mapping))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findOutputMapping(string $mappingId): ?array
    {
        $mapping = DB::table('automation_pack_output_mappings')->where('id', $mappingId)->first();

        return $mapping !== null ? $this->mapOutputMapping($mapping) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function createOutputMapping(string $packId, array $data, ?string $principalId = null): ?array
    {
        $pack = $this->find($packId);

        if ($pack === null) {
            return null;
        }

        $mappingKind = $this->normalizeMappingKind((string) ($data['mapping_kind'] ?? ''));
        $targetSubjectType = $this->normalizeTargetSubjectType((string) ($data['target_subject_type'] ?? ''));
        $targetSubjectId = trim((string) ($data['target_subject_id'] ?? ''));
        $workflowKey = trim((string) ($data['workflow_key'] ?? ''));
        $transitionKey = trim((string) ($data['transition_key'] ?? ''));

        $id = 'automation-output-map-'.Str::lower(Str::ulid());

        DB::table('automation_pack_output_mappings')->insert([
            'id' => $id,
            'automation_pack_id' => $packId,
            'organization_id' => $pack['organization_id'],
            'scope_id' => $pack['scope_id'] !== '' ? $pack['scope_id'] : null,
            'mapping_label' => trim((string) ($data['mapping_label'] ?? '')),
            'mapping_kind' => $mappingKind,
            'target_subject_type' => $targetSubjectType !== '' ? $targetSubjectType : null,
            'target_subject_id' => $targetSubjectId !== '' ? $targetSubjectId : null,
            'workflow_key' => $workflowKey !== '' ? $workflowKey : null,
            'transition_key' => $transitionKey !== '' ? $transitionKey : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'last_applied_at' => null,
            'last_status' => 'never',
            'last_message' => null,
            'created_by_principal_id' => $principalId !== null && $principalId !== '' ? $principalId : null,
            'updated_by_principal_id' => $principalId !== null && $principalId !== '' ? $principalId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findOutputMapping($id);
    }

    /**
     * @return array<string, string>|null
     */
    public function markOutputMappingDelivery(string $mappingId, string $status, ?string $message = null): ?array
    {
        $current = $this->findOutputMapping($mappingId);

        if ($current === null) {
            return null;
        }

        $normalizedStatus = in_array($status, ['success', 'failed'], true) ? $status : 'failed';

        DB::table('automation_pack_output_mappings')
            ->where('id', $mappingId)
            ->update([
                'last_applied_at' => now(),
                'last_status' => $normalizedStatus,
                'last_message' => $message !== null && $message !== '' ? $message : null,
                'updated_at' => now(),
            ]);

        return $this->findOutputMapping($mappingId);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function repositories(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('automation_pack_repositories')
            ->where('organization_id', $organizationId)
            ->orderByDesc('updated_at')
            ->orderBy('label');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where(function ($nested) use ($scopeId): void {
                $nested->where('scope_id', $scopeId)->orWhereNull('scope_id');
            });
        }

        return $query->get()
            ->map(fn ($repository): array => $this->mapRepository($repository))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findRepository(string $repositoryId): ?array
    {
        $repository = DB::table('automation_pack_repositories')->where('id', $repositoryId)->first();

        return $repository !== null ? $this->mapRepository($repository) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function saveRepository(array $data): array
    {
        $organizationId = (string) $data['organization_id'];
        $scopeId = ($data['scope_id'] ?? null) ?: null;
        $repositoryUrl = trim((string) ($data['repository_url'] ?? ''));
        $existing = DB::table('automation_pack_repositories')
            ->where('organization_id', $organizationId)
            ->when($scopeId !== null, static fn ($query) => $query->where('scope_id', $scopeId))
            ->when($scopeId === null, static fn ($query) => $query->whereNull('scope_id'))
            ->where('repository_url', $repositoryUrl)
            ->first();

        $repositorySignUrl = trim((string) ($data['repository_sign_url'] ?? ''));
        $resolvedSignUrl = $repositorySignUrl !== '' ? $repositorySignUrl : sprintf('%s.sign', rtrim($repositoryUrl, '/'));
        $trustTier = $this->normalizeTrustTier((string) ($data['trust_tier'] ?? 'trusted-partner'));
        $isEnabled = (bool) ($data['is_enabled'] ?? true);

        if ($existing !== null) {
            DB::table('automation_pack_repositories')
                ->where('id', (string) $existing->id)
                ->update([
                    'label' => trim((string) ($data['label'] ?? $existing->label)),
                    'repository_sign_url' => $resolvedSignUrl,
                    'public_key_pem' => trim((string) ($data['public_key_pem'] ?? $existing->public_key_pem)),
                    'trust_tier' => $trustTier,
                    'is_enabled' => $isEnabled,
                    'updated_by_principal_id' => ($data['updated_by_principal_id'] ?? null) ?: null,
                    'updated_at' => now(),
                ]);

            /** @var array<string, string> $repository */
            $repository = $this->findRepository((string) $existing->id);

            return $repository;
        }

        $id = 'automation-pack-repository-'.Str::lower(Str::ulid());

        DB::table('automation_pack_repositories')->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'label' => trim((string) ($data['label'] ?? '')),
            'repository_url' => $repositoryUrl,
            'repository_sign_url' => $resolvedSignUrl,
            'public_key_pem' => trim((string) ($data['public_key_pem'] ?? '')),
            'trust_tier' => $trustTier,
            'is_enabled' => $isEnabled,
            'last_refreshed_at' => null,
            'last_status' => 'never',
            'last_error' => null,
            'created_by_principal_id' => ($data['created_by_principal_id'] ?? null) ?: null,
            'updated_by_principal_id' => ($data['updated_by_principal_id'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $repository */
        $repository = $this->findRepository($id);

        return $repository;
    }

    public function markRepositorySyncResult(string $repositoryId, string $status, ?string $error = null): void
    {
        DB::table('automation_pack_repositories')
            ->where('id', $repositoryId)
            ->update([
                'last_refreshed_at' => now(),
                'last_status' => in_array($status, ['success', 'failed'], true) ? $status : 'failed',
                'last_error' => $error !== null && $error !== '' ? $error : null,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $packs
     * @return array{release_rows: int, latest_rows: int}
     */
    public function replaceRepositoryReleases(
        string $repositoryId,
        string $organizationId,
        ?string $scopeId,
        array $packs,
    ): array {
        DB::table('automation_pack_releases')
            ->where('repository_id', $repositoryId)
            ->delete();

        $repositoryUrl = (string) (DB::table('automation_pack_repositories')
            ->where('id', $repositoryId)
            ->value('repository_url') ?? '');

        $releaseRows = 0;
        $latestRows = [];

        foreach ($packs as $pack) {
            $packKey = trim((string) ($pack['pack_key'] ?? ''));
            $packName = trim((string) ($pack['pack_name'] ?? ''));
            $packDescription = trim((string) ($pack['pack_description'] ?? ''));
            $latestVersion = trim((string) ($pack['latest_version'] ?? ''));
            $versions = is_array($pack['versions'] ?? null) ? $pack['versions'] : [];

            if ($packKey === '' || $packName === '' || $versions === []) {
                continue;
            }

            foreach ($versions as $versionRow) {
                if (! is_array($versionRow)) {
                    continue;
                }

                $version = trim((string) ($versionRow['version'] ?? ''));
                $artifactUrl = trim((string) ($versionRow['artifact_url'] ?? ''));

                if ($version === '' || $artifactUrl === '') {
                    continue;
                }

                $isLatest = $version === $latestVersion || ($versionRow['is_latest'] ?? false) === true;

                DB::table('automation_pack_releases')->insert([
                    'id' => 'automation-pack-release-'.Str::lower(Str::ulid()),
                    'repository_id' => $repositoryId,
                    'organization_id' => $organizationId,
                    'scope_id' => $scopeId,
                    'pack_key' => $packKey,
                    'pack_name' => $packName,
                    'pack_description' => $packDescription !== '' ? $packDescription : null,
                    'version' => $version,
                    'is_latest' => $isLatest,
                    'artifact_url' => $artifactUrl,
                    'artifact_signature_url' => ($versionRow['artifact_signature_url'] ?? null) ?: null,
                    'artifact_sha256' => ($versionRow['artifact_sha256'] ?? null) ?: null,
                    'pack_manifest_url' => ($versionRow['pack_manifest_url'] ?? null) ?: null,
                    'capabilities_json' => $this->encodeJson(is_array($versionRow['capabilities'] ?? null) ? $versionRow['capabilities'] : []),
                    'permissions_requested_json' => $this->encodeJson(is_array($versionRow['permissions_requested'] ?? null) ? $versionRow['permissions_requested'] : []),
                    'raw_metadata_json' => $this->encodeJson($versionRow),
                    'discovered_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $releaseRows++;

                if ($isLatest) {
                    $latestRows[] = [
                        'repository_url' => $repositoryUrl,
                        'pack_key' => $packKey,
                        'pack_name' => $packName,
                        'pack_description' => $packDescription,
                        'version' => $version,
                        'artifact_url' => $artifactUrl,
                        'pack_manifest_url' => (string) ($versionRow['pack_manifest_url'] ?? ''),
                    ];
                }
            }
        }

        $this->upsertDiscoveredPacksFromLatest($organizationId, $scopeId, $latestRows);

        return [
            'release_rows' => $releaseRows,
            'latest_rows' => count($latestRows),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function externalCatalogRows(string $organizationId, ?string $scopeId = null): array
    {
        $releasesQuery = DB::table('automation_pack_releases as releases')
            ->join('automation_pack_repositories as repositories', 'repositories.id', '=', 'releases.repository_id')
            ->where('releases.organization_id', $organizationId)
            ->where('releases.is_latest', true)
            ->where('repositories.is_enabled', true)
            ->orderBy('releases.pack_name')
            ->orderBy('releases.pack_key');

        $countsQuery = DB::table('automation_pack_releases')
            ->where('organization_id', $organizationId);

        if ($scopeId !== null && $scopeId !== '') {
            $releasesQuery->where(function ($nested) use ($scopeId): void {
                $nested->where('releases.scope_id', $scopeId)->orWhereNull('releases.scope_id');
            });
            $countsQuery->where(function ($nested) use ($scopeId): void {
                $nested->where('scope_id', $scopeId)->orWhereNull('scope_id');
            });
        }

        $counts = $countsQuery
            ->get(['repository_id', 'pack_key'])
            ->reduce(function (array $carry, object $row): array {
                $key = (string) $row->repository_id.'::'.(string) $row->pack_key;
                $carry[$key] = ($carry[$key] ?? 0) + 1;

                return $carry;
            }, []);

        return $releasesQuery->get([
            'releases.repository_id',
            'releases.pack_key',
            'releases.pack_name',
            'releases.pack_description',
            'releases.version',
            'releases.artifact_url',
            'releases.artifact_signature_url',
            'releases.artifact_sha256',
            'releases.pack_manifest_url',
            'repositories.label as repository_label',
            'repositories.repository_url',
            'repositories.last_status as repository_last_status',
        ])->map(function (object $row) use ($counts): array {
            $countKey = (string) $row->repository_id.'::'.(string) $row->pack_key;

            return $this->mapExternalCatalogRow($row, (int) ($counts[$countKey] ?? 1));
        })->all();
    }

    private function normalizeProviderType(string $providerType): string
    {
        return in_array($providerType, ['native', 'community', 'vendor', 'internal'], true)
            ? $providerType
            : 'community';
    }

    private function normalizeProvenanceType(string $provenanceType): string
    {
        return in_array($provenanceType, ['plugin', 'marketplace', 'git', 'manual'], true)
            ? $provenanceType
            : 'plugin';
    }

    private function normalizeHealthState(string $healthState): string
    {
        return in_array($healthState, ['unknown', 'healthy', 'degraded', 'failing'], true)
            ? $healthState
            : 'unknown';
    }

    private function normalizeTrustTier(string $trustTier): string
    {
        return in_array($trustTier, ['trusted-first-party', 'trusted-partner', 'community-reviewed', 'untrusted'], true)
            ? $trustTier
            : 'trusted-partner';
    }

    private function normalizeMappingKind(string $mappingKind): string
    {
        return in_array($mappingKind, ['evidence-refresh', 'workflow-transition'], true)
            ? $mappingKind
            : 'evidence-refresh';
    }

    private function normalizeTargetSubjectType(string $subjectType): string
    {
        return in_array($subjectType, [
            'asset',
            'control',
            'risk',
            'finding',
            'policy',
            'policy-exception',
            'privacy-data-flow',
            'privacy-processing-activity',
            'continuity-service',
            'continuity-plan',
            'recovery-plan',
            'assessment',
            'assessment-review',
            'vendor-review',
        ], true) ? $subjectType : '';
    }

    /**
     * @return array<string, string>
     */
    private function mapPack(object $pack): array
    {
        return [
            'id' => (string) $pack->id,
            'organization_id' => (string) $pack->organization_id,
            'scope_id' => is_string($pack->scope_id) ? $pack->scope_id : '',
            'pack_key' => (string) $pack->pack_key,
            'name' => (string) $pack->name,
            'summary' => is_string($pack->summary) ? $pack->summary : '',
            'version' => is_string($pack->version) ? $pack->version : '',
            'provider_type' => is_string($pack->provider_type) ? $pack->provider_type : 'community',
            'source_ref' => is_string($pack->source_ref) ? $pack->source_ref : '',
            'provenance_type' => is_string($pack->provenance_type) ? $pack->provenance_type : 'plugin',
            'owner_principal_id' => is_string($pack->owner_principal_id) ? $pack->owner_principal_id : '',
            'lifecycle_state' => is_string($pack->lifecycle_state) ? $pack->lifecycle_state : 'discovered',
            'is_installed' => (bool) $pack->is_installed ? '1' : '0',
            'is_enabled' => (bool) $pack->is_enabled ? '1' : '0',
            'installed_at' => $pack->installed_at !== null ? (string) $pack->installed_at : '',
            'enabled_at' => $pack->enabled_at !== null ? (string) $pack->enabled_at : '',
            'disabled_at' => $pack->disabled_at !== null ? (string) $pack->disabled_at : '',
            'health_state' => is_string($pack->health_state) ? $pack->health_state : 'unknown',
            'last_run_at' => $pack->last_run_at !== null ? (string) $pack->last_run_at : '',
            'last_success_at' => $pack->last_success_at !== null ? (string) $pack->last_success_at : '',
            'last_failure_at' => $pack->last_failure_at !== null ? (string) $pack->last_failure_at : '',
            'last_failure_reason' => is_string($pack->last_failure_reason) ? $pack->last_failure_reason : '',
            'last_sync_at' => $pack->last_sync_at !== null ? (string) $pack->last_sync_at : '',
            'created_at' => (string) $pack->created_at,
            'updated_at' => (string) $pack->updated_at,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapOutputMapping(object $mapping): array
    {
        return [
            'id' => (string) $mapping->id,
            'automation_pack_id' => (string) $mapping->automation_pack_id,
            'organization_id' => (string) $mapping->organization_id,
            'scope_id' => is_string($mapping->scope_id) ? $mapping->scope_id : '',
            'mapping_label' => (string) $mapping->mapping_label,
            'mapping_kind' => is_string($mapping->mapping_kind) ? $mapping->mapping_kind : 'evidence-refresh',
            'target_subject_type' => is_string($mapping->target_subject_type) ? $mapping->target_subject_type : '',
            'target_subject_id' => is_string($mapping->target_subject_id) ? $mapping->target_subject_id : '',
            'workflow_key' => is_string($mapping->workflow_key) ? $mapping->workflow_key : '',
            'transition_key' => is_string($mapping->transition_key) ? $mapping->transition_key : '',
            'is_active' => (bool) $mapping->is_active ? '1' : '0',
            'last_applied_at' => $mapping->last_applied_at !== null ? (string) $mapping->last_applied_at : '',
            'last_status' => is_string($mapping->last_status) ? $mapping->last_status : 'never',
            'last_message' => is_string($mapping->last_message) ? $mapping->last_message : '',
            'created_by_principal_id' => is_string($mapping->created_by_principal_id) ? $mapping->created_by_principal_id : '',
            'updated_by_principal_id' => is_string($mapping->updated_by_principal_id) ? $mapping->updated_by_principal_id : '',
            'created_at' => (string) $mapping->created_at,
            'updated_at' => (string) $mapping->updated_at,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapRepository(object $repository): array
    {
        return [
            'id' => (string) $repository->id,
            'organization_id' => (string) $repository->organization_id,
            'scope_id' => is_string($repository->scope_id) ? $repository->scope_id : '',
            'label' => (string) $repository->label,
            'repository_url' => (string) $repository->repository_url,
            'repository_sign_url' => is_string($repository->repository_sign_url) ? $repository->repository_sign_url : '',
            'public_key_pem' => (string) $repository->public_key_pem,
            'trust_tier' => is_string($repository->trust_tier) ? $repository->trust_tier : 'trusted-partner',
            'is_enabled' => (bool) $repository->is_enabled ? '1' : '0',
            'last_refreshed_at' => $repository->last_refreshed_at !== null ? (string) $repository->last_refreshed_at : '',
            'last_status' => is_string($repository->last_status) ? $repository->last_status : 'never',
            'last_error' => is_string($repository->last_error) ? $repository->last_error : '',
            'created_by_principal_id' => is_string($repository->created_by_principal_id) ? $repository->created_by_principal_id : '',
            'updated_by_principal_id' => is_string($repository->updated_by_principal_id) ? $repository->updated_by_principal_id : '',
            'created_at' => (string) $repository->created_at,
            'updated_at' => (string) $repository->updated_at,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapExternalCatalogRow(object $row, int $versionsCount): array
    {
        return [
            'repository_id' => (string) $row->repository_id,
            'repository_label' => (string) $row->repository_label,
            'repository_url' => (string) $row->repository_url,
            'repository_last_status' => is_string($row->repository_last_status) ? $row->repository_last_status : 'never',
            'pack_key' => (string) $row->pack_key,
            'pack_name' => (string) $row->pack_name,
            'pack_description' => is_string($row->pack_description) ? $row->pack_description : '',
            'latest_version' => (string) $row->version,
            'versions_available' => (string) max(1, $versionsCount),
            'artifact_url' => (string) $row->artifact_url,
            'artifact_signature_url' => is_string($row->artifact_signature_url) ? $row->artifact_signature_url : '',
            'artifact_sha256' => is_string($row->artifact_sha256) ? $row->artifact_sha256 : '',
            'pack_manifest_url' => is_string($row->pack_manifest_url) ? $row->pack_manifest_url : '',
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $latestRows
     */
    private function upsertDiscoveredPacksFromLatest(string $organizationId, ?string $scopeId, array $latestRows): void
    {
        foreach ($latestRows as $latest) {
            $packKey = (string) ($latest['pack_key'] ?? '');
            $sourceRef = $this->resolvePackSourceRef($latest);

            if ($packKey === '') {
                continue;
            }

            $existing = DB::table('automation_packs')
                ->where('organization_id', $organizationId)
                ->when($scopeId !== null && $scopeId !== '', static fn ($query) => $query->where('scope_id', $scopeId))
                ->when($scopeId === null || $scopeId === '', static fn ($query) => $query->whereNull('scope_id'))
                ->where('pack_key', $packKey)
                ->first();

            if ($existing !== null) {
                DB::table('automation_packs')
                    ->where('id', (string) $existing->id)
                    ->update([
                        'name' => (string) ($latest['pack_name'] ?? $existing->name),
                        'summary' => ($latest['pack_description'] ?? null) ?: null,
                        'version' => ($latest['version'] ?? null) ?: null,
                        'provider_type' => 'community',
                        'provenance_type' => 'marketplace',
                        'source_ref' => $sourceRef,
                        'last_sync_at' => now(),
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('automation_packs')->insert([
                'id' => 'automation-pack-'.Str::lower(Str::ulid()),
                'organization_id' => $organizationId,
                'scope_id' => $scopeId !== '' ? $scopeId : null,
                'pack_key' => $packKey,
                'name' => (string) ($latest['pack_name'] ?? $packKey),
                'summary' => ($latest['pack_description'] ?? null) ?: null,
                'version' => ($latest['version'] ?? null) ?: null,
                'provider_type' => 'community',
                'source_ref' => $sourceRef,
                'provenance_type' => 'marketplace',
                'owner_principal_id' => null,
                'lifecycle_state' => 'discovered',
                'is_installed' => false,
                'is_enabled' => false,
                'installed_at' => null,
                'enabled_at' => null,
                'disabled_at' => null,
                'health_state' => 'unknown',
                'last_run_at' => null,
                'last_success_at' => null,
                'last_failure_at' => null,
                'last_failure_reason' => null,
                'last_sync_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $latest
     */
    private function resolvePackSourceRef(array $latest): ?string
    {
        $packKey = trim((string) ($latest['pack_key'] ?? ''));
        $repositoryUrl = trim((string) ($latest['repository_url'] ?? ''));
        if ($packKey !== '' && $repositoryUrl !== '') {
            $cleanRepositoryUrl = preg_replace('/[?#].*$/', '', $repositoryUrl);
            $cleanRepositoryUrl = is_string($cleanRepositoryUrl) ? $cleanRepositoryUrl : $repositoryUrl;
            $lastSlash = strrpos($cleanRepositoryUrl, '/');

            if ($lastSlash !== false) {
                $repositoryRoot = substr($cleanRepositoryUrl, 0, $lastSlash);
                if ($repositoryRoot !== '') {
                    return rtrim($repositoryRoot, '/').'/?pack='.rawurlencode($packKey);
                }
            }
        }

        $manifestUrl = trim((string) ($latest['pack_manifest_url'] ?? ''));
        if ($manifestUrl !== '') {
            if (str_ends_with($manifestUrl, '/pack.json')) {
                return substr($manifestUrl, 0, -strlen('/pack.json')).'/';
            }

            return rtrim($manifestUrl, '/').'/';
        }

        $artifactUrl = trim((string) ($latest['artifact_url'] ?? ''));
        if ($artifactUrl === '') {
            return null;
        }

        $cleanArtifact = preg_replace('/[?#].*$/', '', $artifactUrl);
        $cleanArtifact = is_string($cleanArtifact) ? $cleanArtifact : $artifactUrl;
        $lastSlash = strrpos($cleanArtifact, '/');

        if ($lastSlash === false) {
            return null;
        }

        return substr($cleanArtifact, 0, $lastSlash + 1);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encodeJson(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '{}';
    }
}
