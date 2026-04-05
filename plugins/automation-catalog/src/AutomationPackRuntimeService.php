<?php

namespace PymeSec\Plugins\AutomationCatalog;

use Cron\CronExpression;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PymeSec\Plugins\AutomationCatalog\Runtime\AutomationPackRuntimeExecutorRegistry;
use RuntimeException;
use Throwable;

class AutomationPackRuntimeService
{
    public function __construct(
        private readonly AutomationCatalogRepository $repository,
        private readonly AutomationOutputMappingDeliveryService $delivery,
        private readonly AutomationTargetPosturePropagationService $posturePropagation,
        private readonly AutomationFailureFindingService $failureFinding,
        private readonly AutomationPackRuntimeExecutorRegistry $executors,
    ) {}

    /**
     * @return array<string, string>|null
     */
    public function runPack(
        string $packId,
        string $triggerMode,
        ?string $principalId,
        ?string $membershipId,
    ): ?array {
        $pack = $this->repository->find($packId);

        if ($pack === null) {
            return null;
        }

        $resolvedPrincipalId = $this->resolvePrincipalId($pack, $principalId);
        $resolvedMembershipId = is_string($membershipId) && $membershipId !== '' ? $membershipId : null;
        $startedAt = microtime(true);
        $runStartedAt = now();

        $run = $this->repository->createPackRun($packId, [
            'organization_id' => $pack['organization_id'],
            'scope_id' => $pack['scope_id'] !== '' ? $pack['scope_id'] : null,
            'trigger_mode' => in_array($triggerMode, ['manual', 'scheduled'], true) ? $triggerMode : 'manual',
            'status' => 'running',
            'started_at' => $runStartedAt,
            'initiated_by_principal_id' => $resolvedPrincipalId,
            'initiated_by_membership_id' => $resolvedMembershipId,
        ]);

        if ($run === null) {
            return null;
        }

        try {
            if (($pack['is_installed'] ?? '0') !== '1' || ($pack['is_enabled'] ?? '0') !== '1') {
                throw new RuntimeException('Pack must be installed and enabled before runtime execution.');
            }

            $activeMappings = array_values(array_filter(
                $this->repository->outputMappings($packId),
                fn (array $mapping): bool => ($mapping['is_active'] ?? '0') === '1' && $this->mappingRunsInRuntime($mapping)
            ));

            $executor = $this->executors->resolve($pack);
            if ($executor === null) {
                throw new RuntimeException(sprintf('No runtime executor available for pack [%s].', (string) ($pack['pack_key'] ?? $packId)));
            }

            $results = [];

            foreach ($activeMappings as $mapping) {
                $mappingId = (string) ($mapping['id'] ?? '');
                $resolvedTargets = $this->resolveTargets($pack, $mapping);
                $targets = $resolvedTargets['targets'];
                $targetsError = $resolvedTargets['error'];

                if ($targetsError !== '') {
                    if ($mappingId !== '') {
                        $this->repository->markOutputMappingDelivery($mappingId, 'failed', $targetsError);
                    }

                    $results[] = [
                        'mapping_id' => $mappingId,
                        'mapping_label' => (string) ($mapping['mapping_label'] ?? ''),
                        'mapping_kind' => (string) ($mapping['mapping_kind'] ?? ''),
                        'status' => 'failed',
                        'outcome' => 'error',
                        'severity' => 'high',
                        'message' => $targetsError,
                        'target_subject_type' => (string) ($mapping['target_subject_type'] ?? ''),
                        'target_subject_id' => '',
                        'target_scope_id' => (string) ($mapping['target_scope_id'] ?? ''),
                    ];

                    $this->repository->createCheckResult([
                        'automation_pack_run_id' => (string) $run['id'],
                        'automation_pack_id' => $packId,
                        'automation_output_mapping_id' => $mappingId !== '' ? $mappingId : null,
                        'organization_id' => (string) $pack['organization_id'],
                        'scope_id' => $this->resolveCheckResultScopeId(
                            packScopeId: (string) ($pack['scope_id'] ?? ''),
                            mappingScopeId: (string) ($mapping['target_scope_id'] ?? ''),
                            targetScopeId: '',
                        ),
                        'trigger_mode' => in_array($triggerMode, ['manual', 'scheduled'], true) ? $triggerMode : 'manual',
                        'mapping_kind' => (string) ($mapping['mapping_kind'] ?? ''),
                        'target_subject_type' => (string) ($mapping['target_subject_type'] ?? ''),
                        'target_subject_id' => '',
                        'status' => 'failed',
                        'outcome' => 'error',
                        'severity' => 'high',
                        'message' => $targetsError,
                        'checked_at' => now(),
                    ]);

                    continue;
                }

                if ($targets === []) {
                    $noTargetMessage = 'No target objects resolved for this mapping.';
                    if ($mappingId !== '') {
                        $this->repository->markOutputMappingDelivery($mappingId, 'skipped', $noTargetMessage);
                    }

                    $results[] = [
                        'mapping_id' => $mappingId,
                        'mapping_label' => (string) ($mapping['mapping_label'] ?? ''),
                        'mapping_kind' => (string) ($mapping['mapping_kind'] ?? ''),
                        'status' => 'skipped',
                        'outcome' => 'not-applicable',
                        'severity' => 'info',
                        'message' => $noTargetMessage,
                        'target_subject_type' => (string) ($mapping['target_subject_type'] ?? ''),
                        'target_subject_id' => '',
                        'target_scope_id' => (string) ($mapping['target_scope_id'] ?? ''),
                    ];

                    $this->repository->createCheckResult([
                        'automation_pack_run_id' => (string) $run['id'],
                        'automation_pack_id' => $packId,
                        'automation_output_mapping_id' => $mappingId !== '' ? $mappingId : null,
                        'organization_id' => (string) $pack['organization_id'],
                        'scope_id' => $this->resolveCheckResultScopeId(
                            packScopeId: (string) ($pack['scope_id'] ?? ''),
                            mappingScopeId: (string) ($mapping['target_scope_id'] ?? ''),
                            targetScopeId: '',
                        ),
                        'trigger_mode' => in_array($triggerMode, ['manual', 'scheduled'], true) ? $triggerMode : 'manual',
                        'mapping_kind' => (string) ($mapping['mapping_kind'] ?? ''),
                        'target_subject_type' => (string) ($mapping['target_subject_type'] ?? ''),
                        'target_subject_id' => '',
                        'status' => 'skipped',
                        'outcome' => $this->checkOutcomeForStatus('skipped'),
                        'severity' => $this->checkSeverityForStatus('skipped'),
                        'message' => $noTargetMessage,
                        'checked_at' => now(),
                    ]);

                    continue;
                }

                $mappingTargetResults = [];

                foreach ($targets as $target) {
                    $idempotencyKey = $this->idempotencyKeyFor(
                        packId: $packId,
                        mappingId: $mappingId,
                        targetType: (string) $target['subject_type'],
                        targetId: (string) $target['subject_id'],
                    );
                    $attemptsAllowed = 1 + $this->mappingRetryMaxAttempts($mapping);
                    $backoffMs = $this->mappingRetryBackoffMs($mapping);
                    $attempt = 1;
                    $targetResult = [];

                    while ($attempt <= $attemptsAllowed) {
                        $targetResult = $this->executeMappingTargetOnce(
                            pack: $pack,
                            mapping: $mapping,
                            mappingId: $mappingId,
                            target: $target,
                            executor: $executor,
                            triggerMode: $triggerMode,
                            runStartedAt: $runStartedAt->toDateTimeString(),
                            resolvedPrincipalId: $resolvedPrincipalId,
                            resolvedMembershipId: $resolvedMembershipId,
                        );

                        if (($targetResult['status'] ?? 'failed') !== 'failed' || $attempt >= $attemptsAllowed) {
                            break;
                        }

                        if ($backoffMs > 0) {
                            usleep($backoffMs * 1000);
                        }

                        $attempt++;
                    }

                    $attemptCount = max(1, $attempt);
                    $retryCount = max(0, $attemptCount - 1);
                    $targetResult['attempt_count'] = (string) $attemptCount;
                    $targetResult['retry_count'] = (string) $retryCount;
                    $targetResult['idempotency_key'] = $idempotencyKey;

                    if ($retryCount > 0) {
                        $baseMessage = trim((string) ($targetResult['message'] ?? ''));
                        $targetResult['message'] = trim(sprintf(
                            '%s%sRetry attempts: %d.',
                            $baseMessage,
                            $baseMessage !== '' ? ' ' : '',
                            $retryCount
                        ));
                    }

                    $mappingTargetResults[] = $targetResult;
                }

                foreach ($mappingTargetResults as $mappingTargetResult) {
                    $checkResult = $this->repository->createCheckResult([
                        'automation_pack_run_id' => (string) $run['id'],
                        'automation_pack_id' => $packId,
                        'automation_output_mapping_id' => $mappingId !== '' ? $mappingId : null,
                        'organization_id' => (string) $pack['organization_id'],
                        'scope_id' => $this->resolveCheckResultScopeId(
                            packScopeId: (string) ($pack['scope_id'] ?? ''),
                            mappingScopeId: (string) ($mapping['target_scope_id'] ?? ''),
                            targetScopeId: (string) ($mappingTargetResult['target_scope_id'] ?? ''),
                        ),
                        'trigger_mode' => in_array($triggerMode, ['manual', 'scheduled'], true) ? $triggerMode : 'manual',
                        'mapping_kind' => (string) ($mappingTargetResult['mapping_kind'] ?? ''),
                        'target_subject_type' => (string) ($mappingTargetResult['target_subject_type'] ?? ''),
                        'target_subject_id' => (string) ($mappingTargetResult['target_subject_id'] ?? ''),
                        'status' => (string) ($mappingTargetResult['status'] ?? 'failed'),
                        'outcome' => (string) ($mappingTargetResult['outcome'] ?? $this->checkOutcomeForStatus((string) ($mappingTargetResult['status'] ?? 'failed'))),
                        'severity' => (string) ($mappingTargetResult['severity'] ?? $this->checkSeverityForStatus((string) ($mappingTargetResult['status'] ?? 'failed'))),
                        'message' => (string) ($mappingTargetResult['message'] ?? ''),
                        'artifact_id' => (string) ($mappingTargetResult['artifact_id'] ?? ''),
                        'evidence_id' => (string) ($mappingTargetResult['evidence_id'] ?? ''),
                        'idempotency_key' => (string) ($mappingTargetResult['idempotency_key'] ?? ''),
                        'attempt_count' => (string) ($mappingTargetResult['attempt_count'] ?? '1'),
                        'retry_count' => (string) ($mappingTargetResult['retry_count'] ?? '0'),
                        'checked_at' => now(),
                    ]);

                    if (is_array($checkResult)) {
                        $this->posturePropagation->propagate(
                            mapping: $mapping,
                            checkResult: $checkResult,
                            organizationId: (string) $pack['organization_id'],
                        );

                        $findingId = $this->failureFinding->raiseForFailedCheck(
                            pack: $pack,
                            mapping: $mapping,
                            checkResult: $checkResult,
                        );

                        $checkResultId = (string) ($checkResult['id'] ?? '');
                        if ($checkResultId !== '' && $findingId !== null && $findingId !== '') {
                            $actionByCheckResult = $this->repository->remediationActionByCheckResultIds([$checkResultId]);
                            $this->repository->updateCheckResultTraceability($checkResultId, [
                                'finding_id' => $findingId,
                                'remediation_action_id' => $actionByCheckResult[$checkResultId] ?? null,
                            ]);
                        }
                    } else {
                        $this->posturePropagation->propagate(
                            mapping: $mapping,
                            checkResult: [
                                'status' => (string) ($mappingTargetResult['status'] ?? 'failed'),
                                'target_subject_type' => (string) ($mappingTargetResult['target_subject_type'] ?? ''),
                                'target_subject_id' => (string) ($mappingTargetResult['target_subject_id'] ?? ''),
                                'message' => (string) ($mappingTargetResult['message'] ?? ''),
                            ],
                            organizationId: (string) $pack['organization_id'],
                        );
                    }
                }

                $mappingSuccess = count(array_filter($mappingTargetResults, static fn (array $result): bool => ($result['status'] ?? '') === 'success'));
                $mappingFailed = count(array_filter($mappingTargetResults, static fn (array $result): bool => ($result['status'] ?? '') === 'failed'));
                $mappingSkipped = count(array_filter($mappingTargetResults, static fn (array $result): bool => ($result['status'] ?? '') === 'skipped'));
                $mappingStatus = $mappingFailed > 0
                    ? 'failed'
                    : ($mappingSuccess > 0 ? 'success' : 'skipped');

                if ($mappingId !== '') {
                    $this->repository->markOutputMappingDelivery(
                        mappingId: $mappingId,
                        status: $mappingStatus,
                        message: sprintf(
                            'Targets %d · success %d · failed %d · skipped %d',
                            count($mappingTargetResults),
                            $mappingSuccess,
                            $mappingFailed,
                            $mappingSkipped,
                        ),
                    );
                }

                $results = [...$results, ...$mappingTargetResults];
            }

            $successCount = count(array_filter($results, static fn (array $result): bool => ($result['status'] ?? '') === 'success'));
            $failedCount = count(array_filter($results, static fn (array $result): bool => ($result['status'] ?? '') === 'failed'));
            $skippedCount = count(array_filter($results, static fn (array $result): bool => ($result['status'] ?? '') === 'skipped'));
            $totalDeliveries = count($results);

            $status = match (true) {
                $failedCount === 0 => 'success',
                $successCount > 0 || $skippedCount > 0 => 'partial',
                default => 'failed',
            };

            $failureReason = null;
            $healthState = 'healthy';

            if ($status === 'partial') {
                $healthState = 'degraded';
                $failureReason = sprintf('Runtime completed with failures: %d failed deliveries.', $failedCount);
            } elseif ($status === 'failed') {
                $healthState = 'failing';
                $failureReason = sprintf('Runtime failed: %d of %d deliveries failed.', $failedCount, $totalDeliveries);
            }

            $this->repository->updateHealth($packId, [
                'health_state' => $healthState,
                'last_failure_reason' => $failureReason,
                'last_run_at' => now(),
            ]);

            return $this->repository->completePackRun((string) $run['id'], [
                'status' => $status,
                'finished_at' => now(),
                'duration_ms' => (string) max(0, (int) round((microtime(true) - $startedAt) * 1000)),
                'total_mappings' => (string) $totalDeliveries,
                'success_count' => (string) $successCount,
                'failed_count' => (string) $failedCount,
                'skipped_count' => (string) $skippedCount,
                'summary' => json_encode($results, JSON_UNESCAPED_SLASHES),
                'failure_reason' => $failureReason,
            ]);
        } catch (Throwable $exception) {
            $this->repository->updateHealth($packId, [
                'health_state' => 'failing',
                'last_failure_reason' => $exception->getMessage(),
                'last_run_at' => now(),
            ]);

            return $this->repository->completePackRun((string) $run['id'], [
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => (string) max(0, (int) round((microtime(true) - $startedAt) * 1000)),
                'total_mappings' => '0',
                'success_count' => '0',
                'failed_count' => '0',
                'skipped_count' => '0',
                'summary' => null,
                'failure_reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function runEnabledPacks(
        ?string $organizationId,
        ?string $scopeId,
        string $triggerMode,
        ?string $principalId,
        ?string $membershipId,
    ): array {
        $runs = [];

        foreach ($this->repository->enabledPacksForRuntime($organizationId, $scopeId) as $pack) {
            $run = $this->runPack(
                packId: (string) $pack['id'],
                triggerMode: $triggerMode,
                principalId: $principalId,
                membershipId: $membershipId,
            );

            if (is_array($run)) {
                $runs[] = $run;
            }
        }

        return $runs;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function runDueScheduledPacks(
        ?string $organizationId,
        ?string $scopeId,
        ?string $principalId,
        ?string $membershipId,
    ): array {
        $runs = [];
        $nowUtc = now('UTC');

        foreach ($this->repository->enabledPacksForRuntime($organizationId, $scopeId) as $pack) {
            $dueSlot = $this->scheduledDueSlot($pack, $nowUtc);
            if ($dueSlot === null) {
                continue;
            }

            $run = $this->runPack(
                packId: (string) $pack['id'],
                triggerMode: 'scheduled',
                principalId: $principalId,
                membershipId: $membershipId,
            );

            if (is_array($run)) {
                $runs[] = $run;
            }

            $this->repository->markPackRuntimeScheduleSlot((string) $pack['id'], $dueSlot);
        }

        return $runs;
    }

    /**
     * @param  array<string, string>  $pack
     */
    private function resolvePrincipalId(array $pack, ?string $principalId): string
    {
        if (is_string($principalId) && $principalId !== '') {
            return $principalId;
        }

        if (($pack['owner_principal_id'] ?? '') !== '') {
            return (string) $pack['owner_principal_id'];
        }

        return 'principal-org-a';
    }

    /**
     * @param  array<string, string>  $pack
     */
    private function scheduledDueSlot(array $pack, \Illuminate\Support\Carbon $nowUtc): ?string
    {
        if (($pack['runtime_schedule_enabled'] ?? '0') !== '1') {
            return null;
        }

        $cron = trim((string) ($pack['runtime_schedule_cron'] ?? ''));
        if ($cron === '') {
            return null;
        }

        $timezone = trim((string) ($pack['runtime_schedule_timezone'] ?? ''));
        if ($timezone === '') {
            $timezone = 'UTC';
        }

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            return null;
        }

        try {
            $expression = CronExpression::factory($cron);
        } catch (Throwable) {
            return null;
        }

        $localNow = $nowUtc->copy()->setTimezone($timezone);
        if (! $expression->isDue($localNow->toDateTimeString())) {
            return null;
        }

        $slot = $localNow->format('Y-m-d H:i');

        return ($pack['runtime_schedule_last_slot'] ?? '') === $slot ? null : $slot;
    }

    /**
     * @param  array<string, string>  $pack
     * @param  array<string, string>  $mapping
     * @param  array{subject_type: string, subject_id: string, scope_id: string}  $target
     * @return array<string, string>
     */
    private function executeMappingTargetOnce(
        array $pack,
        array $mapping,
        string $mappingId,
        array $target,
        \PymeSec\Plugins\AutomationCatalog\Runtime\AutomationPackRuntimeExecutorInterface $executor,
        string $triggerMode,
        string $runStartedAt,
        string $resolvedPrincipalId,
        ?string $resolvedMembershipId,
    ): array {
        $resolvedMapping = [
            ...$mapping,
            'target_subject_type' => $target['subject_type'],
            'target_subject_id' => $target['subject_id'],
        ];

        if (($mapping['mapping_kind'] ?? '') === 'evidence-refresh') {
            $payload = $executor->generateEvidencePayload(
                pack: $pack,
                mapping: $mapping,
                target: $target,
                context: [
                    'trigger_mode' => in_array($triggerMode, ['manual', 'scheduled'], true) ? $triggerMode : 'manual',
                    'run_started_at' => $runStartedAt,
                ],
            );

            $payloadStatus = (string) ($payload['status'] ?? 'failed');
            $checkOutcome = $this->normalizeCheckOutcome(
                (string) ($payload['check_outcome'] ?? $this->checkOutcomeForStatus($payloadStatus))
            );
            $checkSeverity = $this->normalizeCheckSeverity(
                (string) ($payload['severity'] ?? $this->checkSeverityForStatus($payloadStatus))
            );
            $payloadFingerprint = trim((string) ($payload['change_fingerprint'] ?? ''));
            if ($payloadFingerprint === '') {
                $payloadFingerprint = hash('sha256', (string) ($payload['content'] ?? ''));
            }

            if ($payloadStatus !== 'success') {
                return [
                    'mapping_id' => $mappingId,
                    'mapping_label' => (string) ($mapping['mapping_label'] ?? ''),
                    'mapping_kind' => (string) ($mapping['mapping_kind'] ?? ''),
                    'status' => 'failed',
                    'outcome' => $checkOutcome,
                    'severity' => $checkSeverity,
                    'message' => (string) ($payload['message'] ?? 'Runtime payload generation failed.'),
                    'target_subject_type' => $target['subject_type'],
                    'target_subject_id' => $target['subject_id'],
                    'target_scope_id' => $target['scope_id'],
                    'payload_fingerprint' => $payloadFingerprint,
                    'artifact_id' => '',
                    'evidence_id' => '',
                ];
            }

            $payloadMaxBytes = $this->mappingPayloadMaxBytes($mapping);
            $payloadContent = (string) ($payload['content'] ?? '');
            if (strlen($payloadContent) > $payloadMaxBytes) {
                return [
                    'mapping_id' => $mappingId,
                    'mapping_label' => (string) ($mapping['mapping_label'] ?? ''),
                    'mapping_kind' => (string) ($mapping['mapping_kind'] ?? ''),
                    'status' => 'failed',
                    'outcome' => 'error',
                    'severity' => 'high',
                    'message' => sprintf(
                        'Guardrail: payload size %d bytes exceeds runtime payload max %d bytes.',
                        strlen($payloadContent),
                        $payloadMaxBytes,
                    ),
                    'target_subject_type' => $target['subject_type'],
                    'target_subject_id' => $target['subject_id'],
                    'target_scope_id' => $target['scope_id'],
                    'payload_fingerprint' => $payloadFingerprint,
                    'artifact_id' => '',
                    'evidence_id' => '',
                ];
            }

            $artifactType = trim((string) ($payload['artifact_type'] ?? 'report'));
            if (! in_array($artifactType, ['document', 'workpaper', 'snapshot', 'report', 'ticket', 'log-export', 'statement', 'other'], true)) {
                return [
                    'mapping_id' => $mappingId,
                    'mapping_label' => (string) ($mapping['mapping_label'] ?? ''),
                    'mapping_kind' => (string) ($mapping['mapping_kind'] ?? ''),
                    'status' => 'failed',
                    'outcome' => 'error',
                    'severity' => 'high',
                    'message' => sprintf('Guardrail: artifact type [%s] is not allowed.', $artifactType),
                    'target_subject_type' => $target['subject_type'],
                    'target_subject_id' => $target['subject_id'],
                    'target_scope_id' => $target['scope_id'],
                    'payload_fingerprint' => $payloadFingerprint,
                    'artifact_id' => '',
                    'evidence_id' => '',
                ];
            }

            $evidenceDecision = $this->evaluateEvidencePolicy(
                mapping: $mapping,
                target: $target,
                checkOutcome: $checkOutcome,
                payloadFingerprint: $payloadFingerprint,
            );

            if (! $evidenceDecision['deliver']) {
                return [
                    'mapping_id' => $mappingId,
                    'mapping_label' => (string) ($mapping['mapping_label'] ?? ''),
                    'mapping_kind' => (string) ($mapping['mapping_kind'] ?? ''),
                    'status' => 'skipped',
                    'outcome' => $checkOutcome,
                    'severity' => $checkSeverity,
                    'message' => $evidenceDecision['reason'],
                    'target_subject_type' => $target['subject_type'],
                    'target_subject_id' => $target['subject_id'],
                    'target_scope_id' => $target['scope_id'],
                    'payload_fingerprint' => $payloadFingerprint,
                    'artifact_id' => '',
                    'evidence_id' => '',
                ];
            }

            $upload = $this->buildUploadedFileFromContent(
                (string) ($payload['filename'] ?? 'runtime-output.txt'),
                $payloadContent
            );

            try {
                $result = $this->delivery->deliver(
                    mapping: $resolvedMapping,
                    data: [
                        'output_file' => $upload,
                        'evidence_kind' => $artifactType,
                    ],
                    principalId: $resolvedPrincipalId,
                    membershipId: $resolvedMembershipId,
                    organizationId: (string) $pack['organization_id'],
                    scopeId: $target['scope_id'] !== '' ? $target['scope_id'] : (($pack['scope_id'] ?? '') !== '' ? (string) $pack['scope_id'] : null),
                );
            } finally {
                @unlink($upload->getPathname());
            }

            if (($result['status'] ?? '') === 'success' && $mappingId !== '') {
                $this->repository->upsertEvidenceDeliveryState([
                    'organization_id' => (string) $pack['organization_id'],
                    'scope_id' => $target['scope_id'] !== '' ? $target['scope_id'] : (($pack['scope_id'] ?? '') !== '' ? (string) $pack['scope_id'] : null),
                    'automation_output_mapping_id' => $mappingId,
                    'target_subject_type' => (string) $target['subject_type'],
                    'target_subject_id' => (string) $target['subject_id'],
                    'last_payload_fingerprint' => $payloadFingerprint,
                    'last_check_outcome' => $checkOutcome,
                    'last_artifact_id' => is_string($result['artifact_id'] ?? null) ? (string) $result['artifact_id'] : null,
                    'last_delivered_at' => now(),
                ]);
            }

            return [
                'mapping_id' => $mappingId,
                'mapping_label' => (string) ($mapping['mapping_label'] ?? ''),
                'mapping_kind' => (string) ($mapping['mapping_kind'] ?? ''),
                'status' => (string) ($result['status'] ?? 'failed'),
                'outcome' => $checkOutcome,
                'severity' => $checkSeverity,
                'message' => is_string($result['message'] ?? null) ? (string) $result['message'] : '',
                'target_subject_type' => $target['subject_type'],
                'target_subject_id' => $target['subject_id'],
                'target_scope_id' => $target['scope_id'],
                'payload_fingerprint' => $payloadFingerprint,
                'artifact_id' => is_string($result['artifact_id'] ?? null) ? (string) $result['artifact_id'] : '',
                'evidence_id' => is_string($result['evidence_id'] ?? null) ? (string) $result['evidence_id'] : '',
            ];
        }

        $result = $this->delivery->deliver(
            mapping: $resolvedMapping,
            data: [],
            principalId: $resolvedPrincipalId,
            membershipId: $resolvedMembershipId,
            organizationId: (string) $pack['organization_id'],
            scopeId: $target['scope_id'] !== '' ? $target['scope_id'] : (($pack['scope_id'] ?? '') !== '' ? (string) $pack['scope_id'] : null),
        );

        $status = (string) ($result['status'] ?? 'failed');

        return [
            'mapping_id' => $mappingId,
            'mapping_label' => (string) ($mapping['mapping_label'] ?? ''),
            'mapping_kind' => (string) ($mapping['mapping_kind'] ?? ''),
            'status' => $status,
            'outcome' => $this->checkOutcomeForStatus($status),
            'severity' => $this->checkSeverityForStatus($status),
            'message' => is_string($result['message'] ?? null) ? (string) $result['message'] : '',
            'target_subject_type' => $target['subject_type'],
            'target_subject_id' => $target['subject_id'],
            'target_scope_id' => $target['scope_id'],
            'payload_fingerprint' => '',
            'artifact_id' => '',
            'evidence_id' => '',
        ];
    }

    /**
     * @param  array<string, string>  $pack
     * @param  array<string, string>  $mapping
     * @return array{targets: array<int, array{subject_type: string, subject_id: string, scope_id: string}>, error: string}
     */
    private function resolveTargets(array $pack, array $mapping): array
    {
        $subjectType = (string) ($mapping['target_subject_type'] ?? '');
        if ($subjectType === '') {
            return ['targets' => [], 'error' => ''];
        }

        $bindingMode = (string) ($mapping['target_binding_mode'] ?? 'explicit');

        if ($bindingMode !== 'scope') {
            $subjectId = trim((string) ($mapping['target_subject_id'] ?? ''));

            if ($subjectId === '') {
                return ['targets' => [], 'error' => ''];
            }

            return [
                'targets' => [[
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'scope_id' => (string) ($mapping['target_scope_id'] ?? ''),
                ]],
                'error' => '',
            ];
        }

        $maxTargets = $this->mappingMaxTargets($mapping);

        return match ($subjectType) {
            'asset' => $this->resolveScopeTargetsFromTable(
                table: 'assets',
                idColumn: 'id',
                subjectType: 'asset',
                organizationId: (string) ($pack['organization_id'] ?? ''),
                resolverScopeId: (string) ($mapping['target_scope_id'] ?? ''),
                defaultScopeId: (string) ($pack['scope_id'] ?? ''),
                allowedTags: ['type', 'criticality', 'classification'],
                selectorJson: (string) ($mapping['target_selector_json'] ?? ''),
                maxTargets: $maxTargets,
            ),
            'risk' => $this->resolveScopeTargetsFromTable(
                table: 'risks',
                idColumn: 'id',
                subjectType: 'risk',
                organizationId: (string) ($pack['organization_id'] ?? ''),
                resolverScopeId: (string) ($mapping['target_scope_id'] ?? ''),
                defaultScopeId: (string) ($pack['scope_id'] ?? ''),
                allowedTags: ['category'],
                selectorJson: (string) ($mapping['target_selector_json'] ?? ''),
                maxTargets: $maxTargets,
            ),
            default => ['targets' => [], 'error' => sprintf('Guardrail: scope resolver does not support subject type [%s].', $subjectType)],
        };
    }

    /**
     * @param  array<int, string>  $allowedTags
     * @return array{targets: array<int, array{subject_type: string, subject_id: string, scope_id: string}>, error: string}
     */
    private function resolveScopeTargetsFromTable(
        string $table,
        string $idColumn,
        string $subjectType,
        string $organizationId,
        string $resolverScopeId,
        string $defaultScopeId,
        array $allowedTags,
        string $selectorJson,
        int $maxTargets,
    ): array {
        $query = DB::table($table)
            ->where('organization_id', $organizationId);

        $scopeId = $resolverScopeId !== '' ? $resolverScopeId : $defaultScopeId;
        if ($scopeId !== '') {
            $query->where(function ($nested) use ($scopeId): void {
                $nested->where('scope_id', $scopeId)->orWhereNull('scope_id');
            });
        }

        $selectorFilters = $this->selectorTagsToFilters($selectorJson, $allowedTags);
        if ($selectorFilters['invalid_tags'] !== []) {
            return [
                'targets' => [],
                'error' => sprintf(
                    'Guardrail: malformed or unsupported selector tags [%s].',
                    implode(', ', $selectorFilters['invalid_tags'])
                ),
            ];
        }

        foreach ($selectorFilters['filters'] as $filter) {
            $query->where($filter['key'], $filter['value']);
        }

        $resolvedCount = (int) (clone $query)->count();
        if ($resolvedCount > $maxTargets) {
            return [
                'targets' => [],
                'error' => sprintf(
                    'Guardrail: resolved targets %d exceeds max targets %d for mapping runtime policy.',
                    $resolvedCount,
                    $maxTargets,
                ),
            ];
        }

        $targets = $query
            ->limit($maxTargets)
            ->get([$idColumn, 'scope_id'])
            ->map(static fn (object $row): array => [
                'subject_type' => $subjectType,
                'subject_id' => (string) $row->{$idColumn},
                'scope_id' => is_string($row->scope_id ?? null) ? (string) $row->scope_id : '',
            ])
            ->all();

        return ['targets' => $targets, 'error' => ''];
    }

    /**
     * @param  array<int, string>  $allowedTags
     * @return array{filters: array<int, array{key: string, value: string}>, invalid_tags: array<int, string>}
     */
    private function selectorTagsToFilters(string $selectorJson, array $allowedTags): array
    {
        $decoded = json_decode($selectorJson, true);
        if (! is_array($decoded)) {
            return ['filters' => [], 'invalid_tags' => []];
        }

        $rawTags = is_array($decoded['tags'] ?? null) ? $decoded['tags'] : [];
        $filters = [];
        $invalidTags = [];

        foreach ($rawTags as $tag) {
            if (! is_string($tag) || $tag === '') {
                continue;
            }

            [$rawKey, $rawValue] = array_pad(explode(':', $tag, 2), 2, '');
            $key = trim($rawKey);
            $value = trim($rawValue);

            if ($key === '' || $value === '' || ! in_array($key, $allowedTags, true)) {
                $invalidTags[] = $tag;
                continue;
            }

            $filters[] = ['key' => $key, 'value' => $value];
        }

        return ['filters' => $filters, 'invalid_tags' => $invalidTags];
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function mappingRetryMaxAttempts(array $mapping): int
    {
        return max(0, min(5, (int) ($mapping['runtime_retry_max_attempts'] ?? '0')));
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function mappingRetryBackoffMs(array $mapping): int
    {
        return max(0, min(60000, (int) ($mapping['runtime_retry_backoff_ms'] ?? '0')));
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function mappingMaxTargets(array $mapping): int
    {
        return max(1, min(2000, (int) ($mapping['runtime_max_targets'] ?? '200')));
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function mappingPayloadMaxBytes(array $mapping): int
    {
        $maxKb = max(0, min(10240, (int) ($mapping['runtime_payload_max_kb'] ?? '512')));

        return $maxKb * 1024;
    }

    private function idempotencyKeyFor(string $packId, string $mappingId, string $targetType, string $targetId): string
    {
        return hash('sha256', implode('|', [
            $packId,
            $mappingId,
            $targetType,
            $targetId,
        ]));
    }

    private function buildUploadedFileFromContent(string $filename, string $content): UploadedFile
    {
        $safeFilename = trim($filename) !== '' ? trim($filename) : 'runtime-output.txt';
        if (! str_contains($safeFilename, '.')) {
            $safeFilename .= '.txt';
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'pymesec-runtime-');

        if ($tmpPath === false) {
            throw new RuntimeException('Unable to allocate temporary runtime output file.');
        }

        file_put_contents($tmpPath, $content);

        return new UploadedFile(
            path: $tmpPath,
            originalName: $safeFilename,
            mimeType: 'text/plain',
            error: null,
            test: true,
        );
    }

    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, string>  $target
     * @return array{deliver: bool, reason: string}
     */
    private function evaluateEvidencePolicy(array $mapping, array $target, string $checkOutcome, string $payloadFingerprint): array
    {
        $policy = $this->normalizeEvidencePolicy((string) ($mapping['evidence_policy'] ?? 'always'));

        if ($policy === 'on-fail' && ! in_array($checkOutcome, ['fail', 'error'], true)) {
            return [
                'deliver' => false,
                'reason' => sprintf('Evidence policy on-fail skipped delivery because outcome is [%s].', $checkOutcome),
            ];
        }

        if ($policy === 'on-change') {
            $mappingId = trim((string) ($mapping['id'] ?? ''));
            $targetType = trim((string) ($target['subject_type'] ?? ''));
            $targetId = trim((string) ($target['subject_id'] ?? ''));

            if ($mappingId !== '' && $targetType !== '' && $targetId !== '') {
                $state = $this->repository->findEvidenceDeliveryState($mappingId, $targetType, $targetId);
                if (is_array($state) && (string) ($state['last_payload_fingerprint'] ?? '') === $payloadFingerprint) {
                    return [
                        'deliver' => false,
                        'reason' => 'Evidence policy on-change skipped delivery because payload fingerprint is unchanged.',
                    ];
                }
            }
        }

        return [
            'deliver' => true,
            'reason' => 'Evidence delivery allowed by policy.',
        ];
    }

    private function normalizeEvidencePolicy(string $policy): string
    {
        return in_array($policy, ['always', 'on-fail', 'on-change'], true)
            ? $policy
            : 'always';
    }

    private function normalizeCheckOutcome(string $outcome): string
    {
        return in_array($outcome, ['pass', 'fail', 'warn', 'error', 'not-applicable'], true)
            ? $outcome
            : 'error';
    }

    private function normalizeCheckSeverity(string $severity): string
    {
        return in_array($severity, ['info', 'low', 'medium', 'high', 'critical'], true)
            ? $severity
            : 'medium';
    }

    private function checkOutcomeForStatus(string $status): string
    {
        return match ($status) {
            'success' => 'pass',
            'skipped' => 'not-applicable',
            'failed' => 'fail',
            default => 'error',
        };
    }

    private function checkSeverityForStatus(string $status): string
    {
        return match ($status) {
            'success', 'skipped' => 'info',
            'failed' => 'medium',
            default => 'high',
        };
    }

    private function resolveCheckResultScopeId(string $packScopeId, string $mappingScopeId, string $targetScopeId): ?string
    {
        if ($targetScopeId !== '') {
            return $targetScopeId;
        }

        if ($mappingScopeId !== '') {
            return $mappingScopeId;
        }

        return $packScopeId !== '' ? $packScopeId : null;
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function mappingRunsInRuntime(array $mapping): bool
    {
        $executionMode = (string) ($mapping['execution_mode'] ?? 'both');

        return in_array($executionMode, ['both', 'runtime-only'], true);
    }
}
