<?php

namespace PymeSec\Core\FunctionalActors\Contracts;

use PymeSec\Core\FunctionalActors\FunctionalActorLink;
use PymeSec\Core\FunctionalActors\FunctionalActorReference;
use PymeSec\Core\FunctionalActors\FunctionalAssignment;

interface FunctionalActorServiceInterface
{
    /**
     * @return array<int, FunctionalActorReference>
     */
    public function actors(?string $organizationId = null, ?string $scopeId = null): array;

    public function findActor(string $actorId): ?FunctionalActorReference;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createActor(
        string $provider,
        string $kind,
        string $displayName,
        string $organizationId,
        ?string $scopeId = null,
        array $metadata = [],
        ?string $createdByPrincipalId = null,
    ): FunctionalActorReference;

    /**
     * @return array<int, FunctionalAssignment>
     */
    public function assignments(
        ?string $organizationId = null,
        ?string $scopeId = null,
        ?string $domainObjectType = null,
    ): array;

    /**
     * @return array<int, FunctionalAssignment>
     */
    public function assignmentsFor(
        string $domainObjectType,
        string $domainObjectId,
        string $organizationId,
        ?string $scopeId = null,
    ): array;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function assignActor(
        string $actorId,
        string $domainObjectType,
        string $domainObjectId,
        string $assignmentType,
        string $organizationId,
        ?string $scopeId = null,
        array $metadata = [],
        ?string $assignedByPrincipalId = null,
    ): FunctionalAssignment;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function syncSingleAssignment(
        string $actorId,
        string $domainObjectType,
        string $domainObjectId,
        string $assignmentType,
        string $organizationId,
        ?string $scopeId = null,
        array $metadata = [],
        ?string $assignedByPrincipalId = null,
    ): FunctionalAssignment;

    public function deactivateAssignment(
        string $assignmentId,
        ?string $deactivatedByPrincipalId = null,
    ): void;

    /**
     * @return array<int, FunctionalActorLink>
     */
    public function linksForPrincipal(string $principalId, ?string $organizationId = null): array;

    /**
     * @return array<int, FunctionalActorLink>
     */
    public function linksForActor(string $actorId): array;

    /**
     * @return array<int, FunctionalActorReference>
     */
    public function actorsForPrincipal(string $principalId, ?string $organizationId = null): array;

    public function linkPrincipal(
        string $principalId,
        string $actorId,
        string $organizationId,
        ?string $linkedByPrincipalId = null,
    ): FunctionalActorLink;
}
