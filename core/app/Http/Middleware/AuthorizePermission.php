<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use Symfony\Component\HttpFoundation\Response;

class AuthorizePermission
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly TenancyServiceInterface $tenancy,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $principalId = $this->actingPrincipalId($request);

        if ($principalId === null) {
            return $this->deny($request, $permission, 'principal_context_required');
        }

        // Once a shell session exists, downstream routes must not be able to impersonate
        // a different principal via query or form parameters.
        $request->query->set('principal_id', $principalId);
        $request->request->set('principal_id', $principalId);

        $organizationId = $this->stringValue($request, 'organization_id');
        $requestedScopeId = $this->stringValue($request, 'scope_id');
        $membershipIds = $request->input('membership_ids', $request->query('membership_ids', []));

        if (! is_array($membershipIds)) {
            $membershipIds = [];
        }

        $membershipId = $this->stringValue($request, 'membership_id');

        if ($membershipId !== null) {
            $membershipIds[] = $membershipId;
        }

        $tenancy = $this->tenancy->resolveContext(
            principalId: $principalId,
            requestedOrganizationId: $organizationId,
            requestedScopeId: $requestedScopeId,
            requestedMembershipIds: $membershipIds,
        );

        $effectiveScopeId = $tenancy->scope?->id;

        if ($tenancy->organization !== null && $tenancy->memberships !== [] && ! $this->hasOrganizationWideMembership($tenancy->memberships)) {
            if ($requestedScopeId !== null && $effectiveScopeId === null) {
                return $this->deny($request, $permission, 'scope_not_granted_for_membership');
            }

            if ($effectiveScopeId === null) {
                $effectiveScopeId = $tenancy->scopes[0]->id ?? null;
            }
        }

        $result = $this->authorization->authorize(new AuthorizationContext(
            principal: new PrincipalReference(
                id: $principalId,
                provider: 'request',
            ),
            permission: $permission,
            memberships: $tenancy->memberships,
            organizationId: $tenancy->organization?->id,
            scopeId: $effectiveScopeId,
        ));

        if (! $result->allowed()) {
            return $this->deny($request, $permission, $result->reason ?? 'permission_denied');
        }

        $canonicalOrganizationId = $tenancy->organization?->id ?? $organizationId;
        $canonicalScopeId = $effectiveScopeId;

        if ($tenancy->organization === null && $tenancy->memberships === []) {
            $canonicalScopeId = $requestedScopeId;
        }

        $this->canonicalizeResolvedTenancy(
            request: $request,
            organizationId: $canonicalOrganizationId,
            scopeId: $canonicalScopeId,
            membershipIds: array_values(array_map(
                static fn ($membership): string => $membership->id,
                $tenancy->memberships,
            )),
            requestedMembershipId: $membershipId,
            requestedMembershipIds: $membershipIds,
        );

        return $next($request);
    }

    private function deny(Request $request, string $permission, string $reason): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Forbidden',
                'permission' => $permission,
                'reason' => $reason,
            ], 403);
        }

        abort(403, sprintf('Permission [%s] denied: %s', $permission, $reason));
    }

    private function stringValue(Request $request, string $key): ?string
    {
        $value = $request->input($key, $request->query($key));

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function actingPrincipalId(Request $request): ?string
    {
        $resolvedPrincipal = $request->attributes->get('core.authenticated_principal_id');

        if (is_string($resolvedPrincipal) && $resolvedPrincipal !== '') {
            return $resolvedPrincipal;
        }

        $sessionValue = $request->hasSession()
            ? $request->session()->get('auth.principal_id')
            : null;

        if (is_string($sessionValue) && $sessionValue !== '') {
            return $sessionValue;
        }

        if (app()->environment('testing')) {
            return $this->stringValue($request, 'principal_id');
        }

        return null;
    }

    /**
     * @param  array<int, string>  $membershipIds
     * @param  array<int, mixed>  $requestedMembershipIds
     */
    private function canonicalizeResolvedTenancy(
        Request $request,
        ?string $organizationId,
        ?string $scopeId,
        array $membershipIds,
        ?string $requestedMembershipId,
        array $requestedMembershipIds,
    ): void {
        $this->setOrForget($request, 'organization_id', $organizationId);
        $this->setOrForget($request, 'scope_id', $scopeId);

        $request->query->set('membership_ids', $membershipIds);
        $request->request->set('membership_ids', $membershipIds);

        $selectedMembershipId = $this->selectMembershipId($membershipIds, $requestedMembershipId, $requestedMembershipIds);
        $this->setOrForget($request, 'membership_id', $selectedMembershipId);
    }

    private function setOrForget(Request $request, string $key, ?string $value): void
    {
        if (is_string($value) && $value !== '') {
            $request->query->set($key, $value);
            $request->request->set($key, $value);

            return;
        }

        $request->query->remove($key);
        $request->request->remove($key);
    }

    /**
     * @param  array<int, string>  $membershipIds
     * @param  array<int, mixed>  $requestedMembershipIds
     */
    private function selectMembershipId(array $membershipIds, ?string $requestedMembershipId, array $requestedMembershipIds): ?string
    {
        if ($requestedMembershipId !== null && in_array($requestedMembershipId, $membershipIds, true)) {
            return $requestedMembershipId;
        }

        foreach ($requestedMembershipIds as $candidate) {
            if (is_string($candidate) && in_array($candidate, $membershipIds, true)) {
                return $candidate;
            }
        }

        return $membershipIds[0] ?? null;
    }

    private function hasOrganizationWideMembership(array $memberships): bool
    {
        foreach ($memberships as $membership) {
            if ($membership->scopes === []) {
                return true;
            }
        }

        return false;
    }
}
