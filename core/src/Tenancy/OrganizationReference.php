<?php

namespace PymeSec\Core\Tenancy;

class OrganizationReference
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $defaultLocale,
        public readonly string $defaultTimezone,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'default_locale' => $this->defaultLocale,
            'default_timezone' => $this->defaultTimezone,
        ];
    }
}
