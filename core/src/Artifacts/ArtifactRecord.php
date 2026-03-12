<?php

namespace PymeSec\Core\Artifacts;

class ArtifactRecord
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $ownerComponent,
        public readonly string $subjectType,
        public readonly string $subjectId,
        public readonly string $artifactType,
        public readonly string $label,
        public readonly string $originalFilename,
        public readonly string $mediaType,
        public readonly string $extension,
        public readonly int $sizeBytes,
        public readonly string $sha256,
        public readonly string $disk,
        public readonly string $storagePath,
        public readonly ?string $principalId,
        public readonly ?string $membershipId,
        public readonly ?string $organizationId,
        public readonly ?string $scopeId,
        public readonly array $metadata,
        public readonly string $createdAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'owner_component' => $this->ownerComponent,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'artifact_type' => $this->artifactType,
            'label' => $this->label,
            'original_filename' => $this->originalFilename,
            'media_type' => $this->mediaType,
            'extension' => $this->extension,
            'size_bytes' => $this->sizeBytes,
            'sha256' => $this->sha256,
            'disk' => $this->disk,
            'storage_path' => $this->storagePath,
            'principal_id' => $this->principalId,
            'membership_id' => $this->membershipId,
            'organization_id' => $this->organizationId,
            'scope_id' => $this->scopeId,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
        ];
    }
}
