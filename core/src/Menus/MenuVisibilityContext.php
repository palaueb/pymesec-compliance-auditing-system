<?php

namespace PymeSec\Core\Menus;

use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;

class MenuVisibilityContext
{
    /**
     * @param  array<int, MembershipReference>  $memberships
     */
    public function __construct(
        public readonly ?PrincipalReference $principal = null,
        public readonly array $memberships = [],
        public readonly ?string $organizationId = null,
        public readonly ?string $scopeId = null,
    ) {
    }
}
