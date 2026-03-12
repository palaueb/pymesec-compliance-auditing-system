<?php

namespace PymeSec\Core\Artifacts\Contracts;

use PymeSec\Core\Artifacts\ArtifactRecord;
use PymeSec\Core\Artifacts\ArtifactUploadData;

interface ArtifactServiceInterface
{
    public function store(ArtifactUploadData $artifact): ArtifactRecord;

    /**
     * @return array<int, ArtifactRecord>
     */
    public function latest(int $limit = 50, array $filters = []): array;

    /**
     * @return array<int, ArtifactRecord>
     */
    public function forSubject(string $subjectType, string $subjectId, string $organizationId, ?string $scopeId = null, int $limit = 50): array;
}
