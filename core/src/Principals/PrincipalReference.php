<?php

namespace PymeSec\Core\Principals;

class PrincipalReference
{
    public function __construct(
        public readonly string $id,
        public readonly string $provider,
        public readonly ?string $subject = null,
        public readonly ?string $displayName = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'subject' => $this->subject,
            'display_name' => $this->displayName,
        ];
    }
}
