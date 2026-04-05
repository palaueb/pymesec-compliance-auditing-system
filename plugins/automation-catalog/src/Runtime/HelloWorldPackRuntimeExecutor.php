<?php

namespace PymeSec\Plugins\AutomationCatalog\Runtime;

class HelloWorldPackRuntimeExecutor implements AutomationPackRuntimeExecutorInterface
{
    public function supports(array $pack): bool
    {
        return (string) ($pack['pack_key'] ?? '') === 'utility.hello-world';
    }

    public function generateEvidencePayload(array $pack, array $mapping, array $target, array $context): array
    {
        $subjectType = (string) ($target['subject_type'] ?? 'unknown-subject');
        $subjectId = (string) ($target['subject_id'] ?? 'unknown-id');
        $trigger = (string) ($context['trigger_mode'] ?? 'manual');
        $timestamp = (string) ($context['run_started_at'] ?? now()->toDateTimeString());
        $changeFingerprint = hash('sha256', implode('|', [
            'hello-world-v1',
            (string) ($pack['pack_key'] ?? 'utility.hello-world'),
            (string) ($mapping['mapping_label'] ?? 'runtime-mapping'),
            $subjectType,
            $subjectId,
        ]));

        return [
            'status' => 'success',
            'check_outcome' => 'pass',
            'severity' => 'info',
            'change_fingerprint' => $changeFingerprint,
            'artifact_type' => 'report',
            'label' => sprintf('Hello World check for %s:%s', $subjectType, $subjectId),
            'filename' => sprintf(
                'hello-world-%s-%s.txt',
                $subjectType,
                preg_replace('/[^a-zA-Z0-9_-]+/', '-', $subjectId) ?: 'subject'
            ),
            'content' => implode(PHP_EOL, [
                'Hello from the PymeSec automation runtime.',
                sprintf('Pack: %s', (string) ($pack['pack_key'] ?? 'utility.hello-world')),
                sprintf('Target: %s:%s', $subjectType, $subjectId),
                sprintf('Trigger: %s', $trigger),
                sprintf('Generated at: %s', $timestamp),
                'Status: pass',
            ]).PHP_EOL,
            'message' => sprintf('Hello World generated evidence for %s:%s.', $subjectType, $subjectId),
        ];
    }
}
