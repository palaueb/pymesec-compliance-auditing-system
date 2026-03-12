<?php

namespace PymeSec\Core\Artifacts;

use Illuminate\Http\UploadedFile;

class ArtifactUploadData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $ownerComponent,
        public readonly string $subjectType,
        public readonly string $subjectId,
        public readonly string $artifactType,
        public readonly string $label,
        public readonly UploadedFile $file,
        public readonly ?string $principalId = null,
        public readonly ?string $membershipId = null,
        public readonly ?string $organizationId = null,
        public readonly ?string $scopeId = null,
        public readonly array $metadata = [],
        public readonly string $executionOrigin = 'request',
    ) {}
}
