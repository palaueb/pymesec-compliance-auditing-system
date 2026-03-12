<?php

namespace PymeSec\Core\Audit;

class AuditRecordData
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $correlation
     */
    public function __construct(
        public readonly string $eventType,
        public readonly string $outcome,
        public readonly string $originComponent,
        public readonly ?string $principalId = null,
        public readonly ?string $membershipId = null,
        public readonly ?string $organizationId = null,
        public readonly ?string $scopeId = null,
        public readonly ?string $targetType = null,
        public readonly ?string $targetId = null,
        public readonly array $summary = [],
        public readonly array $correlation = [],
        public readonly ?string $executionOrigin = null,
    ) {}
}
