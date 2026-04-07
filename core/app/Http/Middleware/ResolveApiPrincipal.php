<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PymeSec\Core\Security\ApiAccessTokenRepository;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiPrincipal
{
    public function __construct(
        private readonly ApiAccessTokenRepository $tokens,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $sessionPrincipalId = $request->hasSession()
            ? $request->session()->get('auth.principal_id')
            : null;

        if (is_string($sessionPrincipalId) && $sessionPrincipalId !== '') {
            $this->setResolvedPrincipal(
                request: $request,
                principalId: $sessionPrincipalId,
                authorType: 'principal',
                authorId: $sessionPrincipalId,
            );

            return $next($request);
        }

        if (app()->environment('testing')) {
            $testingPrincipalId = $this->stringValue($request, 'principal_id');

            if ($testingPrincipalId !== null) {
                $this->setResolvedPrincipal(
                    request: $request,
                    principalId: $testingPrincipalId,
                    authorType: 'principal',
                    authorId: $testingPrincipalId,
                );

                return $next($request);
            }
        }

        $bearerToken = $request->bearerToken();

        if (! is_string($bearerToken) || trim($bearerToken) === '') {
            return $this->unauthorized('api_token_required');
        }

        $resolvedToken = $this->tokens->resolve($bearerToken);

        if ($resolvedToken === null) {
            return $this->unauthorized('api_token_invalid_or_expired');
        }

        $this->tokens->touchLastUsed($resolvedToken['id']);
        $this->setResolvedPrincipal(
            request: $request,
            principalId: $resolvedToken['principal_id'],
            authorType: 'api_token',
            authorId: $resolvedToken['id'],
            tokenOrganizationId: $resolvedToken['organization_id'],
            tokenScopeId: $resolvedToken['scope_id'],
            tokenAbilities: $resolvedToken['abilities'] ?? [],
        );

        return $next($request);
    }

    private function unauthorized(string $reason): Response
    {
        return response()->json([
            'error' => [
                'code' => 'authentication_failed',
                'message' => 'Authentication required.',
                'reason' => $reason,
            ],
        ], 401);
    }

    private function setResolvedPrincipal(
        Request $request,
        string $principalId,
        string $authorType,
        string $authorId,
        ?string $tokenOrganizationId = null,
        ?string $tokenScopeId = null,
        array $tokenAbilities = [],
    ): void {
        $request->attributes->set('core.authenticated_principal_id', $principalId);
        $request->attributes->set('core.author_type', $authorType);
        $request->attributes->set('core.author_id', $authorId);
        $request->attributes->set('core.api_token_abilities', array_values(array_filter(
            $tokenAbilities,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        )));

        $request->query->set('principal_id', $principalId);
        $request->request->set('principal_id', $principalId);

        if ($this->stringValue($request, 'organization_id') === null && is_string($tokenOrganizationId) && $tokenOrganizationId !== '') {
            $request->query->set('organization_id', $tokenOrganizationId);
            $request->request->set('organization_id', $tokenOrganizationId);
        }

        if ($this->stringValue($request, 'scope_id') === null && is_string($tokenScopeId) && $tokenScopeId !== '') {
            $request->query->set('scope_id', $tokenScopeId);
            $request->request->set('scope_id', $tokenScopeId);
        }
    }

    private function stringValue(Request $request, string $key): ?string
    {
        $value = $request->input($key, $request->query($key));

        return is_string($value) && $value !== '' ? $value : null;
    }
}
