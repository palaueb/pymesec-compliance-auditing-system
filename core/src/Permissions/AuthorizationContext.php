<?php

namespace PymeSec\Core\Permissions;

use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;

class AuthorizationContext
{
    /**
     * @param  array<int, MembershipReference>  $memberships
     */
    public function __construct(
        public readonly PrincipalReference $principal,
        public readonly string $permission,
        public readonly array $memberships = [],
        public readonly ?string $organizationId = null,
        public readonly ?string $scopeId = null,
    ) {}
}
