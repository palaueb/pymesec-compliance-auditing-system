<?php

namespace PymeSec\Core\Permissions;

use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Permissions\Contracts\AuthorizationStoreInterface;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;
use PymeSec\Core\Principals\MembershipReference;

class AuthorizationService implements AuthorizationServiceInterface
{
    public function __construct(
        private readonly PermissionRegistryInterface $permissions,
        private readonly AuthorizationStoreInterface $store,
    ) {}

    public function authorize(AuthorizationContext $context): AuthorizationResult
    {
        if (! $this->permissions->has($context->permission)) {
            return AuthorizationResult::unresolved('permission_not_registered');
        }

        $definition = $this->permissions->all();
        $permissionDefinition = collect($definition)->first(
            static fn (PermissionDefinition $item): bool => $item->key === $context->permission
        );

        if ($permissionDefinition instanceof PermissionDefinition) {
            if (in_array('organization', $permissionDefinition->contexts, true) && $context->organizationId === null) {
                return AuthorizationResult::deny('organization_context_required');
            }

            if (in_array('scope', $permissionDefinition->contexts, true) && $context->scopeId === null) {
                return AuthorizationResult::deny('scope_context_required');
            }
        }

        if ($context->scopeId !== null && ! $this->membershipsAllowScope($context->memberships, $context->organizationId, $context->scopeId)) {
            return AuthorizationResult::deny('scope_not_granted_for_membership');
        }

        $matched = [];

        foreach ($this->store->grantDefinitions() as $grant) {
            if (! $this->grantAppliesToContext($grant, $context)) {
                continue;
            }

            if ($this->grantMatchesPermission($grant, $context->permission)) {
                $matched[] = $grant->toArray();
            }
        }

        if ($matched !== []) {
            return AuthorizationResult::allow($matched, 'grant_matched');
        }

        return AuthorizationResult::unresolved('no_matching_grant');
    }

    private function grantAppliesToContext(PermissionGrant $grant, AuthorizationContext $context): bool
    {
        if (! $this->grantTargetsPrincipal($grant, $context->principal->id) && ! $this->grantTargetsMembership($grant, $context->memberships)) {
            return false;
        }

        return match ($grant->contextType) {
            'platform' => true,
            'organization' => $grant->organizationId !== null && $grant->organizationId === $context->organizationId,
            'scope' => $grant->organizationId !== null
                && $grant->organizationId === $context->organizationId
                && $grant->scopeId !== null
                && $grant->scopeId === $context->scopeId,
            default => false,
        };
    }

    private function grantTargetsPrincipal(PermissionGrant $grant, string $principalId): bool
    {
        return $grant->targetType === 'principal' && $grant->targetId === $principalId;
    }

    /**
     * @param  array<int, MembershipReference>  $memberships
     */
    private function grantTargetsMembership(PermissionGrant $grant, array $memberships): bool
    {
        if ($grant->targetType !== 'membership') {
            return false;
        }

        foreach ($memberships as $membership) {
            if ($membership->id === $grant->targetId) {
                return true;
            }
        }

        return false;
    }

    private function grantMatchesPermission(PermissionGrant $grant, string $permission): bool
    {
        if ($grant->grantType === 'permission') {
            return $grant->value === $permission;
        }

        if ($grant->grantType !== 'role') {
            return false;
        }

        $role = $this->store->roleDefinitions()[$grant->value] ?? null;

        return $role instanceof RoleDefinition
            && in_array($permission, $role->permissions, true);
    }

    /**
     * @param  array<int, MembershipReference>  $memberships
     */
    private function membershipsAllowScope(array $memberships, ?string $organizationId, string $scopeId): bool
    {
        if ($memberships === []) {
            return true;
        }

        $matching = array_values(array_filter(
            $memberships,
            static fn (MembershipReference $membership): bool => $organizationId === null || $membership->organizationId === $organizationId,
        ));

        if ($matching === []) {
            return false;
        }

        foreach ($matching as $membership) {
            if ($membership->scopes === [] || in_array($scopeId, $membership->scopes, true)) {
                return true;
            }
        }

        return false;
    }
}
