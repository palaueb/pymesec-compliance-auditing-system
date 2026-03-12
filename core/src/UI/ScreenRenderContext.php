<?php

namespace PymeSec\Core\UI;

use Illuminate\Contracts\Foundation\Application;
use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;

class ScreenRenderContext
{
    /**
     * @param  array<int, MembershipReference>  $memberships
     * @param  array<string, mixed>  $query
     */
    public function __construct(
        public readonly Application $app,
        public readonly ?PrincipalReference $principal = null,
        public readonly array $memberships = [],
        public readonly ?string $organizationId = null,
        public readonly ?string $scopeId = null,
        public readonly string $locale = 'en',
        public readonly array $query = [],
    ) {}
}
