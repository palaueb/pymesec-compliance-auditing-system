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
        $scopeId = $this->stringValue($request, 'scope_id');
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
            requestedScopeId: $scopeId,
            requestedMembershipIds: $membershipIds,
        );

        $result = $this->authorization->authorize(new AuthorizationContext(
            principal: new PrincipalReference(
                id: $principalId,
                provider: 'request',
            ),
            permission: $permission,
            memberships: $tenancy->memberships,
            organizationId: $tenancy->organization?->id,
            scopeId: $tenancy->scope?->id,
        ));

        if (! $result->allowed()) {
            return $this->deny($request, $permission, $result->reason ?? 'permission_denied');
        }

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
        $sessionValue = $request->session()->get('auth.principal_id');

        if (is_string($sessionValue) && $sessionValue !== '') {
            return $sessionValue;
        }

        if (app()->environment('testing')) {
            return $this->stringValue($request, 'principal_id');
        }

        return null;
    }
}
