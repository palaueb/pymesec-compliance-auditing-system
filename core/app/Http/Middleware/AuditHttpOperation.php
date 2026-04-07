<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditHttpOperation
{
    private static ?bool $canRecord = null;

    public function __construct(
        private readonly AuditTrailInterface $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->recordingIsAvailable()) {
            return $next($request);
        }

        $startedAt = microtime(true);
        $requestId = $this->requestId($request);
        $request->attributes->set('core.request_id', $requestId);

        try {
            $response = $next($request);

            $this->record(
                request: $request,
                statusCode: $response->getStatusCode(),
                durationMs: (int) round((microtime(true) - $startedAt) * 1000),
                exception: null,
            );

            return $response;
        } catch (Throwable $exception) {
            $statusCode = method_exists($exception, 'getStatusCode')
                ? (int) $exception->getStatusCode()
                : 500;

            $this->record(
                request: $request,
                statusCode: $statusCode,
                durationMs: (int) round((microtime(true) - $startedAt) * 1000),
                exception: $exception,
            );

            throw $exception;
        }
    }

    private function record(Request $request, int $statusCode, int $durationMs, ?Throwable $exception): void
    {
        [$authorType, $authorId, $principalId] = $this->resolveAuthor($request);
        $channel = $request->is('api/*') ? 'api' : 'web';
        $outcome = $this->outcomeFromStatus($statusCode);

        $this->audit->record(new AuditRecordData(
            eventType: 'core.http.request',
            outcome: $outcome,
            originComponent: 'core.http',
            channel: $channel,
            authorType: $authorType,
            authorId: $authorId,
            principalId: $principalId,
            membershipId: $this->stringValue($request, 'membership_id'),
            organizationId: $this->stringValue($request, 'organization_id'),
            scopeId: $this->stringValue($request, 'scope_id'),
            targetType: 'http-route',
            targetId: $request->route()?->getName(),
            summary: [
                'duration_ms' => $durationMs,
                'route_name' => $request->route()?->getName(),
                'query_keys' => array_values(array_keys($request->query())),
                'body_keys' => $this->bodyKeys($request),
                'exception' => $exception?->getMessage(),
                'exception_class' => $exception !== null ? $exception::class : null,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            ],
            correlation: [
                'request_id' => (string) $request->attributes->get('core.request_id'),
            ],
            executionOrigin: 'http',
            requestMethod: $request->getMethod(),
            requestPath: '/'.ltrim($request->path(), '/'),
            statusCode: $statusCode,
        ));
    }

    private function recordingIsAvailable(): bool
    {
        if (self::$canRecord !== null) {
            return self::$canRecord;
        }

        try {
            self::$canRecord = Schema::hasTable('audit_logs');
        } catch (Throwable) {
            self::$canRecord = false;
        }

        return self::$canRecord;
    }

    /**
     * @return array{0: string, 1: string|null, 2: string|null}
     */
    private function resolveAuthor(Request $request): array
    {
        $authorType = $request->attributes->get('core.author_type');
        $authorId = $request->attributes->get('core.author_id');
        $principalId = $request->attributes->get('core.authenticated_principal_id');

        $principalId = is_string($principalId) && $principalId !== ''
            ? $principalId
            : $this->stringValue($request, 'principal_id');

        if (is_string($authorType) && $authorType !== '') {
            return [$authorType, is_string($authorId) && $authorId !== '' ? $authorId : null, $principalId];
        }

        if (is_string($principalId) && $principalId !== '') {
            return ['principal', $principalId, $principalId];
        }

        return ['anonymous', null, null];
    }

    private function requestId(Request $request): string
    {
        $header = $request->headers->get('X-Request-Id');

        if (is_string($header) && trim($header) !== '') {
            return trim($header);
        }

        return (string) Str::ulid();
    }

    private function outcomeFromStatus(int $statusCode): string
    {
        if ($statusCode >= 200 && $statusCode < 400) {
            return 'success';
        }

        if (in_array($statusCode, [401, 403], true)) {
            return 'denied';
        }

        return 'failure';
    }

    /**
     * @return array<int, string>
     */
    private function bodyKeys(Request $request): array
    {
        $blocked = [
            '_token',
            'password',
            'password_confirmation',
            'password_hash',
            'token',
            'secret',
            'private_key',
            'public_key_pem',
        ];

        return array_values(array_filter(
            array_keys($request->except($blocked)),
            static fn (mixed $key): bool => is_string($key),
        ));
    }

    private function stringValue(Request $request, string $key): ?string
    {
        $value = $request->input($key, $request->query($key));

        return is_string($value) && $value !== '' ? $value : null;
    }
}
