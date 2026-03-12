<?php

namespace PymeSec\Core\Tenancy;

class ScopeReference
{
    public function __construct(
        public readonly string $id,
        public readonly string $organizationId,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
        ];
    }
}
