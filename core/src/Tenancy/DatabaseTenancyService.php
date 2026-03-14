<?php

namespace PymeSec\Core\Tenancy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;

class DatabaseTenancyService implements TenancyServiceInterface
{
    public function __construct(
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
    ) {}

    public function organizations(): array
    {
        return DB::table('organizations')
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn ($record): OrganizationReference => $this->mapOrganization($record))
            ->all();
    }

    public function organizationsForPrincipal(string $principalId): array
    {
        return DB::table('organizations')
            ->join('memberships', 'memberships.organization_id', '=', 'organizations.id')
            ->where('organizations.is_active', true)
            ->where('memberships.is_active', true)
            ->where('memberships.principal_id', $principalId)
            ->select(
                'organizations.id',
                'organizations.name',
                'organizations.slug',
                'organizations.default_locale',
                'organizations.default_timezone',
            )
            ->distinct()
            ->orderBy('organizations.id')
            ->get()
            ->map(fn ($record): OrganizationReference => $this->mapOrganization($record))
            ->all();
    }

    public function scopesForOrganization(string $organizationId, array $memberships = []): array
    {
        $query = DB::table('scopes')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('name');

        if ($memberships !== [] && ! $this->hasOrganizationWideMembership($memberships)) {
            $allowedScopeIds = [];

            foreach ($memberships as $membership) {
                foreach ($membership->scopes as $scopeId) {
                    $allowedScopeIds[$scopeId] = true;
                }
            }

            if ($allowedScopeIds === []) {
                return [];
            }

            $query->whereIn('id', array_keys($allowedScopeIds));
        }

        return $query->get()
            ->map(fn ($record): ScopeReference => $this->mapScope($record))
            ->all();
    }

    public function membershipsForPrincipal(string $principalId, string $organizationId, array $requestedMembershipIds = []): array
    {
        $normalizedIds = array_values(array_filter($requestedMembershipIds, static fn (mixed $value): bool => is_string($value) && $value !== ''));

        $query = DB::table('memberships')
            ->where('principal_id', $principalId)
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('id');

        if ($normalizedIds !== []) {
            $query->whereIn('id', $normalizedIds);
        }

        $records = $query->get();

        if ($records->isEmpty() && $normalizedIds !== []) {
            $records = DB::table('memberships')
                ->where('principal_id', $principalId)
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();
        }

        $membershipIds = $records->pluck('id')->all();
        $scopesByMembership = [];

        if ($membershipIds !== []) {
            foreach (DB::table('membership_scope')
                ->whereIn('membership_id', $membershipIds)
                ->orderBy('scope_id')
                ->get(['membership_id', 'scope_id']) as $record) {
                $scopesByMembership[$record->membership_id][] = $record->scope_id;
            }
        }

        return $records
            ->map(static fn ($record) => new MembershipReference(
                id: (string) $record->id,
                principalId: (string) $record->principal_id,
                organizationId: (string) $record->organization_id,
                roles: self::decodeStringArray($record->roles ?? null),
                scopes: $scopesByMembership[$record->id] ?? [],
            ))
            ->all();
    }

    public function resolveContext(
        ?string $principalId,
        ?string $requestedOrganizationId = null,
        ?string $requestedScopeId = null,
        array $requestedMembershipIds = [],
    ): TenancyContext {
        $organizations = is_string($principalId) && $principalId !== ''
            ? $this->organizationsForPrincipal($principalId)
            : $this->organizations();

        $organization = $this->selectOrganization($organizations, $requestedOrganizationId);
        $memberships = [];
        $scopes = [];
        $scope = null;

        if ($organization !== null && is_string($principalId) && $principalId !== '') {
            $memberships = $this->membershipsForPrincipal($principalId, $organization->id, $requestedMembershipIds);
            $scopes = $this->scopesForOrganization($organization->id, $memberships);
            $scope = $this->selectScope($scopes, $requestedScopeId);
        }

        return new TenancyContext(
            principalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            organizations: $organizations,
            organization: $organization,
            scopes: $scopes,
            scope: $scope,
            memberships: $memberships,
        );
    }

    public function createOrganization(array $data): OrganizationReference
    {
        $name = trim((string) ($data['name'] ?? ''));
        $slug = $this->normalizeSlug($data['slug'] ?? null, $name, 'organization');
        $organizationId = $this->nextOrganizationId($slug);

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => $name,
            'slug' => $slug,
            'default_locale' => (string) ($data['default_locale'] ?? 'en'),
            'default_timezone' => (string) ($data['default_timezone'] ?? 'UTC'),
            'is_active' => true,
            'archived_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $organization = $this->findOrganization($organizationId);

        $this->audit->record(new AuditRecordData(
            eventType: 'core.tenancy.organization.created',
            outcome: 'success',
            originComponent: 'core',
            targetType: 'organization',
            targetId: $organizationId,
            organizationId: $organizationId,
            summary: [
                'name' => $name,
                'slug' => $slug,
            ],
            executionOrigin: 'tenancy',
        ));

        $this->events->publish(new PublicEvent(
            name: 'core.tenancy.organization.created',
            originComponent: 'core',
            organizationId: $organizationId,
            payload: [
                'name' => $name,
                'slug' => $slug,
            ],
        ));

        return $organization ?? new OrganizationReference(
            id: $organizationId,
            name: $name,
            slug: $slug,
            defaultLocale: (string) ($data['default_locale'] ?? 'en'),
            defaultTimezone: (string) ($data['default_timezone'] ?? 'UTC'),
        );
    }

    public function updateOrganization(string $organizationId, array $data): ?OrganizationReference
    {
        $existing = DB::table('organizations')->where('id', $organizationId)->first();

        if ($existing === null) {
            return null;
        }

        $name = trim((string) ($data['name'] ?? $existing->name));
        $slug = $this->normalizeSlug($data['slug'] ?? $existing->slug, $name, 'organization');

        DB::table('organizations')
            ->where('id', $organizationId)
            ->update([
                'name' => $name,
                'slug' => $slug,
                'default_locale' => (string) ($data['default_locale'] ?? $existing->default_locale),
                'default_timezone' => (string) ($data['default_timezone'] ?? $existing->default_timezone),
                'updated_at' => now(),
            ]);

        $organization = $this->findOrganization($organizationId);

        $this->audit->record(new AuditRecordData(
            eventType: 'core.tenancy.organization.updated',
            outcome: 'success',
            originComponent: 'core',
            targetType: 'organization',
            targetId: $organizationId,
            organizationId: $organizationId,
            summary: [
                'name' => $name,
                'slug' => $slug,
            ],
            executionOrigin: 'tenancy',
        ));

        $this->events->publish(new PublicEvent(
            name: 'core.tenancy.organization.updated',
            originComponent: 'core',
            organizationId: $organizationId,
            payload: [
                'name' => $name,
                'slug' => $slug,
            ],
        ));

        return $organization;
    }

    public function archiveOrganization(string $organizationId): bool
    {
        $updated = DB::table('organizations')
            ->where('id', $organizationId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'archived_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->audit->record(new AuditRecordData(
                eventType: 'core.tenancy.organization.archived',
                outcome: 'success',
                originComponent: 'core',
                targetType: 'organization',
                targetId: $organizationId,
                organizationId: $organizationId,
                summary: [
                    'operation' => 'archive',
                ],
                executionOrigin: 'tenancy',
            ));

            $this->events->publish(new PublicEvent(
                name: 'core.tenancy.organization.archived',
                originComponent: 'core',
                organizationId: $organizationId,
                payload: [
                    'operation' => 'archive',
                ],
            ));
        }

        return $updated > 0;
    }

    public function activateOrganization(string $organizationId): bool
    {
        $updated = DB::table('organizations')
            ->where('id', $organizationId)
            ->where('is_active', false)
            ->update([
                'is_active' => true,
                'archived_at' => null,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->audit->record(new AuditRecordData(
                eventType: 'core.tenancy.organization.activated',
                outcome: 'success',
                originComponent: 'core',
                targetType: 'organization',
                targetId: $organizationId,
                organizationId: $organizationId,
                summary: [
                    'operation' => 'activate',
                ],
                executionOrigin: 'tenancy',
            ));

            $this->events->publish(new PublicEvent(
                name: 'core.tenancy.organization.activated',
                originComponent: 'core',
                organizationId: $organizationId,
                payload: [
                    'operation' => 'activate',
                ],
            ));
        }

        return $updated > 0;
    }

    public function createScope(array $data): ScopeReference
    {
        $organizationId = (string) ($data['organization_id'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));
        $slug = $this->normalizeSlug($data['slug'] ?? null, $name, 'scope');
        $scopeId = $this->nextScopeId($slug);
        $description = trim((string) ($data['description'] ?? ''));

        DB::table('scopes')->insert([
            'id' => $scopeId,
            'organization_id' => $organizationId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description !== '' ? $description : null,
            'is_active' => true,
            'archived_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scope = $this->findScope($scopeId);

        $this->audit->record(new AuditRecordData(
            eventType: 'core.tenancy.scope.created',
            outcome: 'success',
            originComponent: 'core',
            targetType: 'scope',
            targetId: $scopeId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            summary: [
                'name' => $name,
                'slug' => $slug,
            ],
            executionOrigin: 'tenancy',
        ));

        $this->events->publish(new PublicEvent(
            name: 'core.tenancy.scope.created',
            originComponent: 'core',
            organizationId: $organizationId,
            scopeId: $scopeId,
            payload: [
                'name' => $name,
                'slug' => $slug,
            ],
        ));

        return $scope ?? new ScopeReference(
            id: $scopeId,
            organizationId: $organizationId,
            name: $name,
            slug: $slug,
            description: $description !== '' ? $description : null,
        );
    }

    public function updateScope(string $scopeId, array $data): ?ScopeReference
    {
        $existing = DB::table('scopes')->where('id', $scopeId)->first();

        if ($existing === null) {
            return null;
        }

        $name = trim((string) ($data['name'] ?? $existing->name));
        $slug = $this->normalizeSlug($data['slug'] ?? $existing->slug, $name, 'scope');
        $description = trim((string) ($data['description'] ?? ($existing->description ?? '')));

        DB::table('scopes')
            ->where('id', $scopeId)
            ->update([
                'name' => $name,
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'updated_at' => now(),
            ]);

        $scope = $this->findScope($scopeId);

        $this->audit->record(new AuditRecordData(
            eventType: 'core.tenancy.scope.updated',
            outcome: 'success',
            originComponent: 'core',
            targetType: 'scope',
            targetId: $scopeId,
            organizationId: (string) $existing->organization_id,
            scopeId: $scopeId,
            summary: [
                'name' => $name,
                'slug' => $slug,
            ],
            executionOrigin: 'tenancy',
        ));

        $this->events->publish(new PublicEvent(
            name: 'core.tenancy.scope.updated',
            originComponent: 'core',
            organizationId: (string) $existing->organization_id,
            scopeId: $scopeId,
            payload: [
                'name' => $name,
                'slug' => $slug,
            ],
        ));

        return $scope;
    }

    public function archiveScope(string $scopeId): bool
    {
        $scope = DB::table('scopes')
            ->where('id', $scopeId)
            ->first(['id', 'organization_id', 'is_active']);

        if ($scope === null || ! (bool) $scope->is_active) {
            return false;
        }

        $updated = DB::table('scopes')
            ->where('id', $scopeId)
            ->update([
                'is_active' => false,
                'archived_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->audit->record(new AuditRecordData(
                eventType: 'core.tenancy.scope.archived',
                outcome: 'success',
                originComponent: 'core',
                targetType: 'scope',
                targetId: $scopeId,
                organizationId: (string) $scope->organization_id,
                scopeId: $scopeId,
                summary: [
                    'operation' => 'archive',
                ],
                executionOrigin: 'tenancy',
            ));

            $this->events->publish(new PublicEvent(
                name: 'core.tenancy.scope.archived',
                originComponent: 'core',
                organizationId: (string) $scope->organization_id,
                scopeId: $scopeId,
                payload: [
                    'operation' => 'archive',
                ],
            ));
        }

        return $updated > 0;
    }

    public function activateScope(string $scopeId): bool
    {
        $scope = DB::table('scopes')
            ->where('id', $scopeId)
            ->first(['id', 'organization_id', 'is_active']);

        if ($scope === null || (bool) $scope->is_active) {
            return false;
        }

        $updated = DB::table('scopes')
            ->where('id', $scopeId)
            ->update([
                'is_active' => true,
                'archived_at' => null,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->audit->record(new AuditRecordData(
                eventType: 'core.tenancy.scope.activated',
                outcome: 'success',
                originComponent: 'core',
                targetType: 'scope',
                targetId: $scopeId,
                organizationId: (string) $scope->organization_id,
                scopeId: $scopeId,
                summary: [
                    'operation' => 'activate',
                ],
                executionOrigin: 'tenancy',
            ));

            $this->events->publish(new PublicEvent(
                name: 'core.tenancy.scope.activated',
                originComponent: 'core',
                organizationId: (string) $scope->organization_id,
                scopeId: $scopeId,
                payload: [
                    'operation' => 'activate',
                ],
            ));
        }

        return $updated > 0;
    }

    private function selectOrganization(array $organizations, ?string $requestedOrganizationId): ?OrganizationReference
    {
        if ($requestedOrganizationId !== null && $requestedOrganizationId !== '') {
            foreach ($organizations as $organization) {
                if ($organization->id === $requestedOrganizationId) {
                    return $organization;
                }
            }

            return null;
        }

        return $organizations[0] ?? null;
    }

    private function selectScope(array $scopes, ?string $requestedScopeId): ?ScopeReference
    {
        if ($requestedScopeId === null || $requestedScopeId === '') {
            return null;
        }

        foreach ($scopes as $scope) {
            if ($scope->id === $requestedScopeId) {
                return $scope;
            }
        }

        return null;
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

    private function mapOrganization(object $record): OrganizationReference
    {
        return new OrganizationReference(
            id: (string) $record->id,
            name: (string) $record->name,
            slug: (string) $record->slug,
            defaultLocale: (string) $record->default_locale,
            defaultTimezone: (string) $record->default_timezone,
        );
    }

    private function mapScope(object $record): ScopeReference
    {
        return new ScopeReference(
            id: (string) $record->id,
            organizationId: (string) $record->organization_id,
            name: (string) $record->name,
            slug: (string) $record->slug,
            description: is_string($record->description ?? null) ? $record->description : null,
        );
    }

    /**
     * @return array<int, string>
     */
    private static function decodeStringArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== ''));
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }

    private function findOrganization(string $organizationId): ?OrganizationReference
    {
        $record = DB::table('organizations')->where('id', $organizationId)->first();

        return $record !== null ? $this->mapOrganization($record) : null;
    }

    private function findScope(string $scopeId): ?ScopeReference
    {
        $record = DB::table('scopes')->where('id', $scopeId)->first();

        return $record !== null ? $this->mapScope($record) : null;
    }

    private function normalizeSlug(mixed $slug, string $fallback, string $context): string
    {
        $candidate = Str::slug(is_string($slug) && $slug !== '' ? $slug : $fallback);

        if ($candidate !== '') {
            return $candidate;
        }

        return sprintf('%s-%s', $context, Str::lower(Str::random(6)));
    }

    private function nextOrganizationId(string $slug): string
    {
        $candidate = 'org-'.$slug;

        if (! DB::table('organizations')->where('id', $candidate)->exists()) {
            return $candidate;
        }

        return $candidate.'-'.Str::lower(Str::random(4));
    }

    private function nextScopeId(string $slug): string
    {
        $candidate = 'scope-'.$slug;

        if (! DB::table('scopes')->where('id', $candidate)->exists()) {
            return $candidate;
        }

        return $candidate.'-'.Str::lower(Str::random(4));
    }
}
