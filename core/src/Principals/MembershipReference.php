<?php

namespace PymeSec\Core\Principals;

class MembershipReference
{
    /**
     * @param  array<int, string>  $roles
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $principalId,
        public readonly string $organizationId,
        public readonly array $roles = [],
        public readonly array $scopes = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'principal_id' => $this->principalId,
            'organization_id' => $this->organizationId,
            'roles' => $this->roles,
            'scopes' => $this->scopes,
        ];
    }
}
