<?php

namespace PymeSec\Core\FunctionalActors;

class FunctionalActorReference
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $provider,
        public readonly string $kind,
        public readonly string $displayName,
        public readonly string $organizationId,
        public readonly ?string $scopeId = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'kind' => $this->kind,
            'display_name' => $this->displayName,
            'organization_id' => $this->organizationId,
            'scope_id' => $this->scopeId,
            'metadata' => $this->metadata,
        ];
    }
}
