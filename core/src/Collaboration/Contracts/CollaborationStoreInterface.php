<?php

namespace PymeSec\Core\Collaboration\Contracts;

interface CollaborationStoreInterface
{
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
