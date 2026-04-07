<?php

namespace PymeSec\Core\Security;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiAccessTokenRepository
{
    /**
     * @return array{id: string, token: string, token_prefix: string, principal_id: string, organization_id: ?string, scope_id: ?string, expires_at: ?string}
     */
    public function issue(
        string $principalId,
        string $label,
        ?string $organizationId = null,
        ?string $scopeId = null,
        ?string $createdByPrincipalId = null,
        ?CarbonImmutable $expiresAt = null,
        array $abilities = [],
    ): array {
        $id = (string) Str::ulid();
        $secret = Str::random(64);
        $plainToken = 'pmsk_'.$id.'_'.$secret;

        DB::table('api_access_tokens')->insert([
            'id' => $id,
            'label' => trim($label) !== '' ? trim($label) : 'API access token',
            'principal_id' => $principalId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'token_prefix' => substr($plainToken, 0, 20),
            'token_hash' => $this->hashToken($plainToken),
            'abilities' => json_encode(array_values(array_filter(
                $abilities,
                static fn (mixed $value): bool => is_string($value) && $value !== '',
            )), JSON_UNESCAPED_SLASHES),
            'expires_at' => $expiresAt?->toDateTimeString(),
            'last_used_at' => null,
            'revoked_at' => null,
            'created_by_principal_id' => $createdByPrincipalId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'token' => $plainToken,
            'token_prefix' => substr($plainToken, 0, 20),
            'principal_id' => $principalId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'expires_at' => $expiresAt?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: string, principal_id: string, organization_id: ?string, scope_id: ?string, abilities: array<int, string>, expires_at: ?string}|null
     */
    public function resolve(string $plainToken): ?array
    {
        if (trim($plainToken) === '') {
            return null;
        }

        $record = DB::table('api_access_tokens')
            ->where('token_hash', $this->hashToken($plainToken))
            ->whereNull('revoked_at')
            ->first();

        if ($record === null) {
            return null;
        }

        if (is_string($record->expires_at) && $record->expires_at !== '' && now()->greaterThan($record->expires_at)) {
            return null;
        }

        return [
            'id' => (string) $record->id,
            'principal_id' => (string) $record->principal_id,
            'organization_id' => is_string($record->organization_id ?? null) ? $record->organization_id : null,
            'scope_id' => is_string($record->scope_id ?? null) ? $record->scope_id : null,
            'abilities' => $this->decodeAbilities($record->abilities ?? null),
            'expires_at' => is_string($record->expires_at ?? null) ? $record->expires_at : null,
        ];
    }

    public function touchLastUsed(string $id): void
    {
        DB::table('api_access_tokens')
            ->where('id', $id)
            ->update([
                'last_used_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function revoke(string $id): bool
    {
        return DB::table('api_access_tokens')
            ->where('id', $id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]) > 0;
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * @return array<int, string>
     */
    private function decodeAbilities(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn (mixed $entry): bool => is_string($entry) && $entry !== ''));
    }
}
