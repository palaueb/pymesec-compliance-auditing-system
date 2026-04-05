<?php

namespace PymeSec\Plugins\AutomationCatalog;

use Illuminate\Support\Facades\DB;

class AutomationTargetPosturePropagationService
{
    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, string>  $checkResult
     */
    public function propagate(array $mapping, array $checkResult, string $organizationId): void
    {
        $policy = $this->normalizePolicy((string) ($mapping['posture_propagation_policy'] ?? 'disabled'));

        if ($policy === 'disabled') {
            return;
        }

        $subjectType = trim((string) ($checkResult['target_subject_type'] ?? ''));
        $subjectId = trim((string) ($checkResult['target_subject_id'] ?? ''));
        if ($subjectId === '' || ! in_array($subjectType, ['asset', 'risk'], true)) {
            return;
        }

        $posture = $this->postureFromStatus((string) ($checkResult['status'] ?? 'failed'));
        $message = trim((string) ($checkResult['message'] ?? ''));
        $checkResultId = trim((string) ($checkResult['id'] ?? ''));
        $runId = trim((string) ($checkResult['automation_pack_run_id'] ?? ''));

        $table = $subjectType === 'asset' ? 'assets' : 'risks';
        $supportsTraceability = DB::getSchemaBuilder()->hasColumn($table, 'automation_posture_check_result_id')
            && DB::getSchemaBuilder()->hasColumn($table, 'automation_posture_run_id');

        $payload = [
            'automation_posture' => $posture,
            'automation_posture_updated_at' => now(),
            'automation_posture_message' => $message !== '' ? $message : null,
            'updated_at' => now(),
        ];

        if ($supportsTraceability) {
            $payload['automation_posture_check_result_id'] = $checkResultId !== '' ? $checkResultId : null;
            $payload['automation_posture_run_id'] = $runId !== '' ? $runId : null;
        }

        DB::table($table)
            ->where('organization_id', $organizationId)
            ->where('id', $subjectId)
            ->update($payload);
    }

    private function normalizePolicy(string $policy): string
    {
        return in_array($policy, ['disabled', 'status-only'], true)
            ? $policy
            : 'disabled';
    }

    private function postureFromStatus(string $status): string
    {
        return match ($status) {
            'success' => 'healthy',
            'failed' => 'degraded',
            default => 'unknown',
        };
    }
}
