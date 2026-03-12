<?php

namespace PymeSec\Core\FunctionalActors;

class FunctionalAssignment
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $functionalActorId,
        public readonly string $domainObjectType,
        public readonly string $domainObjectId,
        public readonly string $assignmentType,
        public readonly string $organizationId,
        public readonly ?string $scopeId = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'functional_actor_id' => $this->functionalActorId,
            'domain_object_type' => $this->domainObjectType,
            'domain_object_id' => $this->domainObjectId,
            'assignment_type' => $this->assignmentType,
            'organization_id' => $this->organizationId,
            'scope_id' => $this->scopeId,
            'metadata' => $this->metadata,
        ];
    }
}
