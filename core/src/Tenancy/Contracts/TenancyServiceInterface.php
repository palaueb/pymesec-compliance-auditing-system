<?php

namespace PymeSec\Core\Tenancy\Contracts;

use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Tenancy\OrganizationReference;
use PymeSec\Core\Tenancy\ScopeReference;
use PymeSec\Core\Tenancy\TenancyContext;

interface TenancyServiceInterface
{
    /**
     * @return array<int, OrganizationReference>
     */
    public function organizations(): array;

    /**
     * @return array<int, OrganizationReference>
     */
    public function organizationsForPrincipal(string $principalId): array;

    /**
     * @param  array<int, MembershipReference>  $memberships
     * @return array<int, ScopeReference>
     */
    public function scopesForOrganization(string $organizationId, array $memberships = []): array;

    /**
     * @param  array<int, string>  $requestedMembershipIds
     * @return array<int, MembershipReference>
     */
    public function membershipsForPrincipal(string $principalId, string $organizationId, array $requestedMembershipIds = []): array;

    /**
     * @param  array<int, string>  $requestedMembershipIds
     */
    public function resolveContext(
        ?string $principalId,
        ?string $requestedOrganizationId = null,
        ?string $requestedScopeId = null,
        array $requestedMembershipIds = [],
    ): TenancyContext;

    public function archiveOrganization(string $organizationId): bool;

    public function activateOrganization(string $organizationId): bool;

    public function archiveScope(string $scopeId): bool;

    public function activateScope(string $scopeId): bool;
}
