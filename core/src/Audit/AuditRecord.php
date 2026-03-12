<?php

namespace PymeSec\Core\Audit;

class AuditRecord
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $correlation
     */
    public function __construct(
        public readonly string $id,
        public readonly string $eventType,
        public readonly string $outcome,
        public readonly string $originComponent,
        public readonly ?string $principalId,
        public readonly ?string $membershipId,
        public readonly ?string $organizationId,
        public readonly ?string $scopeId,
        public readonly ?string $targetType,
        public readonly ?string $targetId,
        public readonly array $summary,
        public readonly array $correlation,
        public readonly ?string $executionOrigin,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->eventType,
            'outcome' => $this->outcome,
            'origin_component' => $this->originComponent,
            'principal_id' => $this->principalId,
            'membership_id' => $this->membershipId,
            'organization_id' => $this->organizationId,
            'scope_id' => $this->scopeId,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'summary' => $this->summary,
            'correlation' => $this->correlation,
            'execution_origin' => $this->executionOrigin,
            'created_at' => $this->createdAt,
        ];
    }
}
