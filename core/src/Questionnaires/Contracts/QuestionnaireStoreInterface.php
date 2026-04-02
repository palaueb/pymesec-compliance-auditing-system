<?php

namespace PymeSec\Core\Questionnaires\Contracts;

interface QuestionnaireStoreInterface
{
    /**
     * @return array<int, array<string, string>>
     */
    public function allTemplates(
        string $organizationId,
        ?string $scopeId,
        string $ownerComponent,
        string $subjectType,
        ?string $profileId = null,
    ): array;

    /**
     * @return array<string, string>|null
     */
    public function findTemplate(string $templateId): ?array;

    /**
     * @return array<int, array<string, string>>
     */
    public function templateItems(string $templateId): array;

    /**
     * @return array<int, array<string, string>>
     */
    public function itemsForSubject(string $ownerComponent, string $subjectType, string $subjectId): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function addSubjectItem(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        array $data,
    ): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function updateSubjectItem(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $itemId,
        array $data,
    ): ?array;

    /**
     * @return array<string, string>|null
     */
    public function findSubjectItem(string $itemId): ?array;

    /**
     * @return array<string, string>|null
     */
    public function submitSubjectAnswer(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $itemId,
        string $answerText,
    ): ?array;

    public function applyTemplateToSubject(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        string $templateId,
    ): int;

    /**
     * @return array<int, array<string, string>>
     */
    public function answerLibraryEntries(
        string $organizationId,
        ?string $scopeId,
        string $ownerComponent,
        string $subjectType,
        string $responseType,
        ?string $prompt = null,
        int $limit = 5,
    ): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function saveAnswerLibraryEntry(
        string $organizationId,
        ?string $scopeId,
        string $ownerComponent,
        string $subjectType,
        array $data,
    ): array;

    /**
     * @return array<int, array<string, string>>
     */
    public function brokeredRequestsForSubject(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
    ): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function issueBrokeredRequest(
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
    public function findBrokeredRequest(string $requestId): ?array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function updateBrokeredRequest(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $requestId,
        array $data,
    ): ?array;

    /**
     * @return array<string, string>|null
     */
    public function reviewSubjectItem(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $itemId,
        string $responseStatus,
        ?string $reviewNotes,
        ?string $reviewedByPrincipalId,
    ): ?array;
}
