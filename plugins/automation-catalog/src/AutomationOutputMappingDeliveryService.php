<?php

namespace PymeSec\Plugins\AutomationCatalog;

use Illuminate\Http\UploadedFile;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Plugins\EvidenceManagement\EvidenceManagementRepository;
use Throwable;

class AutomationOutputMappingDeliveryService
{
    public function __construct(
        private readonly ArtifactServiceInterface $artifacts,
        private readonly EvidenceManagementRepository $evidence,
        private readonly WorkflowServiceInterface $workflows,
    ) {}

    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, mixed>  $data
     * @return array{status: string, message: string}
     */
    public function deliver(
        array $mapping,
        array $data,
        string $principalId,
        ?string $membershipId,
        string $organizationId,
        ?string $scopeId,
    ): array {
        if (($mapping['is_active'] ?? '0') !== '1') {
            return [
                'status' => 'failed',
                'message' => 'Mapping is inactive.',
            ];
        }

        return match ($mapping['mapping_kind'] ?? 'evidence-refresh') {
            'workflow-transition' => $this->deliverWorkflowTransition(
                mapping: $mapping,
                principalId: $principalId,
                membershipId: $membershipId,
                organizationId: $organizationId,
                scopeId: $scopeId,
            ),
            default => $this->deliverEvidenceRefresh(
                mapping: $mapping,
                data: $data,
                principalId: $principalId,
                membershipId: $membershipId,
                organizationId: $organizationId,
                scopeId: $scopeId,
            ),
        };
    }

    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, mixed>  $data
     * @return array{status: string, message: string}
     */
    private function deliverEvidenceRefresh(
        array $mapping,
        array $data,
        string $principalId,
        ?string $membershipId,
        string $organizationId,
        ?string $scopeId,
    ): array {
        if (($mapping['target_subject_type'] ?? '') === '' || ($mapping['target_subject_id'] ?? '') === '') {
            return [
                'status' => 'failed',
                'message' => 'Evidence mapping requires a target subject type and id.',
            ];
        }

        $artifactId = is_string($data['existing_artifact_id'] ?? null) && $data['existing_artifact_id'] !== ''
            ? (string) $data['existing_artifact_id']
            : null;

        if ($artifactId === null && ($data['output_file'] ?? null) instanceof UploadedFile) {
            $artifact = $this->artifacts->store(new ArtifactUploadData(
                ownerComponent: 'automation-catalog',
                subjectType: (string) $mapping['target_subject_type'],
                subjectId: (string) $mapping['target_subject_id'],
                artifactType: is_string($data['evidence_kind'] ?? null) && $data['evidence_kind'] !== ''
                    ? (string) $data['evidence_kind']
                    : 'report',
                label: sprintf('%s output', (string) $mapping['mapping_label']),
                file: $data['output_file'],
                principalId: $principalId !== '' ? $principalId : null,
                membershipId: $membershipId,
                organizationId: $organizationId,
                scopeId: $scopeId,
                metadata: [
                    'automation_pack_id' => $mapping['automation_pack_id'] ?? null,
                    'automation_output_mapping_id' => $mapping['id'] ?? null,
                ],
                executionOrigin: 'automation-catalog',
            ));

            $artifactId = $artifact->id;
        }

        if ($artifactId === null) {
            return [
                'status' => 'failed',
                'message' => 'Provide an output file or an existing artifact id.',
            ];
        }

        try {
            $promotion = $this->evidence->promoteArtifact(
                artifactId: $artifactId,
                organizationId: $organizationId,
                scopeId: $scopeId,
                principalId: $principalId !== '' ? $principalId : null,
                membershipId: $membershipId,
            );
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }

        if ($promotion === null || ! is_array($promotion['record'] ?? null)) {
            return [
                'status' => 'failed',
                'message' => 'Unable to promote automation output to evidence.',
            ];
        }

        $evidenceId = (string) (($promotion['record']['id'] ?? '') ?: '');

        return [
            'status' => 'success',
            'message' => ($promotion['created'] ?? false)
                ? sprintf('Evidence refreshed: %s.', $evidenceId)
                : sprintf('Artifact already mapped to evidence: %s.', $evidenceId),
        ];
    }

    /**
     * @param  array<string, string>  $mapping
     * @return array{status: string, message: string}
     */
    private function deliverWorkflowTransition(
        array $mapping,
        string $principalId,
        ?string $membershipId,
        string $organizationId,
        ?string $scopeId,
    ): array {
        if (
            ($mapping['workflow_key'] ?? '') === ''
            || ($mapping['transition_key'] ?? '') === ''
            || ($mapping['target_subject_type'] ?? '') === ''
            || ($mapping['target_subject_id'] ?? '') === ''
        ) {
            return [
                'status' => 'failed',
                'message' => 'Workflow mapping requires workflow key, transition key, and target subject.',
            ];
        }

        try {
            $record = $this->workflows->transition(
                workflowKey: (string) $mapping['workflow_key'],
                subjectType: (string) $mapping['target_subject_type'],
                subjectId: (string) $mapping['target_subject_id'],
                transitionKey: (string) $mapping['transition_key'],
                context: new WorkflowExecutionContext(
                    principal: new PrincipalReference(
                        id: $principalId !== '' ? $principalId : 'principal-org-a',
                        provider: 'automation-catalog',
                    ),
                    memberships: $membershipId !== null && $membershipId !== ''
                        ? [
                            new MembershipReference(
                                id: $membershipId,
                                principalId: $principalId !== '' ? $principalId : 'principal-org-a',
                                organizationId: $organizationId,
                            ),
                        ]
                        : [],
                    organizationId: $organizationId,
                    scopeId: $scopeId,
                    membershipId: $membershipId,
                ),
            );
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }

        return [
            'status' => 'success',
            'message' => sprintf(
                'Workflow transition applied: %s (%s -> %s).',
                $record->transitionKey,
                $record->fromState,
                $record->toState,
            ),
        ];
    }
}
