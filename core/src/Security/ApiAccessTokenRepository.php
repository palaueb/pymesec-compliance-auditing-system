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

        return $this->normalizeRecord($record);
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

    /**
     * @return array{id: string, token: string, token_prefix: string, principal_id: string, organization_id: ?string, scope_id: ?string, expires_at: ?string}|null
     */
    public function rotate(string $id): ?array
    {
        $record = DB::table('api_access_tokens')
            ->where('id', $id)
            ->whereNull('revoked_at')
            ->first();

        if ($record === null) {
            return null;
        }

        if (is_string($record->expires_at) && $record->expires_at !== '' && now()->greaterThan($record->expires_at)) {
            return null;
        }

        $secret = Str::random(64);
        $plainToken = 'pmsk_'.$id.'_'.$secret;

        DB::table('api_access_tokens')
            ->where('id', $id)
            ->update([
                'token_prefix' => substr($plainToken, 0, 20),
                'token_hash' => $this->hashToken($plainToken),
                'last_used_at' => null,
                'updated_at' => now(),
            ]);

        return [
            'id' => $id,
            'token' => $plainToken,
            'token_prefix' => substr($plainToken, 0, 20),
            'principal_id' => (string) $record->principal_id,
            'organization_id' => is_string($record->organization_id ?? null) && $record->organization_id !== ''
                ? $record->organization_id
                : null,
            'scope_id' => is_string($record->scope_id ?? null) && $record->scope_id !== ''
                ? $record->scope_id
                : null,
            'expires_at' => is_string($record->expires_at ?? null) && $record->expires_at !== ''
                ? CarbonImmutable::parse((string) $record->expires_at)->toIso8601String()
                : null,
        ];
    }

    /**
     * @return array<int, array{
     *   id: string,
     *   label: string,
     *   principal_id: string,
     *   organization_id: ?string,
     *   scope_id: ?string,
     *   token_prefix: string,
     *   abilities: array<int, string>,
     *   expires_at: ?string,
     *   last_used_at: ?string,
     *   revoked_at: ?string,
     *   created_by_principal_id: ?string,
     *   created_at: ?string,
     *   updated_at: ?string
     * }>
     */
    public function list(
        ?string $organizationId = null,
        ?string $scopeId = null,
        ?string $principalId = null,
        int $limit = 200,
    ): array {
        $query = DB::table('api_access_tokens')
            ->orderByDesc('created_at')
            ->limit(max(1, min($limit, 1000)));

        if (is_string($organizationId) && $organizationId !== '') {
            $query->where('organization_id', $organizationId);
        }

        if (is_string($scopeId) && $scopeId !== '') {
            $query->where(function ($inner) use ($scopeId): void {
                $inner->whereNull('scope_id')->orWhere('scope_id', $scopeId);
            });
        }

        if (is_string($principalId) && $principalId !== '') {
            $query->where('principal_id', $principalId);
        }

        return $query->get()->map(fn ($record): array => $this->normalizeRecord($record))->all();
    }

    /**
     * @return array{
     *   id: string,
     *   label: string,
     *   principal_id: string,
     *   organization_id: ?string,
     *   scope_id: ?string,
     *   token_prefix: string,
     *   abilities: array<int, string>,
     *   expires_at: ?string,
     *   last_used_at: ?string,
     *   revoked_at: ?string,
     *   created_by_principal_id: ?string,
     *   created_at: ?string,
     *   updated_at: ?string
     * }|null
     */
    public function find(string $id): ?array
    {
        $record = DB::table('api_access_tokens')->where('id', $id)->first();

        if ($record === null) {
            return null;
        }

        return $this->normalizeRecord($record);
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * @return array{
     *   id: string,
     *   label: string,
     *   principal_id: string,
     *   organization_id: ?string,
     *   scope_id: ?string,
     *   token_prefix: string,
     *   abilities: array<int, string>,
     *   expires_at: ?string,
     *   last_used_at: ?string,
     *   revoked_at: ?string,
     *   created_by_principal_id: ?string,
     *   created_at: ?string,
     *   updated_at: ?string
     * }
     */
    private function normalizeRecord(object $record): array
    {
        return [
            'id' => (string) $record->id,
            'label' => is_string($record->label ?? null) ? $record->label : 'API access token',
            'principal_id' => (string) $record->principal_id,
            'organization_id' => is_string($record->organization_id ?? null) && $record->organization_id !== ''
                ? $record->organization_id
                : null,
            'scope_id' => is_string($record->scope_id ?? null) && $record->scope_id !== ''
                ? $record->scope_id
                : null,
            'token_prefix' => is_string($record->token_prefix ?? null) ? $record->token_prefix : '',
            'abilities' => $this->decodeAbilities($record->abilities ?? null),
            'expires_at' => is_string($record->expires_at ?? null) && $record->expires_at !== ''
                ? $record->expires_at
                : null,
            'last_used_at' => is_string($record->last_used_at ?? null) && $record->last_used_at !== ''
                ? $record->last_used_at
                : null,
            'revoked_at' => is_string($record->revoked_at ?? null) && $record->revoked_at !== ''
                ? $record->revoked_at
                : null,
            'created_by_principal_id' => is_string($record->created_by_principal_id ?? null) && $record->created_by_principal_id !== ''
                ? $record->created_by_principal_id
                : null,
            'created_at' => is_string($record->created_at ?? null) && $record->created_at !== ''
                ? $record->created_at
                : null,
            'updated_at' => is_string($record->updated_at ?? null) && $record->updated_at !== ''
                ? $record->updated_at
                : null,
        ];
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
