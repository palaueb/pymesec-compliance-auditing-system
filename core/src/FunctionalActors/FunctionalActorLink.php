<?php

namespace PymeSec\Core\FunctionalActors;

class FunctionalActorLink
{
    public function __construct(
        public readonly string $id,
        public readonly string $principalId,
        public readonly string $functionalActorId,
        public readonly string $organizationId,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'principal_id' => $this->principalId,
            'functional_actor_id' => $this->functionalActorId,
            'organization_id' => $this->organizationId,
        ];
    }
}
