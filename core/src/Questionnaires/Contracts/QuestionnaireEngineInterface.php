<?php

namespace PymeSec\Core\Questionnaires\Contracts;

interface QuestionnaireEngineInterface
{
    /**
     * @return array<string, string>
     */
    public function responseTypes(): array;

    /**
     * @return array<string, string>
     */
    public function responseStatuses(): array;

    /**
     * @return array<string, string>
     */
    public function attachmentModes(): array;

    /**
     * @return array<int, string>
     */
    public function responseTypeKeys(): array;

    /**
     * @return array<int, string>
     */
    public function responseStatusKeys(): array;

    /**
     * @return array<int, string>
     */
    public function attachmentModeKeys(): array;

    public function responseTypeLabel(string $type): string;

    public function responseStatusLabel(string $status): string;

    public function attachmentModeLabel(string $mode): string;

    /**
     * @return array<int, mixed>
     */
    public function answerValidationRules(string $responseType): array;

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{title: string, items: array<int, array<string, mixed>>}>
     */
    public function groupItemsBySection(array $items, string $defaultSection = 'General'): array;
}
