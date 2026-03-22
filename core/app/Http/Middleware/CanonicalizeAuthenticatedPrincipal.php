<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class CanonicalizeAuthenticatedPrincipal
{
    public function handle(Request $request, Closure $next): Response
    {
        $principalId = $request->session()->get('auth.principal_id');

        if (! is_string($principalId) || $principalId === '') {
            return $next($request);
        }

        $hadPrincipalInQuery = $request->query->has('principal_id');
        $request->request->set('principal_id', $principalId);

        if ($hadPrincipalInQuery && in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return redirect()->to($this->stripPrincipalIdFromUrl($request->fullUrl()));
        }

        $response = $next($request);

        if ($response instanceof RedirectResponse) {
            $response->setTargetUrl($this->stripPrincipalIdFromUrl($response->getTargetUrl()));
        }

        return $response;
    }

    private function stripPrincipalIdFromUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        $query = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            unset($query['principal_id']);
        }

        $rebuilt = '';

        if (isset($parts['scheme'])) {
            $rebuilt .= $parts['scheme'].'://';
        }

        if (isset($parts['user'])) {
            $rebuilt .= $parts['user'];

            if (isset($parts['pass'])) {
                $rebuilt .= ':'.$parts['pass'];
            }

            $rebuilt .= '@';
        }

        if (isset($parts['host'])) {
            $rebuilt .= $parts['host'];
        }

        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }

        $rebuilt .= $parts['path'] ?? '';

        if ($query !== []) {
            $rebuilt .= '?'.http_build_query($query);
        }

        if (isset($parts['fragment'])) {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt !== '' ? $rebuilt : $url;
    }
}
