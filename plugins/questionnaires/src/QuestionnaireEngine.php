<?php

namespace PymeSec\Plugins\Questionnaires;

use Illuminate\Validation\Rule;
use PymeSec\Core\Questionnaires\Contracts\QuestionnaireEngineInterface;

class QuestionnaireEngine implements QuestionnaireEngineInterface
{
    /**
     * @return array<string, string>
     */
    public function responseTypes(): array
    {
        return [
            'yes-no' => 'Yes / no',
            'long-text' => 'Long text',
            'date' => 'Date',
            'evidence-list' => 'Evidence list',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function responseStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'sent' => 'Sent',
            'submitted' => 'Submitted',
            'under-review' => 'Under review',
            'accepted' => 'Accepted',
            'needs-follow-up' => 'Needs follow-up',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attachmentModes(): array
    {
        return [
            'none' => 'No attachment requested',
            'supporting-document' => 'Supporting document',
            'supporting-evidence' => 'Supporting evidence',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function responseTypeKeys(): array
    {
        return array_keys($this->responseTypes());
    }

    /**
     * @return array<int, string>
     */
    public function responseStatusKeys(): array
    {
        return array_keys($this->responseStatuses());
    }

    /**
     * @return array<int, string>
     */
    public function attachmentModeKeys(): array
    {
        return array_keys($this->attachmentModes());
    }

    public function responseTypeLabel(string $type): string
    {
        return $this->responseTypes()[$type] ?? $this->humanize($type);
    }

    public function responseStatusLabel(string $status): string
    {
        return $this->responseStatuses()[$status] ?? $this->humanize($status);
    }

    public function attachmentModeLabel(string $mode): string
    {
        return $this->attachmentModes()[$mode] ?? $this->humanize($mode);
    }

    /**
     * @return array<int, mixed>
     */
    public function answerValidationRules(string $responseType): array
    {
        return match ($responseType) {
            'yes-no' => ['required', 'string', Rule::in(['yes', 'no', 'not-applicable'])],
            'date' => ['required', 'date'],
            default => ['required', 'string', 'max:4000'],
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{title: string, items: array<int, array<string, mixed>>}>
     */
    public function groupItemsBySection(array $items, string $defaultSection = 'General'): array
    {
        $sections = [];

        foreach ($items as $item) {
            $title = is_string($item['section_title'] ?? null) && trim((string) $item['section_title']) !== ''
                ? trim((string) $item['section_title'])
                : $defaultSection;

            if (! isset($sections[$title])) {
                $sections[$title] = [
                    'title' => $title,
                    'items' => [],
                ];
            }

            $sections[$title]['items'][] = $item;
        }

        return array_values($sections);
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $value));
    }
}
