<?php

namespace PymeSec\Core\Artifacts;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;

class DatabaseArtifactService implements ArtifactServiceInterface
{
    public function __construct(
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
        private readonly ArtifactUploadFileTypeGuard $fileTypeGuard,
    ) {}

    public function store(ArtifactUploadData $artifact): ArtifactRecord
    {
        $id = (string) Str::ulid();
        $disk = (string) config('artifacts.disk', 'local');
        $prefix = trim((string) config('artifacts.path_prefix', 'artifacts'), '/');
        $originalFilename = $artifact->file->getClientOriginalName() ?: 'artifact.bin';
        $validatedFile = $this->fileTypeGuard->validate($artifact);
        $extension = $validatedFile['extension'];
        $filenameBase = pathinfo($originalFilename, PATHINFO_FILENAME);
        $safeFilenameBase = Str::slug($filenameBase !== '' ? $filenameBase : 'artifact');
        $storageFilename = $safeFilenameBase !== ''
            ? sprintf('%s-%s.%s', $id, $safeFilenameBase, $extension)
            : sprintf('%s.%s', $id, $extension);
        $segments = array_values(array_filter([
            $prefix,
            $artifact->ownerComponent,
            $artifact->organizationId ?? 'platform',
            $artifact->scopeId ?? '_',
            $artifact->subjectType,
            $artifact->subjectId,
        ], static fn (?string $value): bool => is_string($value) && $value !== ''));
        $storagePath = implode('/', $segments).'/'.$storageFilename;
        $contents = $artifact->file->get();

        Storage::disk($disk)->put($storagePath, $contents);

        $record = new ArtifactRecord(
            id: $id,
            ownerComponent: $artifact->ownerComponent,
            subjectType: $artifact->subjectType,
            subjectId: $artifact->subjectId,
            artifactType: $artifact->artifactType,
            label: $artifact->label,
            originalFilename: $originalFilename,
            mediaType: $validatedFile['media_type'],
            extension: $extension,
            sizeBytes: (int) ($artifact->file->getSize() ?? strlen($contents)),
            sha256: hash('sha256', $contents),
            disk: $disk,
            storagePath: $storagePath,
            principalId: $artifact->principalId,
            membershipId: $artifact->membershipId,
            organizationId: $artifact->organizationId,
            scopeId: $artifact->scopeId,
            metadata: $artifact->metadata,
            createdAt: now()->toDateTimeString(),
        );

        DB::table('artifacts')->insert([
            'id' => $record->id,
            'owner_component' => $record->ownerComponent,
            'subject_type' => $record->subjectType,
            'subject_id' => $record->subjectId,
            'artifact_type' => $record->artifactType,
            'label' => $record->label,
            'original_filename' => $record->originalFilename,
            'media_type' => $record->mediaType,
            'extension' => $record->extension,
            'size_bytes' => $record->sizeBytes,
            'sha256' => $record->sha256,
            'disk' => $record->disk,
            'storage_path' => $record->storagePath,
            'principal_id' => $record->principalId,
            'membership_id' => $record->membershipId,
            'organization_id' => $record->organizationId,
            'scope_id' => $record->scopeId,
            'metadata' => $this->encodeJson($record->metadata),
            'created_at' => $record->createdAt,
        ]);

        $this->audit->record(new AuditRecordData(
            eventType: 'core.artifacts.created',
            outcome: 'success',
            originComponent: 'core',
            principalId: $record->principalId,
            membershipId: $record->membershipId,
            organizationId: $record->organizationId,
            scopeId: $record->scopeId,
            targetType: 'artifact',
            targetId: $record->id,
            summary: [
                'owner_component' => $record->ownerComponent,
                'subject_type' => $record->subjectType,
                'subject_id' => $record->subjectId,
                'artifact_type' => $record->artifactType,
                'label' => $record->label,
                'original_filename' => $record->originalFilename,
            ],
            executionOrigin: $artifact->executionOrigin,
        ));

        $this->events->publish(new PublicEvent(
            name: 'core.artifacts.created',
            originComponent: 'core',
            organizationId: $record->organizationId,
            scopeId: $record->scopeId,
            payload: [
                'artifact_id' => $record->id,
                'owner_component' => $record->ownerComponent,
                'subject_type' => $record->subjectType,
                'subject_id' => $record->subjectId,
                'artifact_type' => $record->artifactType,
            ],
        ));

        return $record;
    }

    public function latest(int $limit = 50, array $filters = []): array
    {
        $query = DB::table('artifacts')->orderByDesc('created_at')->orderByDesc('id');

        foreach ([
            'owner_component',
            'subject_type',
            'subject_id',
            'artifact_type',
            'principal_id',
            'membership_id',
            'organization_id',
            'scope_id',
        ] as $field) {
            $value = $filters[$field] ?? null;

            if (is_string($value) && $value !== '') {
                $query->where($field, $value);
            }
        }

        return $query
            ->limit(max(1, min($limit, 500)))
            ->get()
            ->map(fn ($record): ArtifactRecord => $this->mapArtifact($record))
            ->all();
    }

    public function forSubject(string $subjectType, string $subjectId, string $organizationId, ?string $scopeId = null, int $limit = 50): array
    {
        return $this->latest($limit, array_filter([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
        ], static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }

    private function mapArtifact(object $record): ArtifactRecord
    {
        return new ArtifactRecord(
            id: (string) $record->id,
            ownerComponent: (string) $record->owner_component,
            subjectType: (string) $record->subject_type,
            subjectId: (string) $record->subject_id,
            artifactType: (string) $record->artifact_type,
            label: (string) $record->label,
            originalFilename: (string) $record->original_filename,
            mediaType: (string) $record->media_type,
            extension: (string) $record->extension,
            sizeBytes: (int) $record->size_bytes,
            sha256: (string) $record->sha256,
            disk: (string) $record->disk,
            storagePath: (string) $record->storage_path,
            principalId: is_string($record->principal_id) ? $record->principal_id : null,
            membershipId: is_string($record->membership_id) ? $record->membership_id : null,
            organizationId: is_string($record->organization_id) ? $record->organization_id : null,
            scopeId: is_string($record->scope_id) ? $record->scope_id : null,
            metadata: $this->decodeJson($record->metadata ?? null),
            createdAt: (string) $record->created_at,
        );
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
