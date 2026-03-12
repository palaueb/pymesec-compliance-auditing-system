<?php

namespace PymeSec\Core\Tenancy;

use PymeSec\Core\Principals\MembershipReference;

class TenancyContext
{
    /**
     * @param  array<int, OrganizationReference>  $organizations
     * @param  array<int, ScopeReference>  $scopes
     * @param  array<int, MembershipReference>  $memberships
     */
    public function __construct(
        public readonly ?string $principalId,
        public readonly array $organizations = [],
        public readonly ?OrganizationReference $organization = null,
        public readonly array $scopes = [],
        public readonly ?ScopeReference $scope = null,
        public readonly array $memberships = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function membershipIds(): array
    {
        return array_map(static fn (MembershipReference $membership): string => $membership->id, $this->memberships);
    }
}
