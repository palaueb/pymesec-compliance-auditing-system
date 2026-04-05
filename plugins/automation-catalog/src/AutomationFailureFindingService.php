<?php

namespace PymeSec\Plugins\AutomationCatalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutomationFailureFindingService
{
    /**
     * @param  array<string, string>  $pack
     * @param  array<string, string>  $mapping
     * @param  array<string, string>  $checkResult
     */
    public function raiseForFailedCheck(array $pack, array $mapping, array $checkResult): ?string
    {
        $policy = $this->normalizePolicy((string) ($mapping['on_fail_policy'] ?? 'no-op'));
        if ($policy === 'no-op') {
            return null;
        }

        if ((string) ($checkResult['status'] ?? '') !== 'failed') {
            return null;
        }

        $organizationId = trim((string) ($pack['organization_id'] ?? ''));
        if ($organizationId === '') {
            return null;
        }

        $scopeId = trim((string) ($checkResult['scope_id'] ?? ''));
        if ($scopeId === '') {
            $scopeId = trim((string) ($pack['scope_id'] ?? ''));
        }

        $packId = trim((string) ($pack['id'] ?? ''));
        $mappingId = trim((string) ($mapping['id'] ?? ''));
        $targetType = trim((string) ($checkResult['target_subject_type'] ?? ''));
        $targetId = trim((string) ($checkResult['target_subject_id'] ?? ''));
        $checkResultId = trim((string) ($checkResult['id'] ?? ''));

        $fingerprint = hash('sha256', implode('|', [
            $organizationId,
            $scopeId,
            $packId,
            $mappingId,
            $targetType,
            $targetId,
        ]));

        return DB::transaction(function () use (
            $organizationId,
            $scopeId,
            $packId,
            $mappingId,
            $targetType,
            $targetId,
            $checkResultId,
            $fingerprint,
            $policy,
            $pack,
            $mapping,
            $checkResult,
        ): ?string {
            $supportsRemediationActionColumn = DB::getSchemaBuilder()->hasColumn('automation_failure_findings', 'remediation_action_id');

            $existing = DB::table('automation_failure_findings')
                ->where('fingerprint', $fingerprint)
                ->first();

            if ($existing !== null) {
                $remediationActionId = is_string($existing->remediation_action_id ?? null)
                    ? (string) $existing->remediation_action_id
                    : '';

                if ($this->policyRequiresAction($policy)) {
                    $remediationActionId = $this->resolveOrCreateRemediationAction(
                        existingActionId: $remediationActionId,
                        findingId: is_string($existing->finding_id ?? null) ? (string) $existing->finding_id : '',
                        organizationId: $organizationId,
                        scopeId: $scopeId,
                        mapping: $mapping,
                        checkResult: $checkResult,
                        targetType: $targetType,
                        targetId: $targetId,
                    ) ?? '';
                }

                DB::table('automation_failure_findings')
                    ->where('id', (string) $existing->id)
                    ->update(array_filter([
                        'last_check_result_id' => $checkResultId !== '' ? $checkResultId : null,
                        'remediation_action_id' => $supportsRemediationActionColumn
                            ? ($remediationActionId !== '' ? $remediationActionId : null)
                            : null,
                        'updated_at' => now(),
                    ], static fn ($value, $key): bool => $key !== 'remediation_action_id' || $supportsRemediationActionColumn, ARRAY_FILTER_USE_BOTH));

                return is_string($existing->finding_id) ? $existing->finding_id : null;
            }

            if (! DB::getSchemaBuilder()->hasTable('findings')) {
                return null;
            }

            $findingId = 'finding-automation-'.Str::lower(Str::ulid());
            $mappingLabel = trim((string) ($mapping['mapping_label'] ?? 'Automation mapping'));
            $packKey = trim((string) ($pack['pack_key'] ?? 'automation-pack'));
            $triggerMode = trim((string) ($checkResult['trigger_mode'] ?? 'runtime'));
            $message = trim((string) ($checkResult['message'] ?? 'Automation check failed.'));
            $targetRef = $targetType !== '' && $targetId !== '' ? sprintf('%s:%s', $targetType, $targetId) : 'unknown-target';

            DB::table('findings')->insert([
                'id' => $findingId,
                'organization_id' => $organizationId,
                'scope_id' => $scopeId !== '' ? $scopeId : null,
                'title' => sprintf('Automation failure · %s · %s', $mappingLabel, $targetRef),
                'severity' => $this->findingSeverity((string) ($checkResult['severity'] ?? 'medium')),
                'description' => implode(PHP_EOL, [
                    'This finding was raised automatically by automation runtime failure policy.',
                    sprintf('Pack: %s', $packKey !== '' ? $packKey : $packId),
                    sprintf('Mapping: %s', $mappingLabel),
                    sprintf('Target: %s', $targetRef),
                    sprintf('Trigger: %s', $triggerMode !== '' ? $triggerMode : 'runtime'),
                    sprintf('Check result: %s', $checkResultId !== '' ? $checkResultId : 'not-recorded'),
                    sprintf('Message: %s', $message !== '' ? $message : 'none'),
                ]),
                'linked_control_id' => $this->validLinkedId('controls', $organizationId, $scopeId, $targetType, $targetId, 'control'),
                'linked_risk_id' => $this->validLinkedId('risks', $organizationId, $scopeId, $targetType, $targetId, 'risk'),
                'due_on' => now()->addDays(30)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $remediationActionId = null;

            if ($this->policyRequiresAction($policy)) {
                $remediationActionId = $this->createRemediationAction(
                    findingId: $findingId,
                    organizationId: $organizationId,
                    scopeId: $scopeId,
                    mapping: $mapping,
                    checkResult: $checkResult,
                    targetType: $targetType,
                    targetId: $targetId,
                );
            }

            DB::table('automation_failure_findings')->insert(array_filter([
                'id' => 'automation-failure-finding-'.Str::lower(Str::ulid()),
                'organization_id' => $organizationId,
                'scope_id' => $scopeId !== '' ? $scopeId : null,
                'automation_pack_id' => $packId !== '' ? $packId : 'automation-pack-unknown',
                'automation_output_mapping_id' => $mappingId !== '' ? $mappingId : null,
                'fingerprint' => $fingerprint,
                'target_subject_type' => $targetType !== '' ? $targetType : null,
                'target_subject_id' => $targetId !== '' ? $targetId : null,
                'finding_id' => $findingId,
                'remediation_action_id' => $supportsRemediationActionColumn ? $remediationActionId : null,
                'first_check_result_id' => $checkResultId !== '' ? $checkResultId : null,
                'last_check_result_id' => $checkResultId !== '' ? $checkResultId : null,
                'created_at' => now(),
                'updated_at' => now(),
            ], static fn ($value, $key): bool => $key !== 'remediation_action_id' || $supportsRemediationActionColumn, ARRAY_FILTER_USE_BOTH));

            return $findingId;
        });
    }

    private function normalizePolicy(string $policy): string
    {
        return in_array($policy, ['no-op', 'raise-finding', 'raise-finding-and-action'], true)
            ? $policy
            : 'no-op';
    }

    private function policyRequiresAction(string $policy): bool
    {
        return $policy === 'raise-finding-and-action';
    }

    private function findingSeverity(string $severity): string
    {
        return match ($severity) {
            'critical', 'high', 'medium', 'low' => $severity,
            'info' => 'low',
            default => 'medium',
        };
    }

    private function validLinkedId(
        string $table,
        string $organizationId,
        string $scopeId,
        string $targetType,
        string $targetId,
        string $expectedType,
    ): ?string {
        if ($targetType !== $expectedType || $targetId === '') {
            return null;
        }

        $exists = DB::table($table)
            ->where('id', $targetId)
            ->where('organization_id', $organizationId)
            ->when($scopeId !== '', static fn ($query) => $query->where(function ($nested) use ($scopeId): void {
                $nested->where('scope_id', $scopeId)->orWhereNull('scope_id');
            }))
            ->exists();

        return $exists ? $targetId : null;
    }

    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, string>  $checkResult
     */
    private function resolveOrCreateRemediationAction(
        string $existingActionId,
        string $findingId,
        string $organizationId,
        string $scopeId,
        array $mapping,
        array $checkResult,
        string $targetType,
        string $targetId,
    ): ?string {
        if ($existingActionId !== ''
            && DB::getSchemaBuilder()->hasTable('remediation_actions')
            && DB::table('remediation_actions')->where('id', $existingActionId)->exists()) {
            return $existingActionId;
        }

        return $this->createRemediationAction(
            findingId: $findingId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            mapping: $mapping,
            checkResult: $checkResult,
            targetType: $targetType,
            targetId: $targetId,
        );
    }

    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, string>  $checkResult
     */
    private function createRemediationAction(
        string $findingId,
        string $organizationId,
        string $scopeId,
        array $mapping,
        array $checkResult,
        string $targetType,
        string $targetId,
    ): ?string {
        if (! DB::getSchemaBuilder()->hasTable('remediation_actions')) {
            return null;
        }

        $actionId = 'action-automation-'.Str::lower(Str::ulid());
        $mappingLabel = trim((string) ($mapping['mapping_label'] ?? 'Automation mapping'));
        $message = trim((string) ($checkResult['message'] ?? 'Automation check failed.'));
        $checkResultId = trim((string) ($checkResult['id'] ?? ''));
        $targetRef = $targetType !== '' && $targetId !== '' ? sprintf('%s:%s', $targetType, $targetId) : 'unknown-target';

        DB::table('remediation_actions')->insert([
            'id' => $actionId,
            'finding_id' => $findingId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId !== '' ? $scopeId : null,
            'title' => sprintf('Investigate automation failure · %s', $mappingLabel),
            'status' => 'planned',
            'notes' => implode(PHP_EOL, [
                'This remediation action was created automatically by automation failure policy.',
                sprintf('Mapping: %s', $mappingLabel),
                sprintf('Target: %s', $targetRef),
                sprintf('Check result: %s', $checkResultId !== '' ? $checkResultId : 'not-recorded'),
                sprintf('Message: %s', $message !== '' ? $message : 'none'),
            ]),
            'due_on' => now()->addDays(14)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $actionId;
    }
}
