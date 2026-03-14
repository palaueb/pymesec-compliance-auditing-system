<?php

namespace PymeSec\Plugins\IdentityLocal;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;

class IdentityLocalAuthService
{
    private const LINK_TTL_MINUTES = 20;
    private const CODE_TTL_MINUTES = 10;

    public function __construct(
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
        private readonly IdentityLocalRepository $users,
        private readonly TenancyServiceInterface $tenancy,
    ) {}

    public function requiresBootstrap(): bool
    {
        return ! $this->users->anyUsers();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function bootstrapSuperAdmin(array $data): array
    {
        if ($this->users->anyUsers()) {
            throw ValidationException::withMessages([
                'email' => 'Bootstrap is only available before the first user exists.',
            ]);
        }

        $organizationId = $this->users->firstOrganizationId();

        if (! is_string($organizationId) || $organizationId === '') {
            $organizationName = trim((string) ($data['organization_name'] ?? ''));

            if ($organizationName === '') {
                throw ValidationException::withMessages([
                    'organization_name' => 'An organization name is required for the first installation.',
                ]);
            }

            $organizationId = $this->tenancy->createOrganization([
                'name' => $organizationName,
                'slug' => (string) ($data['organization_slug'] ?? ''),
                'default_locale' => (string) ($data['default_locale'] ?? 'en'),
                'default_timezone' => (string) ($data['default_timezone'] ?? 'UTC'),
            ])->id;
        }

        $password = is_string($data['password'] ?? null) && $data['password'] !== '' ? (string) $data['password'] : null;

        $user = $this->users->createUser([
            'organization_id' => $organizationId,
            'display_name' => (string) $data['display_name'],
            'username' => (string) $data['username'],
            'email' => (string) $data['email'],
            'job_title' => 'Platform administrator',
            'password' => $password,
            'password_enabled' => $password !== null,
            'magic_link_enabled' => true,
            'is_active' => true,
        ]);

        $this->users->ensurePlatformAdminGrant((string) $user['principal_id']);
        $this->users->ensureBootstrapOrganizationAccess((string) $user['principal_id'], $organizationId);

        return $user;
    }

    /**
     * @return array{token:string,user:array<string,mixed>}|null
     */
    public function issueMagicLink(string $login, ?Request $request = null): ?array
    {
        $user = $this->users->findActiveUserByLogin($login);

        if ($user === null || ! (bool) ($user['magic_link_enabled'] ?? true)) {
            $this->audit->record(new AuditRecordData(
                eventType: 'plugin.identity-local.auth.link.requested',
                outcome: 'failure',
                originComponent: 'identity-local',
                targetType: 'identity_local_user',
                summary: [
                    'login' => $login,
                    'reason' => 'user_not_found_inactive_or_magic_link_disabled',
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
     * @return array{code:string,user:array<string,mixed>}|null
     */
    public function beginPasswordLogin(string $login, string $password, ?Request $request = null): ?array
    {
        $user = $this->users->findActiveUserByLogin($login);

        if ($user === null
            || ! (bool) ($user['password_enabled'] ?? false)
            || ! is_string($user['password_hash'] ?? null)
            || $user['password_hash'] === ''
            || ! Hash::check($password, (string) $user['password_hash'])) {
            $this->audit->record(new AuditRecordData(
                eventType: 'plugin.identity-local.auth.password.requested',
                outcome: 'failure',
                originComponent: 'identity-local',
                targetType: 'identity_local_user',
                summary: [
                    'login' => $login,
                    'reason' => 'invalid_credentials_or_password_disabled',
                ],
                executionOrigin: 'identity-local-auth',
            ));

            return null;
        }

        $code = $this->issueEmailCode($user, 'password-2fa', $request);

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-local.auth.password.requested',
            outcome: 'success',
            originComponent: 'identity-local',
            principalId: (string) $user['principal_id'],
            organizationId: is_string($user['organization_id'] ?? null) ? $user['organization_id'] : null,
            targetType: 'identity_local_user',
            targetId: (string) $user['id'],
            summary: [
                'login' => $login,
                'delivery' => 'mail_code',
            ],
            executionOrigin: 'identity-local-auth',
        ));

        return [
            'code' => $code,
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
    public function consumeEmailCode(string $principalId, string $code, string $purpose = 'password-2fa'): ?array
    {
        $codeHash = $this->codeHash($principalId, $purpose, $code);
        $record = DB::table('identity_local_login_codes')
            ->where('principal_id', $principalId)
            ->where('purpose', $purpose)
            ->where('code_hash', $codeHash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>=', now())
            ->first();

        if ($record === null) {
            $this->audit->record(new AuditRecordData(
                eventType: 'plugin.identity-local.auth.code.consumed',
                outcome: 'failure',
                originComponent: 'identity-local',
                principalId: $principalId !== '' ? $principalId : null,
                summary: [
                    'reason' => 'code_invalid_or_expired',
                    'purpose' => $purpose,
                ],
                executionOrigin: 'identity-local-auth',
            ));

            return null;
        }

        DB::table('identity_local_login_codes')
            ->where('id', $record->id)
            ->update([
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

        $user = $this->users->findUserByPrincipal($principalId);

        if ($user === null || ! (bool) ($user['is_active'] ?? false)) {
            return null;
        }

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-local.auth.code.consumed',
            outcome: 'success',
            originComponent: 'identity-local',
            principalId: (string) $user['principal_id'],
            organizationId: is_string($user['organization_id'] ?? null) ? $user['organization_id'] : null,
            targetType: 'identity_local_user',
            targetId: (string) $user['id'],
            summary: [
                'purpose' => $purpose,
                'email' => $user['email'],
            ],
            executionOrigin: 'identity-local-auth',
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

    /**
     * @param  array<string, mixed>  $user
     */
    private function issueEmailCode(array $user, string $purpose, ?Request $request = null): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('identity_local_login_codes')->insert([
            'id' => (string) Str::ulid(),
            'principal_id' => (string) $user['principal_id'],
            'email' => (string) $user['email'],
            'purpose' => $purpose,
            'code_hash' => $this->codeHash((string) $user['principal_id'], $purpose, $code),
            'requested_ip' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 255, ''),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Mail::raw(
            "Your sign-in code is {$code}\n\nThis code expires in ".self::CODE_TTL_MINUTES.' minutes.',
            function ($message) use ($user): void {
                $message
                    ->to((string) $user['email'], (string) $user['display_name'])
                    ->subject('Your sign-in code');
            },
        );

        $this->events->publish(new PublicEvent(
            name: 'plugin.identity-local.auth.code.requested',
            originComponent: 'identity-local',
            organizationId: is_string($user['organization_id'] ?? null) ? $user['organization_id'] : null,
            payload: [
                'principal_id' => $user['principal_id'],
                'purpose' => $purpose,
            ],
        ));

        return $code;
    }

    private function codeHash(string $principalId, string $purpose, string $code): string
    {
        return hash('sha256', Str::lower($principalId).'|'.$purpose.'|'.$code);
    }
}
