<?php

namespace PymeSec\Core\Collaboration\Contracts;

interface CollaborationStoreInterface
{
    /**
     * @return array<int, array<string, string>>
     */
    public function externalCollaboratorsForSubject(string $ownerComponent, string $subjectType, string $subjectId): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function upsertExternalCollaborator(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        array $data,
    ): array;

    /**
     * @return array<string, string>|null
     */
    public function findExternalCollaborator(string $collaboratorId): ?array;

    /**
     * @return array<string, string>|null
     */
    public function updateExternalCollaboratorLifecycle(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $collaboratorId,
        string $lifecycleState,
        ?string $updatedByPrincipalId = null,
    ): ?array;

    /**
     * @return array<int, array<string, string>>
     */
    public function externalLinksForSubject(string $ownerComponent, string $subjectType, string $subjectId): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, string>, 1: string}
     */
    public function issueExternalLink(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        array $data,
    ): array;

    /**
     * @return array<string, string>|null
     */
    public function findExternalLink(string $linkId): ?array;

    /**
     * @return array<string, string>|null
     */
    public function resolveExternalLinkByToken(string $ownerComponent, string $subjectType, string $token): ?array;

    /**
     * @return array<string, string>|null
     */
    public function revokeExternalLink(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $linkId,
        ?string $revokedByPrincipalId = null,
    ): ?array;

    public function touchExternalLinkAccess(string $linkId): void;

    /**
     * @return array<string, string>|null
     */
    public function recordExternalLinkDelivery(string $linkId, string $status, ?string $error = null): ?array;

    /**
     * @return array<int, array<string, string>>
     */
    public function draftsForSubject(string $ownerComponent, string $subjectType, string $subjectId): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function createDraft(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        array $data,
    ): array;

    /**
     * @return array<string, string>|null
     */
    public function findDraft(string $draftId): ?array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function updateDraft(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $draftId,
        array $data,
    ): ?array;

    public function deleteDraft(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $draftId,
    ): void;

    /**
     * @return array<int, array<string, string>>
     */
    public function commentsForSubject(string $ownerComponent, string $subjectType, string $subjectId): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function addComment(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        array $data,
    ): array;

    /**
     * @return array<int, array<string, string>>
     */
    public function requestsForSubject(string $ownerComponent, string $subjectType, string $subjectId): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function createRequest(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        array $data,
    ): array;

    /**
     * @return array<string, string>|null
     */
    public function findRequest(string $requestId): ?array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function updateRequest(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $requestId,
        array $data,
    ): ?array;
}
