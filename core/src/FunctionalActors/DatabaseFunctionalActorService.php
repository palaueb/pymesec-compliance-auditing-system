<?php

namespace PymeSec\Core\FunctionalActors;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;

class DatabaseFunctionalActorService implements FunctionalActorServiceInterface
{
    public function __construct(
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
    ) {
    }

    public function actors(?string $organizationId = null, ?string $scopeId = null): array
    {
        $query = DB::table('functional_actors')
            ->where('is_active', true)
            ->orderBy('display_name');

        if (is_string($organizationId) && $organizationId !== '') {
            $query->where('organization_id', $organizationId);
        }

        if (is_string($scopeId) && $scopeId !== '') {
            $query->where(function ($inner) use ($scopeId): void {
                $inner->whereNull('scope_id')->orWhere('scope_id', $scopeId);
            });
        }

        return $query->get()
            ->map(fn ($record): FunctionalActorReference => $this->mapActor($record))
            ->all();
    }

    public function findActor(string $actorId): ?FunctionalActorReference
    {
        $record = DB::table('functional_actors')
            ->where('id', $actorId)
            ->where('is_active', true)
            ->first();

        return $record !== null ? $this->mapActor($record) : null;
    }

    public function createActor(
        string $provider,
        string $kind,
        string $displayName,
        string $organizationId,
        ?string $scopeId = null,
        array $metadata = [],
        ?string $createdByPrincipalId = null,
    ): FunctionalActorReference {
        $id = (string) Str::ulid();

        DB::table('functional_actors')->insert([
            'id' => $id,
            'provider' => $provider,
            'kind' => $kind,
            'display_name' => $displayName,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'metadata' => $this->encodeJson($metadata),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $actor = new FunctionalActorReference(
            id: $id,
            provider: $provider,
            kind: $kind,
            displayName: $displayName,
            organizationId: $organizationId,
            scopeId: $scopeId,
            metadata: $metadata,
        );

        $this->audit->record(new AuditRecordData(
            eventType: 'core.functional-actors.actor.created',
            outcome: 'success',
            originComponent: 'core',
            principalId: $createdByPrincipalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: 'functional_actor',
            targetId: $id,
            summary: [
                'provider' => $provider,
                'kind' => $kind,
                'display_name' => $displayName,
            ],
            executionOrigin: 'functional-actors',
        ));

        $this->events->publish(new PublicEvent(
            name: 'core.functional-actors.actor.created',
            originComponent: 'core',
            organizationId: $organizationId,
            scopeId: $scopeId,
            payload: [
                'functional_actor_id' => $id,
                'provider' => $provider,
                'kind' => $kind,
            ],
        ));

        return $actor;
    }

    public function assignments(
        ?string $organizationId = null,
        ?string $scopeId = null,
        ?string $domainObjectType = null,
    ): array {
        $query = DB::table('functional_assignments')
            ->where('is_active', true)
            ->orderBy('domain_object_type')
            ->orderBy('domain_object_id')
            ->orderBy('assignment_type');

        if (is_string($organizationId) && $organizationId !== '') {
            $query->where('organization_id', $organizationId);
        }

        if (is_string($scopeId) && $scopeId !== '') {
            $query->where(function ($inner) use ($scopeId): void {
                $inner->whereNull('scope_id')->orWhere('scope_id', $scopeId);
            });
        }

        if (is_string($domainObjectType) && $domainObjectType !== '') {
            $query->where('domain_object_type', $domainObjectType);
        }

        return $query->get()
            ->map(fn ($record): FunctionalAssignment => $this->mapAssignment($record))
            ->all();
    }

    public function assignmentsFor(
        string $domainObjectType,
        string $domainObjectId,
        string $organizationId,
        ?string $scopeId = null,
    ): array {
        $query = DB::table('functional_assignments')
            ->where('domain_object_type', $domainObjectType)
            ->where('domain_object_id', $domainObjectId)
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('assignment_type')
            ->orderBy('id');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where(function ($inner) use ($scopeId): void {
                $inner->whereNull('scope_id')->orWhere('scope_id', $scopeId);
            });
        }

        return $query->get()
            ->map(fn ($record): FunctionalAssignment => $this->mapAssignment($record))
            ->all();
    }

    public function assignActor(
        string $actorId,
        string $domainObjectType,
        string $domainObjectId,
        string $assignmentType,
        string $organizationId,
        ?string $scopeId = null,
        array $metadata = [],
        ?string $assignedByPrincipalId = null,
    ): FunctionalAssignment {
        $existing = DB::table('functional_assignments')
            ->where('functional_actor_id', $actorId)
            ->where('domain_object_type', $domainObjectType)
            ->where('domain_object_id', $domainObjectId)
            ->where('assignment_type', $assignmentType)
            ->first();

        if ($existing !== null) {
            DB::table('functional_assignments')
                ->where('id', $existing->id)
                ->update([
                    'organization_id' => $organizationId,
                    'scope_id' => $scopeId,
                    'metadata' => $this->encodeJson($metadata),
                    'is_active' => true,
                    'updated_at' => now(),
                ]);

            $assignmentId = (string) $existing->id;
        } else {
            $assignmentId = (string) Str::ulid();

            DB::table('functional_assignments')->insert([
                'id' => $assignmentId,
                'functional_actor_id' => $actorId,
                'domain_object_type' => $domainObjectType,
                'domain_object_id' => $domainObjectId,
                'assignment_type' => $assignmentType,
                'organization_id' => $organizationId,
                'scope_id' => $scopeId,
                'metadata' => $this->encodeJson($metadata),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $assignment = new FunctionalAssignment(
            id: $assignmentId,
            functionalActorId: $actorId,
            domainObjectType: $domainObjectType,
            domainObjectId: $domainObjectId,
            assignmentType: $assignmentType,
            organizationId: $organizationId,
            scopeId: $scopeId,
            metadata: $metadata,
        );

        $this->audit->record(new AuditRecordData(
            eventType: 'core.functional-actors.assignment.created',
            outcome: 'success',
            originComponent: 'core',
            principalId: $assignedByPrincipalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: $domainObjectType,
            targetId: $domainObjectId,
            summary: [
                'assignment_type' => $assignmentType,
                'functional_actor_id' => $actorId,
            ],
            executionOrigin: 'functional-actors',
        ));

        $this->events->publish(new PublicEvent(
            name: 'core.functional-actors.assignment.created',
            originComponent: 'core',
            organizationId: $organizationId,
            scopeId: $scopeId,
            payload: [
                'functional_actor_id' => $actorId,
                'domain_object_type' => $domainObjectType,
                'domain_object_id' => $domainObjectId,
                'assignment_type' => $assignmentType,
            ],
        ));

        return $assignment;
    }

    public function linksForPrincipal(string $principalId, ?string $organizationId = null): array
    {
        $query = DB::table('principal_functional_actor_links')
            ->where('principal_id', $principalId)
            ->orderBy('organization_id')
            ->orderBy('functional_actor_id');

        if (is_string($organizationId) && $organizationId !== '') {
            $query->where('organization_id', $organizationId);
        }

        return $query->get()
            ->map(fn ($record): FunctionalActorLink => $this->mapLink($record))
            ->all();
    }

    public function linksForActor(string $actorId): array
    {
        return DB::table('principal_functional_actor_links')
            ->where('functional_actor_id', $actorId)
            ->orderBy('principal_id')
            ->get()
            ->map(fn ($record): FunctionalActorLink => $this->mapLink($record))
            ->all();
    }

    public function actorsForPrincipal(string $principalId, ?string $organizationId = null): array
    {
        $query = DB::table('functional_actors')
            ->join('principal_functional_actor_links', 'principal_functional_actor_links.functional_actor_id', '=', 'functional_actors.id')
            ->where('functional_actors.is_active', true)
            ->where('principal_functional_actor_links.principal_id', $principalId)
            ->orderBy('functional_actors.display_name');

        if (is_string($organizationId) && $organizationId !== '') {
            $query->where('principal_functional_actor_links.organization_id', $organizationId);
        }

        return $query->get([
            'functional_actors.id',
            'functional_actors.provider',
            'functional_actors.kind',
            'functional_actors.display_name',
            'functional_actors.organization_id',
            'functional_actors.scope_id',
            'functional_actors.metadata',
        ])->map(fn ($record): FunctionalActorReference => $this->mapActor($record))
            ->all();
    }

    public function linkPrincipal(
        string $principalId,
        string $actorId,
        string $organizationId,
        ?string $linkedByPrincipalId = null,
    ): FunctionalActorLink {
        $existing = DB::table('principal_functional_actor_links')
            ->where('principal_id', $principalId)
            ->where('functional_actor_id', $actorId)
            ->where('organization_id', $organizationId)
            ->first();

        if ($existing !== null) {
            return $this->mapLink($existing);
        }

        $id = (string) Str::ulid();

        DB::table('principal_functional_actor_links')->insert([
            'id' => $id,
            'principal_id' => $principalId,
            'functional_actor_id' => $actorId,
            'organization_id' => $organizationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $link = new FunctionalActorLink(
            id: $id,
            principalId: $principalId,
            functionalActorId: $actorId,
            organizationId: $organizationId,
        );

        $this->audit->record(new AuditRecordData(
            eventType: 'core.functional-actors.principal-linked',
            outcome: 'success',
            originComponent: 'core',
            principalId: $linkedByPrincipalId,
            organizationId: $organizationId,
            targetType: 'functional_actor',
            targetId: $actorId,
            summary: [
                'principal_id' => $principalId,
            ],
            executionOrigin: 'functional-actors',
        ));

        $this->events->publish(new PublicEvent(
            name: 'core.functional-actors.principal-linked',
            originComponent: 'core',
            organizationId: $organizationId,
            payload: [
                'principal_id' => $principalId,
                'functional_actor_id' => $actorId,
            ],
        ));

        return $link;
    }

    private function mapActor(object $record): FunctionalActorReference
    {
        return new FunctionalActorReference(
            id: (string) $record->id,
            provider: (string) $record->provider,
            kind: (string) $record->kind,
            displayName: (string) $record->display_name,
            organizationId: (string) $record->organization_id,
            scopeId: is_string($record->scope_id ?? null) && $record->scope_id !== '' ? $record->scope_id : null,
            metadata: $this->decodeJson($record->metadata ?? null),
        );
    }

    private function mapAssignment(object $record): FunctionalAssignment
    {
        return new FunctionalAssignment(
            id: (string) $record->id,
            functionalActorId: (string) $record->functional_actor_id,
            domainObjectType: (string) $record->domain_object_type,
            domainObjectId: (string) $record->domain_object_id,
            assignmentType: (string) $record->assignment_type,
            organizationId: (string) $record->organization_id,
            scopeId: is_string($record->scope_id ?? null) && $record->scope_id !== '' ? $record->scope_id : null,
            metadata: $this->decodeJson($record->metadata ?? null),
        );
    }

    private function mapLink(object $record): FunctionalActorLink
    {
        return new FunctionalActorLink(
            id: (string) $record->id,
            principalId: (string) $record->principal_id,
            functionalActorId: (string) $record->functional_actor_id,
            organizationId: (string) $record->organization_id,
        );
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
