<?php

namespace PymeSec\Plugins\EvidenceManagement;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;

class EvidenceManagementRepository
{
    public function __construct(
        private readonly ArtifactServiceInterface $artifacts,
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('evidence_records')
            ->where('organization_id', $organizationId)
            ->orderByDesc('updated_at')
            ->orderBy('title');

        if (is_string($scopeId) && $scopeId !== '') {
            $query->where(function ($nested) use ($scopeId): void {
                $nested->whereNull('scope_id')
                    ->orWhere('scope_id', $scopeId);
            });
        }

        $records = $query->get();

        return array_map(fn (object $record): array => $this->mapEvidence($record), $records->all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $evidenceId): ?array
    {
        $record = DB::table('evidence_records')->where('id', $evidenceId)->first();

        return $record !== null ? $this->mapEvidence($record) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        array $data,
        ?UploadedFile $artifactFile,
        ?string $principalId,
        ?string $membershipId,
    ): array {
        $evidenceId = $this->nextEvidenceId((string) $data['title']);
        $artifact = $this->resolveArtifact($evidenceId, $data, $artifactFile, $principalId, $membershipId);

        DB::table('evidence_records')->insert([
            'id' => $evidenceId,
            'organization_id' => (string) $data['organization_id'],
            'scope_id' => $this->nullableString($data['scope_id'] ?? null),
            'artifact_id' => $artifact['id'],
            'title' => (string) $data['title'],
            'summary' => (string) ($data['summary'] ?? ''),
            'evidence_kind' => (string) $data['evidence_kind'],
            'status' => (string) $data['status'],
            'valid_from' => $this->nullableString($data['valid_from'] ?? null),
            'valid_until' => $this->nullableString($data['valid_until'] ?? null),
            'review_due_on' => $this->nullableString($data['review_due_on'] ?? null),
            'validated_at' => $this->nullableString($data['validated_at'] ?? null),
            'validated_by_principal_id' => $this->nullableString($data['validated_by_principal_id'] ?? null),
            'validation_notes' => $this->nullableString($data['validation_notes'] ?? null),
            'created_by_principal_id' => $principalId,
            'updated_by_principal_id' => $principalId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->syncLinks($evidenceId, (string) $data['organization_id'], $this->normalizeLinkTargets($data['link_targets'] ?? []));

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.evidence-management.evidence.created',
            outcome: 'success',
            originComponent: 'evidence-management',
            principalId: $principalId,
            membershipId: $membershipId,
            organizationId: (string) $data['organization_id'],
            scopeId: $this->nullableString($data['scope_id'] ?? null),
            targetType: 'evidence_record',
            targetId: $evidenceId,
            summary: [
                'artifact_id' => $artifact['id'],
                'title' => (string) $data['title'],
                'status' => (string) $data['status'],
                'evidence_kind' => (string) $data['evidence_kind'],
            ],
            executionOrigin: 'evidence-management',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.evidence-management.evidence.created',
            originComponent: 'evidence-management',
            organizationId: (string) $data['organization_id'],
            scopeId: $this->nullableString($data['scope_id'] ?? null),
            payload: [
                'evidence_id' => $evidenceId,
                'artifact_id' => $artifact['id'],
            ],
        ));

        return $this->find($evidenceId) ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function update(
        string $evidenceId,
        array $data,
        ?UploadedFile $artifactFile,
        ?string $principalId,
        ?string $membershipId,
    ): ?array {
        $existing = $this->find($evidenceId);

        if ($existing === null) {
            return null;
        }

        $artifactId = (string) ($existing['artifact']['id'] ?? '');

        if ($artifactFile instanceof UploadedFile || $this->nullableString($data['existing_artifact_id'] ?? null) !== null) {
            $artifact = $this->resolveArtifact($evidenceId, $data, $artifactFile, $principalId, $membershipId);
            $artifactId = $artifact['id'];
        }

        DB::table('evidence_records')
            ->where('id', $evidenceId)
            ->update([
                'scope_id' => $this->nullableString($data['scope_id'] ?? null),
                'artifact_id' => $artifactId,
                'title' => (string) $data['title'],
                'summary' => (string) ($data['summary'] ?? ''),
                'evidence_kind' => (string) $data['evidence_kind'],
                'status' => (string) $data['status'],
                'valid_from' => $this->nullableString($data['valid_from'] ?? null),
                'valid_until' => $this->nullableString($data['valid_until'] ?? null),
                'review_due_on' => $this->nullableString($data['review_due_on'] ?? null),
                'validated_at' => $this->nullableString($data['validated_at'] ?? null),
                'validated_by_principal_id' => $this->nullableString($data['validated_by_principal_id'] ?? null),
                'validation_notes' => $this->nullableString($data['validation_notes'] ?? null),
                'updated_by_principal_id' => $principalId,
                'updated_at' => now(),
            ]);

        $this->syncLinks($evidenceId, (string) $data['organization_id'], $this->normalizeLinkTargets($data['link_targets'] ?? []));

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.evidence-management.evidence.updated',
            outcome: 'success',
            originComponent: 'evidence-management',
            principalId: $principalId,
            membershipId: $membershipId,
            organizationId: (string) $data['organization_id'],
            scopeId: $this->nullableString($data['scope_id'] ?? null),
            targetType: 'evidence_record',
            targetId: $evidenceId,
            summary: [
                'artifact_id' => $artifactId,
                'title' => (string) $data['title'],
                'status' => (string) $data['status'],
                'evidence_kind' => (string) $data['evidence_kind'],
            ],
            executionOrigin: 'evidence-management',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.evidence-management.evidence.updated',
            originComponent: 'evidence-management',
            organizationId: (string) $data['organization_id'],
            scopeId: $this->nullableString($data['scope_id'] ?? null),
            payload: [
                'evidence_id' => $evidenceId,
                'artifact_id' => $artifactId,
            ],
        ));

        return $this->find($evidenceId);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function artifactOptions(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('artifacts')
            ->where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->limit(25);

        if (is_string($scopeId) && $scopeId !== '') {
            $query->where(function ($nested) use ($scopeId): void {
                $nested->whereNull('scope_id')
                    ->orWhere('scope_id', $scopeId);
            });
        }

        return $query->get(['id', 'label', 'original_filename', 'artifact_type'])
            ->map(static fn (object $artifact): array => [
                'id' => (string) $artifact->id,
                'label' => sprintf(
                    '%s · %s · %s',
                    (string) $artifact->label,
                    (string) $artifact->original_filename,
                    (string) $artifact->artifact_type
                ),
            ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function linkOptions(string $organizationId, ?string $scopeId = null): array
    {
        $options = [];

        foreach ($this->linkSourceDefinitions() as $definition) {
            if (! Schema::hasTable($definition['table'])) {
                continue;
            }

            $query = DB::table($definition['table'])
                ->where('organization_id', $organizationId)
                ->orderBy($definition['label_column']);

            if (is_string($scopeId) && $scopeId !== '' && $definition['scope_column'] !== null) {
                $scopeColumn = $definition['scope_column'];
                $query->where(function ($nested) use ($scopeColumn, $scopeId): void {
                    $nested->whereNull($scopeColumn)
                        ->orWhere($scopeColumn, $scopeId);
                });
            }

            $rows = $query->get(['id', $definition['label_column']]);

            if ($rows->isEmpty()) {
                continue;
            }

            $options[] = [
                'label' => $definition['group_label'],
                'items' => $rows->map(function (object $row) use ($definition): array {
                    $labelColumn = $definition['label_column'];

                    return [
                        'value' => $definition['domain_type'].':'.(string) $row->id,
                        'label' => (string) $row->{$labelColumn},
                    ];
                })->all(),
            ];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEvidence(object $record): array
    {
        $artifact = DB::table('artifacts')->where('id', (string) $record->artifact_id)->first();

        return [
            'id' => (string) $record->id,
            'organization_id' => (string) $record->organization_id,
            'scope_id' => is_string($record->scope_id) ? $record->scope_id : '',
            'artifact' => $artifact !== null ? [
                'id' => (string) $artifact->id,
                'label' => (string) $artifact->label,
                'artifact_type' => (string) $artifact->artifact_type,
                'original_filename' => (string) $artifact->original_filename,
                'media_type' => (string) $artifact->media_type,
                'size_bytes' => (int) $artifact->size_bytes,
                'sha256' => (string) $artifact->sha256,
                'created_at' => (string) $artifact->created_at,
            ] : null,
            'title' => (string) $record->title,
            'summary' => (string) $record->summary,
            'evidence_kind' => (string) $record->evidence_kind,
            'status' => (string) $record->status,
            'valid_from' => is_string($record->valid_from) ? $record->valid_from : '',
            'valid_until' => is_string($record->valid_until) ? $record->valid_until : '',
            'review_due_on' => is_string($record->review_due_on) ? $record->review_due_on : '',
            'validated_at' => is_string($record->validated_at) ? $record->validated_at : '',
            'validated_by_principal_id' => is_string($record->validated_by_principal_id) ? $record->validated_by_principal_id : '',
            'validation_notes' => is_string($record->validation_notes) ? $record->validation_notes : '',
            'created_by_principal_id' => is_string($record->created_by_principal_id) ? $record->created_by_principal_id : '',
            'updated_by_principal_id' => is_string($record->updated_by_principal_id) ? $record->updated_by_principal_id : '',
            'created_at' => (string) $record->created_at,
            'updated_at' => (string) $record->updated_at,
            'links' => $this->linksForEvidence((string) $record->id),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function linksForEvidence(string $evidenceId): array
    {
        return DB::table('evidence_record_links')
            ->where('evidence_id', $evidenceId)
            ->orderBy('domain_type')
            ->orderBy('domain_label')
            ->get()
            ->map(static fn (object $link): array => [
                'id' => (string) $link->id,
                'domain_type' => (string) $link->domain_type,
                'domain_id' => (string) $link->domain_id,
                'domain_label' => (string) $link->domain_label,
                'organization_id' => (string) $link->organization_id,
                'scope_id' => is_string($link->scope_id) ? $link->scope_id : '',
            ])->all();
    }

    /**
     * @param  array<int, string>  $linkTargets
     * @return array<int, array<string, string>>
     */
    private function normalizeLinkTargets(array $linkTargets): array
    {
        $normalized = [];

        foreach ($linkTargets as $target) {
            if (! is_string($target) || ! str_contains($target, ':')) {
                continue;
            }

            [$domainType, $domainId] = explode(':', $target, 2);
            $domainType = trim($domainType);
            $domainId = trim($domainId);

            if ($domainType === '' || $domainId === '') {
                continue;
            }

            $normalized[] = [
                'domain_type' => $domainType,
                'domain_id' => $domainId,
            ];
        }

        return array_values(array_unique($normalized, SORT_REGULAR));
    }

    /**
     * @param  array<int, array<string, string>>  $links
     */
    private function syncLinks(string $evidenceId, string $organizationId, array $links): void
    {
        DB::table('evidence_record_links')->where('evidence_id', $evidenceId)->delete();

        foreach ($links as $link) {
            $resolved = $this->resolveDomainTarget($organizationId, $link['domain_type'], $link['domain_id']);

            if ($resolved === null) {
                continue;
            }

            DB::table('evidence_record_links')->insert([
                'id' => (string) Str::ulid(),
                'evidence_id' => $evidenceId,
                'domain_type' => $resolved['domain_type'],
                'domain_id' => $resolved['domain_id'],
                'domain_label' => $resolved['domain_label'],
                'organization_id' => $organizationId,
                'scope_id' => $resolved['scope_id'],
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, string>|null
     */
    private function resolveDomainTarget(string $organizationId, string $domainType, string $domainId): ?array
    {
        foreach ($this->linkSourceDefinitions() as $definition) {
            if ($definition['domain_type'] !== $domainType || ! Schema::hasTable($definition['table'])) {
                continue;
            }

            $query = DB::table($definition['table'])
                ->where('organization_id', $organizationId)
                ->where('id', $domainId);

            $columns = ['id', $definition['label_column']];

            if ($definition['scope_column'] !== null) {
                $columns[] = $definition['scope_column'];
            }

            $row = $query->first($columns);

            if ($row === null) {
                return null;
            }

            $labelColumn = $definition['label_column'];
            $scopeColumn = $definition['scope_column'];

            return [
                'domain_type' => $definition['domain_type'],
                'domain_id' => (string) $row->id,
                'domain_label' => (string) $row->{$labelColumn},
                'scope_id' => $scopeColumn !== null && is_string($row->{$scopeColumn} ?? null) ? $row->{$scopeColumn} : null,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveArtifact(
        string $evidenceId,
        array $data,
        ?UploadedFile $artifactFile,
        ?string $principalId,
        ?string $membershipId,
    ): array {
        $existingArtifactId = $this->nullableString($data['existing_artifact_id'] ?? null);

        if (is_string($existingArtifactId)) {
            $artifact = DB::table('artifacts')
                ->where('id', $existingArtifactId)
                ->where('organization_id', (string) $data['organization_id'])
                ->first();

            if ($artifact === null) {
                throw new \RuntimeException('Selected artifact could not be found in this organization.');
            }

            return [
                'id' => (string) $artifact->id,
            ];
        }

        if (! $artifactFile instanceof UploadedFile) {
            throw new \RuntimeException('A file or existing artifact is required to create evidence.');
        }

        $artifact = $this->artifacts->store(new ArtifactUploadData(
            ownerComponent: 'evidence-management',
            subjectType: 'evidence-record',
            subjectId: $evidenceId,
            artifactType: (string) $data['evidence_kind'],
            label: (string) $data['title'],
            file: $artifactFile,
            principalId: $principalId,
            membershipId: $membershipId,
            organizationId: (string) $data['organization_id'],
            scopeId: $this->nullableString($data['scope_id'] ?? null),
            metadata: [
                'plugin' => 'evidence-management',
                'evidence_id' => $evidenceId,
                'status' => (string) $data['status'],
            ],
            executionOrigin: 'evidence-management',
        ));

        return $artifact->toArray();
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function nextEvidenceId(string $title): string
    {
        $base = 'evidence-'.Str::slug($title);
        $candidate = $base !== 'evidence-' ? $base : 'evidence-'.Str::lower(Str::ulid());

        while (DB::table('evidence_records')->where('id', $candidate)->exists()) {
            $candidate = $base.'-'.Str::lower(Str::random(4));
        }

        return $candidate;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function linkSourceDefinitions(): array
    {
        return [
            ['domain_type' => 'asset', 'table' => 'assets', 'label_column' => 'name', 'scope_column' => 'scope_id', 'group_label' => 'Assets'],
            ['domain_type' => 'control', 'table' => 'controls', 'label_column' => 'name', 'scope_column' => 'scope_id', 'group_label' => 'Controls'],
            ['domain_type' => 'risk', 'table' => 'risks', 'label_column' => 'title', 'scope_column' => 'scope_id', 'group_label' => 'Risks'],
            ['domain_type' => 'finding', 'table' => 'findings', 'label_column' => 'title', 'scope_column' => 'scope_id', 'group_label' => 'Findings'],
            ['domain_type' => 'policy', 'table' => 'policies', 'label_column' => 'title', 'scope_column' => 'scope_id', 'group_label' => 'Policies'],
            ['domain_type' => 'policy-exception', 'table' => 'policy_exceptions', 'label_column' => 'title', 'scope_column' => 'scope_id', 'group_label' => 'Exceptions'],
            ['domain_type' => 'data-flow', 'table' => 'data_flows', 'label_column' => 'title', 'scope_column' => 'scope_id', 'group_label' => 'Data flows'],
            ['domain_type' => 'processing-activity', 'table' => 'privacy_processing_activities', 'label_column' => 'title', 'scope_column' => 'scope_id', 'group_label' => 'Processing activities'],
            ['domain_type' => 'continuity-service', 'table' => 'continuity_services', 'label_column' => 'title', 'scope_column' => 'scope_id', 'group_label' => 'Continuity services'],
            ['domain_type' => 'recovery-plan', 'table' => 'continuity_recovery_plans', 'label_column' => 'title', 'scope_column' => 'scope_id', 'group_label' => 'Recovery plans'],
            ['domain_type' => 'assessment', 'table' => 'assessment_campaigns', 'label_column' => 'title', 'scope_column' => 'scope_id', 'group_label' => 'Assessments'],
        ];
    }
}
