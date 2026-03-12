<?php

namespace PymeSec\Core\Events;

class PublicEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $name,
        public readonly string $originComponent,
        public readonly array $payload = [],
        public readonly ?string $organizationId = null,
        public readonly ?string $scopeId = null,
        public readonly ?string $publishedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'origin_component' => $this->originComponent,
            'organization_id' => $this->organizationId,
            'scope_id' => $this->scopeId,
            'payload' => $this->payload,
            'published_at' => $this->publishedAt,
        ];
    }
}
