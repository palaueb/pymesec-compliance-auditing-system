<?php

namespace PymeSec\Plugins\IdentityLocal;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;

class IdentityLocalAuthService
{
    private const LINK_TTL_MINUTES = 20;

    public function __construct(
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
        private readonly IdentityLocalRepository $users,
    ) {}

    /**
     * @return array{token:string,user:array<string,mixed>}|null
     */
    public function issueMagicLink(string $email, ?Request $request = null): ?array
    {
        $user = $this->users->findActiveUserByEmail($email);

        if ($user === null) {
            $this->audit->record(new AuditRecordData(
                eventType: 'plugin.identity-local.auth.link.requested',
                outcome: 'failure',
                originComponent: 'identity-local',
                targetType: 'identity_local_user',
                summary: [
                    'email' => $email,
                    'reason' => 'user_not_found_or_inactive',
                ],
                executionOrigin: 'identity-local-auth',
            ));

            return null;
        }

        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        DB::table('identity_local_login_links')->insert([
            'id' => (string) Str::ulid(),
            'principal_id' => $user['principal_id'],
            'email' => $user['email'],
            'token_hash' => $tokenHash,
            'requested_ip' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 255, ''),
            'expires_at' => now()->addMinutes(self::LINK_TTL_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = route('plugin.identity-local.auth.consume', ['token' => $token]);

        Mail::raw(
            "Use this secure sign-in link: {$url}\n\nThis link expires in ".self::LINK_TTL_MINUTES." minutes.",
            function ($message) use ($user): void {
                $message
                    ->to((string) $user['email'], (string) $user['display_name'])
                    ->subject('Your sign-in link');
            },
        );

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-local.auth.link.requested',
            outcome: 'success',
            originComponent: 'identity-local',
            principalId: $user['principal_id'],
            organizationId: is_string($user['organization_id'] ?? null) ? $user['organization_id'] : null,
            targetType: 'identity_local_user',
            targetId: (string) $user['id'],
            summary: [
                'email' => $user['email'],
                'delivery' => 'mail',
            ],
            executionOrigin: 'identity-local-auth',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.identity-local.auth.link.requested',
            originComponent: 'identity-local',
            organizationId: is_string($user['organization_id'] ?? null) ? $user['organization_id'] : null,
            payload: [
                'principal_id' => $user['principal_id'],
                'email' => $user['email'],
            ],
        ));

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function consumeMagicLink(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);
        $record = DB::table('identity_local_login_links')
            ->where('token_hash', $tokenHash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>=', now())
            ->first();

        if ($record === null) {
            $this->audit->record(new AuditRecordData(
                eventType: 'plugin.identity-local.auth.link.consumed',
                outcome: 'failure',
                originComponent: 'identity-local',
                summary: [
                    'reason' => 'token_invalid_or_expired',
                ],
                executionOrigin: 'identity-local-auth',
            ));

            return null;
        }

        DB::table('identity_local_login_links')
            ->where('id', $record->id)
            ->update([
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

        $user = $this->users->findUserByPrincipal((string) $record->principal_id);

        if ($user === null || ! (bool) ($user['is_active'] ?? false)) {
            return null;
        }

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-local.auth.link.consumed',
            outcome: 'success',
            originComponent: 'identity-local',
            principalId: $user['principal_id'],
            organizationId: is_string($user['organization_id'] ?? null) ? $user['organization_id'] : null,
            targetType: 'identity_local_user',
            targetId: (string) $user['id'],
            summary: [
                'email' => $user['email'],
            ],
            executionOrigin: 'identity-local-auth',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.identity-local.auth.link.consumed',
            originComponent: 'identity-local',
            organizationId: is_string($user['organization_id'] ?? null) ? $user['organization_id'] : null,
            payload: [
                'principal_id' => $user['principal_id'],
                'email' => $user['email'],
            ],
        ));

        return $user;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function currentUser(?string $principalId): ?array
    {
        if (! is_string($principalId) || $principalId === '') {
            return null;
        }

        return $this->users->findUserByPrincipal($principalId);
    }
}
