<?php

namespace PymeSec\Plugins\EvidenceManagement;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\Notifications\Contracts\NotificationServiceInterface;

class EvidenceManagementRepository
{
    public function __construct(
        private readonly ArtifactServiceInterface $artifacts,
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
        private readonly NotificationServiceInterface $notifications,
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
                'review_reminder_sent_at' => $this->shouldResetReminderTimestamp(
                    $existing['review_due_on'] ?? '',
                    $this->nullableString($data['review_due_on'] ?? null),
                    $existing['review_reminder_sent_at'] ?? '',
                ) ? null : $this->nullableString($existing['review_reminder_sent_at'] ?? null),
                'expiry_reminder_sent_at' => $this->shouldResetReminderTimestamp(
                    $existing['valid_until'] ?? '',
                    $this->nullableString($data['valid_until'] ?? null),
                    $existing['expiry_reminder_sent_at'] ?? '',
                ) ? null : $this->nullableString($existing['expiry_reminder_sent_at'] ?? null),
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
     * @return array{record: array<string, mixed>, created: bool}|null
     */
    public function promoteArtifact(
        string $artifactId,
        string $organizationId,
        ?string $scopeId,
        ?string $principalId,
        ?string $membershipId,
    ): ?array {
        $existingEvidenceId = $this->evidenceIdForArtifact($artifactId);

        if (is_string($existingEvidenceId)) {
            $existing = $this->find($existingEvidenceId);

            return $existing !== null ? ['record' => $existing, 'created' => false] : null;
        }

        $artifact = DB::table('artifacts')
            ->where('id', $artifactId)
            ->where('organization_id', $organizationId)
            ->first();

        if ($artifact === null) {
            return null;
        }

        if (is_string($scopeId) && $scopeId !== '' && is_string($artifact->scope_id ?? null) && $artifact->scope_id !== $scopeId) {
            return null;
        }

        $title = $this->suggestedTitleForArtifact($artifact);
        $evidenceId = $this->nextEvidenceId($title);
        $summary = $this->suggestedSummaryForArtifact($artifact, $organizationId);
        $kind = $this->normalizeEvidenceKind((string) $artifact->artifact_type);
        $resolvedScopeId = is_string($artifact->scope_id ?? null) && $artifact->scope_id !== ''
            ? (string) $artifact->scope_id
            : $scopeId;
        $links = $this->inferLinkTargetsFromArtifact($artifact, $organizationId);

        DB::table('evidence_records')->insert([
            'id' => $evidenceId,
            'organization_id' => $organizationId,
            'scope_id' => $resolvedScopeId,
            'artifact_id' => (string) $artifact->id,
            'title' => $title,
            'summary' => $summary,
            'evidence_kind' => $kind,
            'status' => 'active',
            'valid_from' => now()->toDateString(),
            'valid_until' => null,
            'review_due_on' => now()->addDays(90)->toDateString(),
            'validated_at' => null,
            'validated_by_principal_id' => null,
            'validation_notes' => null,
            'created_by_principal_id' => $principalId,
            'updated_by_principal_id' => $principalId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->syncLinks($evidenceId, $organizationId, $links);

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.evidence-management.evidence.promoted',
            outcome: 'success',
            originComponent: 'evidence-management',
            principalId: $principalId,
            membershipId: $membershipId,
            organizationId: $organizationId,
            scopeId: $resolvedScopeId,
            targetType: 'evidence_record',
            targetId: $evidenceId,
            summary: [
                'artifact_id' => (string) $artifact->id,
                'source_owner_component' => (string) $artifact->owner_component,
                'source_subject_type' => (string) $artifact->subject_type,
                'source_subject_id' => (string) $artifact->subject_id,
                'link_count' => count($links),
            ],
            executionOrigin: 'evidence-management',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.evidence-management.evidence.promoted',
            originComponent: 'evidence-management',
            organizationId: $organizationId,
            scopeId: $resolvedScopeId,
            payload: [
                'evidence_id' => $evidenceId,
                'artifact_id' => (string) $artifact->id,
                'source_subject_type' => (string) $artifact->subject_type,
                'source_subject_id' => (string) $artifact->subject_id,
            ],
        ));

        $record = $this->find($evidenceId);

        return $record !== null ? ['record' => $record, 'created' => true] : null;
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
    public function promotionCandidates(string $organizationId, ?string $scopeId = null, int $limit = 12): array
    {
        $query = DB::table('artifacts')
            ->where('organization_id', $organizationId)
            ->whereNotExists(function ($nested): void {
                $nested->select(DB::raw(1))
                    ->from('evidence_records')
                    ->whereColumn('evidence_records.artifact_id', 'artifacts.id');
            })
            ->orderByDesc('created_at')
            ->limit(max(1, min($limit, 50)));

        if (is_string($scopeId) && $scopeId !== '') {
            $query->where(function ($nested) use ($scopeId): void {
                $nested->whereNull('scope_id')
                    ->orWhere('scope_id', $scopeId);
            });
        }

        return $query->get()->map(function (object $artifact) use ($organizationId): array {
            $source = $this->mapArtifactSource($artifact, $organizationId);
            $inferredLinks = $this->inferLinkTargetsFromArtifact($artifact, $organizationId);

            return [
                'id' => (string) $artifact->id,
                'label' => (string) $artifact->label,
                'original_filename' => (string) $artifact->original_filename,
                'artifact_type' => (string) $artifact->artifact_type,
                'owner_component' => (string) $artifact->owner_component,
                'subject_type' => (string) $artifact->subject_type,
                'subject_id' => (string) $artifact->subject_id,
                'scope_id' => is_string($artifact->scope_id ?? null) ? $artifact->scope_id : '',
                'created_at' => (string) $artifact->created_at,
                'suggested_title' => $this->suggestedTitleForArtifact($artifact),
                'suggested_summary' => $this->suggestedSummaryForArtifact($artifact, $organizationId),
                'suggested_kind' => $this->normalizeEvidenceKind((string) $artifact->artifact_type),
                'source' => $source,
                'suggested_links' => array_map(
                    fn (array $link): array => $this->resolveDomainTarget($organizationId, $link['domain_type'], $link['domain_id']) ?? [
                        'domain_type' => $link['domain_type'],
                        'domain_id' => $link['domain_id'],
                        'domain_label' => $link['domain_id'],
                        'scope_id' => null,
                    ],
                    $inferredLinks,
                ),
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function reviewQueue(string $organizationId, ?string $scopeId = null, int $limit = 12): array
    {
        $today = now()->toDateString();
        $windowEnd = now()->addDays(30)->toDateString();

        $query = DB::table('evidence_records')
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['active', 'approved'])
            ->where(function ($nested) use ($today, $windowEnd): void {
                $nested->where(function ($query) use ($today, $windowEnd): void {
                    $query->whereNotNull('review_due_on')
                        ->where('review_due_on', '<=', $windowEnd);
                })->orWhere(function ($query) use ($today, $windowEnd): void {
                    $query->whereNotNull('valid_until')
                        ->where('valid_until', '<=', $windowEnd)
                        ->where('valid_until', '>=', $today);
                });
            })
            ->orderBy('review_due_on')
            ->orderBy('valid_until')
            ->limit(max(1, min($limit, 50)));

        if (is_string($scopeId) && $scopeId !== '') {
            $query->where(function ($nested) use ($scopeId): void {
                $nested->whereNull('scope_id')
                    ->orWhere('scope_id', $scopeId);
            });
        }

        return $query->get()->map(function (object $record): array {
            $mapped = $this->mapEvidence($record);

            return [
                'id' => $mapped['id'],
                'title' => $mapped['title'],
                'status' => $mapped['status'],
                'scope_id' => $mapped['scope_id'],
                'review_due_on' => $mapped['review_due_on'],
                'valid_until' => $mapped['valid_until'],
                'review_reminder_sent_at' => $mapped['review_reminder_sent_at'],
                'expiry_reminder_sent_at' => $mapped['expiry_reminder_sent_at'],
                'needs_review_reminder' => $mapped['review_due_on'] !== '' && $mapped['review_reminder_sent_at'] === '',
                'needs_expiry_reminder' => $mapped['valid_until'] !== '' && $mapped['expiry_reminder_sent_at'] === '',
            ];
        })->all();
    }

    public function queueDueReminders(
        ?string $organizationId = null,
        ?string $scopeId = null,
        ?string $principalId = null,
        ?string $membershipId = null,
    ): int {
        $count = 0;
        $today = now()->toDateString();
        $windowEnd = now()->addDays(14)->toDateString();

        $reviewQuery = DB::table('evidence_records')
            ->whereIn('status', ['active', 'approved'])
            ->whereNotNull('review_due_on')
            ->where('review_due_on', '<=', $windowEnd)
            ->whereNull('review_reminder_sent_at');

        if (is_string($organizationId) && $organizationId !== '') {
            $reviewQuery->where('organization_id', $organizationId);
        }

        if (is_string($scopeId) && $scopeId !== '') {
            $reviewQuery->where(function ($nested) use ($scopeId): void {
                $nested->whereNull('scope_id')
                    ->orWhere('scope_id', $scopeId);
            });
        }

        foreach ($reviewQuery->get() as $record) {
            if (! $this->queueReminderForRecord($record, 'review-due', $principalId, $membershipId)) {
                continue;
            }

            DB::table('evidence_records')
                ->where('id', (string) $record->id)
                ->update([
                    'review_reminder_sent_at' => now()->toDateTimeString(),
                    'updated_at' => now(),
                ]);

            $count++;
        }

        $expiryQuery = DB::table('evidence_records')
            ->whereIn('status', ['active', 'approved'])
            ->whereNotNull('valid_until')
            ->where('valid_until', '>=', $today)
            ->where('valid_until', '<=', $windowEnd)
            ->whereNull('expiry_reminder_sent_at');

        if (is_string($organizationId) && $organizationId !== '') {
            $expiryQuery->where('organization_id', $organizationId);
        }

        if (is_string($scopeId) && $scopeId !== '') {
            $expiryQuery->where(function ($nested) use ($scopeId): void {
                $nested->whereNull('scope_id')
                    ->orWhere('scope_id', $scopeId);
            });
        }

        foreach ($expiryQuery->get() as $record) {
            if (! $this->queueReminderForRecord($record, 'expiry-soon', $principalId, $membershipId)) {
                continue;
            }

            DB::table('evidence_records')
                ->where('id', (string) $record->id)
                ->update([
                    'expiry_reminder_sent_at' => now()->toDateTimeString(),
                    'updated_at' => now(),
                ]);

            $count++;
        }

        return $count;
    }

    public function queueReminder(
        string $evidenceId,
        string $type,
        ?string $principalId = null,
        ?string $membershipId = null,
    ): bool {
        $record = DB::table('evidence_records')->where('id', $evidenceId)->first();

        if ($record === null) {
            return false;
        }

        if ($type === 'review-due') {
            if (! is_string($record->review_due_on ?? null) || $record->review_due_on === '') {
                return false;
            }

            if (! $this->queueReminderForRecord($record, $type, $principalId, $membershipId)) {
                return false;
            }

            DB::table('evidence_records')
                ->where('id', $evidenceId)
                ->update([
                    'review_reminder_sent_at' => now()->toDateTimeString(),
                    'updated_at' => now(),
                ]);

            return true;
        }

        if ($type === 'expiry-soon') {
            if (! is_string($record->valid_until ?? null) || $record->valid_until === '') {
                return false;
            }

            if (! $this->queueReminderForRecord($record, $type, $principalId, $membershipId)) {
                return false;
            }

            DB::table('evidence_records')
                ->where('id', $evidenceId)
                ->update([
                    'expiry_reminder_sent_at' => now()->toDateTimeString(),
                    'updated_at' => now(),
                ]);

            return true;
        }

        return false;
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
        $artifactExists = $artifact !== null
            && Storage::disk((string) $artifact->disk)->exists((string) $artifact->storage_path);
        $artifactPreviewable = $artifactExists && $this->isPreviewableArtifact((string) $artifact->media_type);

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
                'disk' => (string) $artifact->disk,
                'storage_path' => (string) $artifact->storage_path,
                'exists' => $artifactExists,
                'previewable' => $artifactPreviewable,
            ] : null,
            'source' => $artifact !== null ? $this->mapArtifactSource($artifact, (string) $record->organization_id) : null,
            'title' => (string) $record->title,
            'summary' => (string) $record->summary,
            'evidence_kind' => (string) $record->evidence_kind,
            'status' => (string) $record->status,
            'valid_from' => is_string($record->valid_from) ? $record->valid_from : '',
            'valid_until' => is_string($record->valid_until) ? $record->valid_until : '',
            'review_due_on' => is_string($record->review_due_on) ? $record->review_due_on : '',
            'review_reminder_sent_at' => is_string($record->review_reminder_sent_at ?? null) ? $record->review_reminder_sent_at : '',
            'validated_at' => is_string($record->validated_at) ? $record->validated_at : '',
            'validated_by_principal_id' => is_string($record->validated_by_principal_id) ? $record->validated_by_principal_id : '',
            'validation_notes' => is_string($record->validation_notes) ? $record->validation_notes : '',
            'expiry_reminder_sent_at' => is_string($record->expiry_reminder_sent_at ?? null) ? $record->expiry_reminder_sent_at : '',
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

    private function evidenceIdForArtifact(string $artifactId): ?string
    {
        $evidenceId = DB::table('evidence_records')
            ->where('artifact_id', $artifactId)
            ->value('id');

        return is_string($evidenceId) && $evidenceId !== '' ? $evidenceId : null;
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

    private function shouldResetReminderTimestamp(mixed $previousDate, ?string $nextDate, mixed $reminderTimestamp): bool
    {
        if (! is_string($reminderTimestamp) || $reminderTimestamp === '') {
            return false;
        }

        $previous = is_string($previousDate) ? $previousDate : '';
        $next = is_string($nextDate) ? $nextDate : '';

        return $previous !== $next;
    }

    private function queueReminderForRecord(
        object $record,
        string $type,
        ?string $principalId = null,
        ?string $membershipId = null,
    ): bool {
        $recipientPrincipalId = is_string($record->updated_by_principal_id ?? null) && $record->updated_by_principal_id !== ''
            ? (string) $record->updated_by_principal_id
            : (is_string($record->created_by_principal_id ?? null) && $record->created_by_principal_id !== ''
                ? (string) $record->created_by_principal_id
                : null);

        if ($recipientPrincipalId === null) {
            return false;
        }

        $reminderDate = $type === 'review-due'
            ? (string) $record->review_due_on
            : (string) $record->valid_until;

        $title = $type === 'review-due'
            ? sprintf('Evidence review due soon: %s', (string) $record->title)
            : sprintf('Evidence expires soon: %s', (string) $record->title);

        $body = $type === 'review-due'
            ? sprintf('Review "%s" before %s to keep the evidence current.', (string) $record->title, $reminderDate)
            : sprintf('Renew or replace "%s" before %s to avoid evidence gaps.', (string) $record->title, $reminderDate);

        $this->notifications->notify(
            type: 'plugin.evidence-management.'.$type,
            title: $title,
            body: $body,
            principalId: $recipientPrincipalId,
            functionalActorId: null,
            organizationId: is_string($record->organization_id ?? null) ? (string) $record->organization_id : null,
            scopeId: is_string($record->scope_id ?? null) ? (string) $record->scope_id : null,
            sourceEventName: 'plugin.evidence-management.reminder-queued',
            metadata: [
                'evidence_id' => (string) $record->id,
                'reminder_type' => $type,
                'due_on' => $reminderDate,
            ],
            deliverAt: now()->toDateTimeString(),
        );

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.evidence-management.reminder.queued',
            outcome: 'success',
            originComponent: 'evidence-management',
            principalId: $principalId,
            membershipId: $membershipId,
            organizationId: is_string($record->organization_id ?? null) ? (string) $record->organization_id : null,
            scopeId: is_string($record->scope_id ?? null) ? (string) $record->scope_id : null,
            targetType: 'evidence_record',
            targetId: (string) $record->id,
            summary: [
                'reminder_type' => $type,
                'recipient_principal_id' => $recipientPrincipalId,
                'due_on' => $reminderDate,
            ],
            executionOrigin: 'evidence-management',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.evidence-management.reminder.queued',
            originComponent: 'evidence-management',
            organizationId: is_string($record->organization_id ?? null) ? (string) $record->organization_id : null,
            scopeId: is_string($record->scope_id ?? null) ? (string) $record->scope_id : null,
            payload: [
                'evidence_id' => (string) $record->id,
                'recipient_principal_id' => $recipientPrincipalId,
                'reminder_type' => $type,
            ],
        ));

        return true;
    }

    private function isPreviewableArtifact(string $mediaType): bool
    {
        return str_starts_with($mediaType, 'text/')
            || str_starts_with($mediaType, 'image/')
            || $mediaType === 'application/pdf'
            || $mediaType === 'application/json';
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

    private function normalizeEvidenceKind(string $artifactType): string
    {
        return match ($artifactType) {
            'document',
            'workpaper',
            'snapshot',
            'report',
            'ticket',
            'log-export',
            'statement',
            'other' => $artifactType,
            'evidence' => 'document',
            default => 'document',
        };
    }

    private function suggestedTitleForArtifact(object $artifact): string
    {
        $label = trim((string) $artifact->label);

        if ($label !== '') {
            return $label;
        }

        $filename = pathinfo((string) $artifact->original_filename, PATHINFO_FILENAME);

        return $filename !== '' ? Str::headline((string) $filename) : 'Promoted evidence';
    }

    private function suggestedSummaryForArtifact(object $artifact, string $organizationId): string
    {
        $source = $this->mapArtifactSource($artifact, $organizationId);

        if (($source['label'] ?? '') !== '') {
            return sprintf(
                'Promoted from %s in %s.',
                strtolower((string) ($source['label'] ?? 'the workspace')),
                str_replace('-', ' ', (string) $artifact->owner_component),
            );
        }

        return sprintf(
            'Promoted from %s artifact %s.',
            str_replace('-', ' ', (string) $artifact->owner_component),
            (string) $artifact->original_filename,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mapArtifactSource(object $artifact, string $organizationId): array
    {
        $resolved = $this->resolveArtifactSourceTarget($artifact, $organizationId);

        return [
            'owner_component' => (string) $artifact->owner_component,
            'subject_type' => (string) $artifact->subject_type,
            'subject_id' => (string) $artifact->subject_id,
            'label' => $resolved['domain_label'] ?? (string) $artifact->label,
            'domain_type' => $resolved['domain_type'] ?? null,
            'domain_id' => $resolved['domain_id'] ?? null,
            'scope_id' => $resolved['scope_id'] ?? (is_string($artifact->scope_id ?? null) ? $artifact->scope_id : null),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function inferLinkTargetsFromArtifact(object $artifact, string $organizationId): array
    {
        $subjectType = (string) $artifact->subject_type;
        $subjectId = (string) $artifact->subject_id;
        $links = [];

        $direct = $this->resolveArtifactSourceTarget($artifact, $organizationId);

        if ($direct !== null) {
            $links[] = [
                'domain_type' => $direct['domain_type'],
                'domain_id' => $direct['domain_id'],
            ];
        }

        if ($subjectType === 'assessment-review' && Schema::hasTable('assessment_control_reviews')) {
            $review = DB::table('assessment_control_reviews')->where('id', $subjectId)->first();

            if ($review !== null) {
                $links[] = ['domain_type' => 'assessment', 'domain_id' => (string) $review->assessment_id];
                $links[] = ['domain_type' => 'control', 'domain_id' => (string) $review->control_id];

                if (is_string($review->linked_finding_id) && $review->linked_finding_id !== '') {
                    $links[] = ['domain_type' => 'finding', 'domain_id' => $review->linked_finding_id];
                }
            }
        }

        if ($subjectType === 'continuity-service' && Schema::hasTable('continuity_services')) {
            $service = DB::table('continuity_services')->where('id', $subjectId)->first();

            if ($service !== null) {
                if (is_string($service->linked_asset_id) && $service->linked_asset_id !== '') {
                    $links[] = ['domain_type' => 'asset', 'domain_id' => $service->linked_asset_id];
                }

                if (is_string($service->linked_risk_id) && $service->linked_risk_id !== '') {
                    $links[] = ['domain_type' => 'risk', 'domain_id' => $service->linked_risk_id];
                }
            }
        }

        if ($subjectType === 'continuity-plan' && Schema::hasTable('continuity_recovery_plans')) {
            $plan = DB::table('continuity_recovery_plans')->where('id', $subjectId)->first();

            if ($plan !== null) {
                $links[] = ['domain_type' => 'continuity-service', 'domain_id' => (string) $plan->service_id];

                if (is_string($plan->linked_finding_id) && $plan->linked_finding_id !== '') {
                    $links[] = ['domain_type' => 'finding', 'domain_id' => $plan->linked_finding_id];
                }

                if (is_string($plan->linked_policy_id) && $plan->linked_policy_id !== '') {
                    $links[] = ['domain_type' => 'policy', 'domain_id' => $plan->linked_policy_id];
                }
            }
        }

        return array_values(array_unique($links, SORT_REGULAR));
    }

    /**
     * @return array<string, string|null>|null
     */
    private function resolveArtifactSourceTarget(object $artifact, string $organizationId): ?array
    {
        return match ((string) $artifact->subject_type) {
            'asset' => $this->resolveDomainTarget($organizationId, 'asset', (string) $artifact->subject_id),
            'control' => $this->resolveDomainTarget($organizationId, 'control', (string) $artifact->subject_id),
            'risk' => $this->resolveDomainTarget($organizationId, 'risk', (string) $artifact->subject_id),
            'finding' => $this->resolveDomainTarget($organizationId, 'finding', (string) $artifact->subject_id),
            'policy' => $this->resolveDomainTarget($organizationId, 'policy', (string) $artifact->subject_id),
            'policy-exception' => $this->resolveDomainTarget($organizationId, 'policy-exception', (string) $artifact->subject_id),
            'privacy-data-flow' => $this->resolveDomainTarget($organizationId, 'data-flow', (string) $artifact->subject_id),
            'privacy-processing-activity' => $this->resolveDomainTarget($organizationId, 'processing-activity', (string) $artifact->subject_id),
            'continuity-service' => $this->resolveDomainTarget($organizationId, 'continuity-service', (string) $artifact->subject_id),
            'continuity-plan',
            'recovery-plan' => $this->resolveDomainTarget($organizationId, 'recovery-plan', (string) $artifact->subject_id),
            'assessment' => $this->resolveDomainTarget($organizationId, 'assessment', (string) $artifact->subject_id),
            'assessment-review' => $this->resolveAssessmentReviewTarget((string) $artifact->subject_id, $organizationId),
            default => null,
        };
    }

    /**
     * @return array<string, string|null>|null
     */
    private function resolveAssessmentReviewTarget(string $reviewId, string $organizationId): ?array
    {
        if (! Schema::hasTable('assessment_control_reviews') || ! Schema::hasTable('assessment_campaigns')) {
            return null;
        }

        $row = DB::table('assessment_control_reviews as reviews')
            ->join('assessment_campaigns as assessments', 'assessments.id', '=', 'reviews.assessment_id')
            ->where('reviews.id', $reviewId)
            ->where('reviews.organization_id', $organizationId)
            ->first([
                'assessments.id as assessment_id',
                'assessments.title as assessment_title',
                'reviews.scope_id',
            ]);

        if ($row === null) {
            return null;
        }

        return [
            'domain_type' => 'assessment',
            'domain_id' => (string) $row->assessment_id,
            'domain_label' => (string) $row->assessment_title,
            'scope_id' => is_string($row->scope_id ?? null) ? $row->scope_id : null,
        ];
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
