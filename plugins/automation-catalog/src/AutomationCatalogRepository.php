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

        $payload = [
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
        ];

        if ($this->packSupportsRuntimeScheduleColumns()) {
            $payload['runtime_schedule_enabled'] = false;
            $payload['runtime_schedule_cron'] = null;
            $payload['runtime_schedule_timezone'] = null;
            $payload['runtime_schedule_last_slot'] = null;
        }

        DB::table('automation_packs')->insert($payload);

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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function updatePackRuntimeSchedule(string $packId, array $data): ?array
    {
        if (! $this->packSupportsRuntimeScheduleColumns()) {
            return $this->find($packId);
        }

        $current = $this->find($packId);
        if ($current === null) {
            return null;
        }

        DB::table('automation_packs')
            ->where('id', $packId)
            ->update([
                'runtime_schedule_enabled' => (bool) ($data['runtime_schedule_enabled'] ?? false),
                'runtime_schedule_cron' => ($data['runtime_schedule_cron'] ?? null) ?: null,
                'runtime_schedule_timezone' => ($data['runtime_schedule_timezone'] ?? null) ?: null,
                'runtime_schedule_last_slot' => ($data['runtime_schedule_last_slot'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        return $this->find($packId);
    }

    public function markPackRuntimeScheduleSlot(string $packId, string $slot): void
    {
        if ($slot === '' || ! $this->packSupportsRuntimeScheduleColumns()) {
            return;
        }

        DB::table('automation_packs')
            ->where('id', $packId)
            ->update([
                'runtime_schedule_last_slot' => $slot,
                'updated_at' => now(),
            ]);
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
        $targetBindingMode = $this->normalizeTargetBindingMode((string) ($data['target_binding_mode'] ?? 'explicit'));
        $targetScopeId = trim((string) ($data['target_scope_id'] ?? ''));
        $targetSelector = is_array($data['target_selector'] ?? null) ? $data['target_selector'] : [];
        $posturePropagationPolicy = $this->normalizePosturePropagationPolicy((string) ($data['posture_propagation_policy'] ?? 'disabled'));
        $executionMode = $this->normalizeExecutionMode((string) ($data['execution_mode'] ?? 'both'));
        $onFailPolicy = $this->normalizeOnFailPolicy((string) ($data['on_fail_policy'] ?? 'no-op'));
        $evidencePolicy = $this->normalizeEvidencePolicy((string) ($data['evidence_policy'] ?? 'always'));
        $runtimeRetryMaxAttempts = $this->normalizeRuntimeRetryMaxAttempts((int) ($data['runtime_retry_max_attempts'] ?? 0));
        $runtimeRetryBackoffMs = $this->normalizeRuntimeRetryBackoffMs((int) ($data['runtime_retry_backoff_ms'] ?? 0));
        $runtimeMaxTargets = $this->normalizeRuntimeMaxTargets((int) ($data['runtime_max_targets'] ?? 200));
        $runtimePayloadMaxKb = $this->normalizeRuntimePayloadMaxKb((int) ($data['runtime_payload_max_kb'] ?? 512));

        $id = 'automation-output-map-'.Str::lower(Str::ulid());

        $payload = [
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
            'target_binding_mode' => $targetBindingMode,
            'target_scope_id' => $targetScopeId !== '' ? $targetScopeId : null,
            'target_selector_json' => $this->encodeJson($targetSelector),
            'posture_propagation_policy' => $posturePropagationPolicy,
            'execution_mode' => $executionMode,
            'on_fail_policy' => $onFailPolicy,
            'evidence_policy' => $evidencePolicy,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'last_applied_at' => null,
            'last_status' => 'never',
            'last_message' => null,
            'created_by_principal_id' => $principalId !== null && $principalId !== '' ? $principalId : null,
            'updated_by_principal_id' => $principalId !== null && $principalId !== '' ? $principalId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($this->mappingSupportsRuntimeGuardrailColumns()) {
            $payload['runtime_retry_max_attempts'] = $runtimeRetryMaxAttempts;
            $payload['runtime_retry_backoff_ms'] = $runtimeRetryBackoffMs;
            $payload['runtime_max_targets'] = $runtimeMaxTargets;
            $payload['runtime_payload_max_kb'] = $runtimePayloadMaxKb;
        }

        DB::table('automation_pack_output_mappings')->insert($payload);

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

        $normalizedStatus = in_array($status, ['success', 'failed', 'skipped'], true) ? $status : 'failed';

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
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function createPackRun(string $packId, array $data): ?array
    {
        if ($this->find($packId) === null) {
            return null;
        }

        $id = 'automation-pack-run-'.Str::lower(Str::ulid());

        DB::table('automation_pack_runs')->insert([
            'id' => $id,
            'automation_pack_id' => $packId,
            'organization_id' => (string) ($data['organization_id'] ?? ''),
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'trigger_mode' => trim((string) ($data['trigger_mode'] ?? 'manual')),
            'status' => trim((string) ($data['status'] ?? 'running')),
            'started_at' => ($data['started_at'] ?? null) ?: now(),
            'finished_at' => ($data['finished_at'] ?? null) ?: null,
            'duration_ms' => (int) ($data['duration_ms'] ?? 0) ?: null,
            'total_mappings' => (int) ($data['total_mappings'] ?? 0),
            'success_count' => (int) ($data['success_count'] ?? 0),
            'failed_count' => (int) ($data['failed_count'] ?? 0),
            'skipped_count' => (int) ($data['skipped_count'] ?? 0),
            'summary' => ($data['summary'] ?? null) ?: null,
            'failure_reason' => ($data['failure_reason'] ?? null) ?: null,
            'initiated_by_principal_id' => ($data['initiated_by_principal_id'] ?? null) ?: null,
            'initiated_by_membership_id' => ($data['initiated_by_membership_id'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findPackRun($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function completePackRun(string $runId, array $data): ?array
    {
        $current = $this->findPackRun($runId);
        if ($current === null) {
            return null;
        }

        DB::table('automation_pack_runs')
            ->where('id', $runId)
            ->update([
                'status' => trim((string) ($data['status'] ?? $current['status'])),
                'finished_at' => ($data['finished_at'] ?? null) ?: now(),
                'duration_ms' => (int) ($data['duration_ms'] ?? $current['duration_ms']),
                'total_mappings' => (int) ($data['total_mappings'] ?? $current['total_mappings']),
                'success_count' => (int) ($data['success_count'] ?? $current['success_count']),
                'failed_count' => (int) ($data['failed_count'] ?? $current['failed_count']),
                'skipped_count' => (int) ($data['skipped_count'] ?? $current['skipped_count']),
                'summary' => ($data['summary'] ?? null) ?: null,
                'failure_reason' => ($data['failure_reason'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        return $this->findPackRun($runId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function createCheckResult(array $data): ?array
    {
        $runId = trim((string) ($data['automation_pack_run_id'] ?? ''));
        $packId = trim((string) ($data['automation_pack_id'] ?? ''));
        $organizationId = trim((string) ($data['organization_id'] ?? ''));

        if ($runId === '' || $packId === '' || $organizationId === '') {
            return null;
        }

        $id = 'automation-check-result-'.Str::lower(Str::ulid());

        $payload = [
            'id' => $id,
            'automation_pack_run_id' => $runId,
            'automation_pack_id' => $packId,
            'automation_output_mapping_id' => ($data['automation_output_mapping_id'] ?? null) ?: null,
            'organization_id' => $organizationId,
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'trigger_mode' => trim((string) ($data['trigger_mode'] ?? 'manual')),
            'mapping_kind' => trim((string) ($data['mapping_kind'] ?? 'evidence-refresh')),
            'target_subject_type' => ($data['target_subject_type'] ?? null) ?: null,
            'target_subject_id' => ($data['target_subject_id'] ?? null) ?: null,
            'status' => trim((string) ($data['status'] ?? 'failed')),
            'outcome' => trim((string) ($data['outcome'] ?? 'error')),
            'severity' => ($data['severity'] ?? null) ?: null,
            'message' => ($data['message'] ?? null) ?: null,
            'checked_at' => ($data['checked_at'] ?? null) ?: now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($this->checkResultSupportsTraceabilityColumns()) {
            $payload['artifact_id'] = ($data['artifact_id'] ?? null) ?: null;
            $payload['evidence_id'] = ($data['evidence_id'] ?? null) ?: null;
            $payload['finding_id'] = ($data['finding_id'] ?? null) ?: null;
            $payload['remediation_action_id'] = ($data['remediation_action_id'] ?? null) ?: null;
        }

        if ($this->checkResultSupportsRetryColumns()) {
            $attemptCount = (int) ($data['attempt_count'] ?? 1);
            if ($attemptCount < 1) {
                $attemptCount = 1;
            }

            $retryCount = (int) ($data['retry_count'] ?? max(0, $attemptCount - 1));
            if ($retryCount < 0) {
                $retryCount = 0;
            }

            $payload['idempotency_key'] = ($data['idempotency_key'] ?? null) ?: null;
            $payload['attempt_count'] = $attemptCount;
            $payload['retry_count'] = $retryCount;
        }

        DB::table('automation_check_results')->insert($payload);

        return $this->findCheckResult($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function updateCheckResultTraceability(string $checkResultId, array $data): ?array
    {
        if (! $this->checkResultSupportsTraceabilityColumns()) {
            return $this->findCheckResult($checkResultId);
        }

        $current = $this->findCheckResult($checkResultId);
        if ($current === null) {
            return null;
        }

        DB::table('automation_check_results')
            ->where('id', $checkResultId)
            ->update([
                'artifact_id' => array_key_exists('artifact_id', $data)
                    ? (($data['artifact_id'] ?? null) ?: null)
                    : (($current['artifact_id'] ?? '') !== '' ? $current['artifact_id'] : null),
                'evidence_id' => array_key_exists('evidence_id', $data)
                    ? (($data['evidence_id'] ?? null) ?: null)
                    : (($current['evidence_id'] ?? '') !== '' ? $current['evidence_id'] : null),
                'finding_id' => array_key_exists('finding_id', $data)
                    ? (($data['finding_id'] ?? null) ?: null)
                    : (($current['finding_id'] ?? '') !== '' ? $current['finding_id'] : null),
                'remediation_action_id' => array_key_exists('remediation_action_id', $data)
                    ? (($data['remediation_action_id'] ?? null) ?: null)
                    : (($current['remediation_action_id'] ?? '') !== '' ? $current['remediation_action_id'] : null),
                'updated_at' => now(),
            ]);

        return $this->findCheckResult($checkResultId);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function recentCheckResultsForPack(string $packId, int $limit = 25): array
    {
        $safeLimit = max(1, min(200, $limit));

        return DB::table('automation_check_results')
            ->where('automation_pack_id', $packId)
            ->orderByDesc('checked_at')
            ->orderByDesc('created_at')
            ->limit($safeLimit)
            ->get()
            ->map(fn ($result): array => $this->mapCheckResult($result))
            ->all();
    }

    /**
     * @param  array<int, string>  $checkResultIds
     * @return array<string, string>
     */
    public function findingByCheckResultIds(array $checkResultIds): array
    {
        $ids = array_values(array_filter(array_map(static fn ($id): string => trim((string) $id), $checkResultIds), static fn (string $id): bool => $id !== ''));
        if ($ids === []) {
            return [];
        }

        $index = [];

        foreach (DB::table('automation_failure_findings')->whereIn('first_check_result_id', $ids)->get(['first_check_result_id', 'finding_id']) as $row) {
            if (is_string($row->first_check_result_id) && $row->first_check_result_id !== '' && is_string($row->finding_id) && $row->finding_id !== '') {
                $index[$row->first_check_result_id] = $row->finding_id;
            }
        }

        foreach (DB::table('automation_failure_findings')->whereIn('last_check_result_id', $ids)->get(['last_check_result_id', 'finding_id']) as $row) {
            if (is_string($row->last_check_result_id) && $row->last_check_result_id !== '' && is_string($row->finding_id) && $row->finding_id !== '') {
                $index[$row->last_check_result_id] = $row->finding_id;
            }
        }

        return $index;
    }

    /**
     * @param  array<int, string>  $checkResultIds
     * @return array<string, string>
     */
    public function remediationActionByCheckResultIds(array $checkResultIds): array
    {
        if (! DB::getSchemaBuilder()->hasColumn('automation_failure_findings', 'remediation_action_id')) {
            return [];
        }

        $ids = array_values(array_filter(array_map(static fn ($id): string => trim((string) $id), $checkResultIds), static fn (string $id): bool => $id !== ''));
        if ($ids === []) {
            return [];
        }

        $index = [];

        foreach (DB::table('automation_failure_findings')->whereIn('first_check_result_id', $ids)->get(['first_check_result_id', 'remediation_action_id']) as $row) {
            if (is_string($row->first_check_result_id)
                && $row->first_check_result_id !== ''
                && is_string($row->remediation_action_id)
                && $row->remediation_action_id !== '') {
                $index[$row->first_check_result_id] = $row->remediation_action_id;
            }
        }

        foreach (DB::table('automation_failure_findings')->whereIn('last_check_result_id', $ids)->get(['last_check_result_id', 'remediation_action_id']) as $row) {
            if (is_string($row->last_check_result_id)
                && $row->last_check_result_id !== ''
                && is_string($row->remediation_action_id)
                && $row->remediation_action_id !== '') {
                $index[$row->last_check_result_id] = $row->remediation_action_id;
            }
        }

        return $index;
    }

    /**
     * @return array<string, string>|null
     */
    public function findEvidenceDeliveryState(string $mappingId, string $targetSubjectType, string $targetSubjectId): ?array
    {
        $state = DB::table('automation_evidence_delivery_states')
            ->where('automation_output_mapping_id', $mappingId)
            ->where('target_subject_type', $targetSubjectType)
            ->where('target_subject_id', $targetSubjectId)
            ->first();

        if ($state === null) {
            return null;
        }

        return [
            'id' => (string) $state->id,
            'organization_id' => (string) $state->organization_id,
            'scope_id' => is_string($state->scope_id) ? $state->scope_id : '',
            'automation_output_mapping_id' => (string) $state->automation_output_mapping_id,
            'target_subject_type' => (string) $state->target_subject_type,
            'target_subject_id' => (string) $state->target_subject_id,
            'last_payload_fingerprint' => is_string($state->last_payload_fingerprint) ? $state->last_payload_fingerprint : '',
            'last_check_outcome' => is_string($state->last_check_outcome) ? $state->last_check_outcome : '',
            'last_artifact_id' => is_string($state->last_artifact_id) ? $state->last_artifact_id : '',
            'last_delivered_at' => $state->last_delivered_at !== null ? (string) $state->last_delivered_at : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertEvidenceDeliveryState(array $data): void
    {
        $mappingId = trim((string) ($data['automation_output_mapping_id'] ?? ''));
        $targetType = trim((string) ($data['target_subject_type'] ?? ''));
        $targetId = trim((string) ($data['target_subject_id'] ?? ''));

        if ($mappingId === '' || $targetType === '' || $targetId === '') {
            return;
        }

        $existing = DB::table('automation_evidence_delivery_states')
            ->where('automation_output_mapping_id', $mappingId)
            ->where('target_subject_type', $targetType)
            ->where('target_subject_id', $targetId)
            ->first();

        if ($existing !== null) {
            DB::table('automation_evidence_delivery_states')
                ->where('id', (string) $existing->id)
                ->update([
                    'last_payload_fingerprint' => ($data['last_payload_fingerprint'] ?? null) ?: null,
                    'last_check_outcome' => ($data['last_check_outcome'] ?? null) ?: null,
                    'last_artifact_id' => ($data['last_artifact_id'] ?? null) ?: null,
                    'last_delivered_at' => ($data['last_delivered_at'] ?? null) ?: now(),
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('automation_evidence_delivery_states')->insert([
            'id' => 'automation-evidence-state-'.Str::lower(Str::ulid()),
            'organization_id' => trim((string) ($data['organization_id'] ?? '')),
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'automation_output_mapping_id' => $mappingId,
            'target_subject_type' => $targetType,
            'target_subject_id' => $targetId,
            'last_payload_fingerprint' => ($data['last_payload_fingerprint'] ?? null) ?: null,
            'last_check_outcome' => ($data['last_check_outcome'] ?? null) ?: null,
            'last_artifact_id' => ($data['last_artifact_id'] ?? null) ?: null,
            'last_delivered_at' => ($data['last_delivered_at'] ?? null) ?: now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function recentRunsForPack(string $packId, int $limit = 15): array
    {
        $safeLimit = max(1, min(100, $limit));

        return DB::table('automation_pack_runs')
            ->where('automation_pack_id', $packId)
            ->orderByDesc('started_at')
            ->orderByDesc('created_at')
            ->limit($safeLimit)
            ->get()
            ->map(fn ($run): array => $this->mapPackRun($run))
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function enabledPacksForRuntime(?string $organizationId = null, ?string $scopeId = null): array
    {
        $query = DB::table('automation_packs')
            ->where('is_installed', true)
            ->where('is_enabled', true)
            ->orderBy('organization_id')
            ->orderBy('scope_id')
            ->orderBy('name');

        if (is_string($organizationId) && $organizationId !== '') {
            $query->where('organization_id', $organizationId);
        }

        if (is_string($scopeId) && $scopeId !== '') {
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
    public function findPackRun(string $runId): ?array
    {
        $run = DB::table('automation_pack_runs')->where('id', $runId)->first();

        return $run !== null ? $this->mapPackRun($run) : null;
    }

    /**
     * @return array<string, string>|null
     */
    public function findCheckResult(string $checkResultId): ?array
    {
        $result = DB::table('automation_check_results')->where('id', $checkResultId)->first();

        return $result !== null ? $this->mapCheckResult($result) : null;
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

    private function normalizeTargetBindingMode(string $bindingMode): string
    {
        return in_array($bindingMode, ['explicit', 'scope'], true)
            ? $bindingMode
            : 'explicit';
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

    private function normalizePosturePropagationPolicy(string $policy): string
    {
        return in_array($policy, ['disabled', 'status-only'], true)
            ? $policy
            : 'disabled';
    }

    private function normalizeExecutionMode(string $executionMode): string
    {
        return in_array($executionMode, ['both', 'runtime-only', 'manual-only'], true)
            ? $executionMode
            : 'both';
    }

    private function normalizeOnFailPolicy(string $policy): string
    {
        return in_array($policy, ['no-op', 'raise-finding', 'raise-finding-and-action'], true)
            ? $policy
            : 'no-op';
    }

    private function normalizeEvidencePolicy(string $policy): string
    {
        return in_array($policy, ['always', 'on-fail', 'on-change'], true)
            ? $policy
            : 'always';
    }

    private function normalizeRuntimeRetryMaxAttempts(int $value): int
    {
        return max(0, min(5, $value));
    }

    private function normalizeRuntimeRetryBackoffMs(int $value): int
    {
        return max(0, min(60000, $value));
    }

    private function normalizeRuntimeMaxTargets(int $value): int
    {
        return max(1, min(2000, $value));
    }

    private function normalizeRuntimePayloadMaxKb(int $value): int
    {
        return max(0, min(10240, $value));
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
            'runtime_schedule_enabled' => (bool) ($pack->runtime_schedule_enabled ?? false) ? '1' : '0',
            'runtime_schedule_cron' => is_string($pack->runtime_schedule_cron ?? null) ? $pack->runtime_schedule_cron : '',
            'runtime_schedule_timezone' => is_string($pack->runtime_schedule_timezone ?? null) ? $pack->runtime_schedule_timezone : '',
            'runtime_schedule_last_slot' => is_string($pack->runtime_schedule_last_slot ?? null) ? $pack->runtime_schedule_last_slot : '',
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
            'target_binding_mode' => is_string($mapping->target_binding_mode) ? $mapping->target_binding_mode : 'explicit',
            'target_scope_id' => is_string($mapping->target_scope_id) ? $mapping->target_scope_id : '',
            'target_selector_json' => is_string($mapping->target_selector_json) ? $mapping->target_selector_json : '',
            'posture_propagation_policy' => is_string($mapping->posture_propagation_policy) ? $mapping->posture_propagation_policy : 'disabled',
            'execution_mode' => is_string($mapping->execution_mode) ? $mapping->execution_mode : 'both',
            'on_fail_policy' => is_string($mapping->on_fail_policy) ? $mapping->on_fail_policy : 'no-op',
            'evidence_policy' => is_string($mapping->evidence_policy) ? $mapping->evidence_policy : 'always',
            'runtime_retry_max_attempts' => is_numeric($mapping->runtime_retry_max_attempts ?? null) ? (string) ((int) $mapping->runtime_retry_max_attempts) : '0',
            'runtime_retry_backoff_ms' => is_numeric($mapping->runtime_retry_backoff_ms ?? null) ? (string) ((int) $mapping->runtime_retry_backoff_ms) : '0',
            'runtime_max_targets' => is_numeric($mapping->runtime_max_targets ?? null) ? (string) ((int) $mapping->runtime_max_targets) : '200',
            'runtime_payload_max_kb' => is_numeric($mapping->runtime_payload_max_kb ?? null) ? (string) ((int) $mapping->runtime_payload_max_kb) : '512',
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
    private function mapPackRun(object $run): array
    {
        return [
            'id' => (string) $run->id,
            'automation_pack_id' => (string) $run->automation_pack_id,
            'organization_id' => (string) $run->organization_id,
            'scope_id' => is_string($run->scope_id) ? $run->scope_id : '',
            'trigger_mode' => is_string($run->trigger_mode) ? $run->trigger_mode : 'manual',
            'status' => is_string($run->status) ? $run->status : 'running',
            'started_at' => (string) $run->started_at,
            'finished_at' => $run->finished_at !== null ? (string) $run->finished_at : '',
            'duration_ms' => $run->duration_ms !== null ? (string) $run->duration_ms : '',
            'total_mappings' => (string) ($run->total_mappings ?? 0),
            'success_count' => (string) ($run->success_count ?? 0),
            'failed_count' => (string) ($run->failed_count ?? 0),
            'skipped_count' => (string) ($run->skipped_count ?? 0),
            'summary' => is_string($run->summary) ? $run->summary : '',
            'failure_reason' => is_string($run->failure_reason) ? $run->failure_reason : '',
            'initiated_by_principal_id' => is_string($run->initiated_by_principal_id) ? $run->initiated_by_principal_id : '',
            'initiated_by_membership_id' => is_string($run->initiated_by_membership_id) ? $run->initiated_by_membership_id : '',
            'created_at' => (string) $run->created_at,
            'updated_at' => (string) $run->updated_at,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapCheckResult(object $result): array
    {
        return [
            'id' => (string) $result->id,
            'automation_pack_run_id' => (string) $result->automation_pack_run_id,
            'automation_pack_id' => (string) $result->automation_pack_id,
            'automation_output_mapping_id' => is_string($result->automation_output_mapping_id) ? $result->automation_output_mapping_id : '',
            'organization_id' => (string) $result->organization_id,
            'scope_id' => is_string($result->scope_id) ? $result->scope_id : '',
            'trigger_mode' => is_string($result->trigger_mode) ? $result->trigger_mode : 'manual',
            'mapping_kind' => is_string($result->mapping_kind) ? $result->mapping_kind : 'evidence-refresh',
            'target_subject_type' => is_string($result->target_subject_type) ? $result->target_subject_type : '',
            'target_subject_id' => is_string($result->target_subject_id) ? $result->target_subject_id : '',
            'status' => is_string($result->status) ? $result->status : 'failed',
            'outcome' => is_string($result->outcome) ? $result->outcome : 'error',
            'severity' => is_string($result->severity) ? $result->severity : '',
            'message' => is_string($result->message) ? $result->message : '',
            'artifact_id' => is_string($result->artifact_id ?? null) ? $result->artifact_id : '',
            'evidence_id' => is_string($result->evidence_id ?? null) ? $result->evidence_id : '',
            'finding_id' => is_string($result->finding_id ?? null) ? $result->finding_id : '',
            'remediation_action_id' => is_string($result->remediation_action_id ?? null) ? $result->remediation_action_id : '',
            'idempotency_key' => is_string($result->idempotency_key ?? null) ? $result->idempotency_key : '',
            'attempt_count' => is_numeric($result->attempt_count ?? null) ? (string) ((int) $result->attempt_count) : '1',
            'retry_count' => is_numeric($result->retry_count ?? null) ? (string) ((int) $result->retry_count) : '0',
            'checked_at' => (string) $result->checked_at,
            'created_at' => (string) $result->created_at,
            'updated_at' => (string) $result->updated_at,
        ];
    }

    private function checkResultSupportsTraceabilityColumns(): bool
    {
        static $supported = null;

        if (is_bool($supported)) {
            return $supported;
        }

        $schema = DB::getSchemaBuilder();
        $supported = $schema->hasColumn('automation_check_results', 'artifact_id')
            && $schema->hasColumn('automation_check_results', 'evidence_id')
            && $schema->hasColumn('automation_check_results', 'finding_id')
            && $schema->hasColumn('automation_check_results', 'remediation_action_id');

        return $supported;
    }

    private function checkResultSupportsRetryColumns(): bool
    {
        static $supported = null;

        if (is_bool($supported)) {
            return $supported;
        }

        $schema = DB::getSchemaBuilder();
        $supported = $schema->hasColumn('automation_check_results', 'idempotency_key')
            && $schema->hasColumn('automation_check_results', 'attempt_count')
            && $schema->hasColumn('automation_check_results', 'retry_count');

        return $supported;
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
                ...($this->packSupportsRuntimeScheduleColumns() ? [
                    'runtime_schedule_enabled' => false,
                    'runtime_schedule_cron' => null,
                    'runtime_schedule_timezone' => null,
                    'runtime_schedule_last_slot' => null,
                ] : []),
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

    private function packSupportsRuntimeScheduleColumns(): bool
    {
        static $supported = null;

        if (is_bool($supported)) {
            return $supported;
        }

        $schema = DB::getSchemaBuilder();
        $supported = $schema->hasColumn('automation_packs', 'runtime_schedule_enabled')
            && $schema->hasColumn('automation_packs', 'runtime_schedule_cron')
            && $schema->hasColumn('automation_packs', 'runtime_schedule_timezone')
            && $schema->hasColumn('automation_packs', 'runtime_schedule_last_slot');

        return $supported;
    }

    private function mappingSupportsRuntimeGuardrailColumns(): bool
    {
        static $supported = null;

        if (is_bool($supported)) {
            return $supported;
        }

        $schema = DB::getSchemaBuilder();
        $supported = $schema->hasColumn('automation_pack_output_mappings', 'runtime_retry_max_attempts')
            && $schema->hasColumn('automation_pack_output_mappings', 'runtime_retry_backoff_ms')
            && $schema->hasColumn('automation_pack_output_mappings', 'runtime_max_targets')
            && $schema->hasColumn('automation_pack_output_mappings', 'runtime_payload_max_kb');

        return $supported;
    }
}
