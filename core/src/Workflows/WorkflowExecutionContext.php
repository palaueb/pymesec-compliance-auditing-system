<?php

namespace PymeSec\Core\Workflows;

use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;

class WorkflowExecutionContext
{
    /**
     * @param  array<int, MembershipReference>  $memberships
     */
    public function __construct(
        public readonly PrincipalReference $principal,
        public readonly array $memberships,
        public readonly string $organizationId,
        public readonly ?string $scopeId = null,
        public readonly ?string $membershipId = null,
    ) {}
}
