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
            'yes-no' => $this->translatedLabel('plugin.questionnaires.response_type.yes_no', 'Yes / no'),
            'long-text' => $this->translatedLabel('plugin.questionnaires.response_type.long_text', 'Long text'),
            'date' => $this->translatedLabel('plugin.questionnaires.response_type.date', 'Date'),
            'evidence-list' => $this->translatedLabel('plugin.questionnaires.response_type.evidence_list', 'Evidence list'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function responseStatuses(): array
    {
        return [
            'draft' => $this->translatedLabel('plugin.questionnaires.response_status.draft', 'Draft'),
            'sent' => $this->translatedLabel('plugin.questionnaires.response_status.sent', 'Sent'),
            'submitted' => $this->translatedLabel('plugin.questionnaires.response_status.submitted', 'Submitted'),
            'under-review' => $this->translatedLabel('plugin.questionnaires.response_status.under_review', 'Under review'),
            'accepted' => $this->translatedLabel('plugin.questionnaires.response_status.accepted', 'Accepted'),
            'needs-follow-up' => $this->translatedLabel('plugin.questionnaires.response_status.needs_follow_up', 'Needs follow-up'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attachmentModes(): array
    {
        return [
            'none' => $this->translatedLabel('plugin.questionnaires.attachment_mode.none', 'No attachment requested'),
            'supporting-document' => $this->translatedLabel('plugin.questionnaires.attachment_mode.supporting_document', 'Supporting document'),
            'supporting-evidence' => $this->translatedLabel('plugin.questionnaires.attachment_mode.supporting_evidence', 'Supporting evidence'),
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

    private function translatedLabel(string $key, string $fallback): string
    {
        $catalogue = $this->catalogue();

        return is_string($catalogue[$key] ?? null) && $catalogue[$key] !== ''
            ? $catalogue[$key]
            : $fallback;
    }

    /**
     * @return array<string, string>
     */
    private function catalogue(): array
    {
        static $cache = [];

        $locale = (string) app()->getLocale();

        if (isset($cache[$locale])) {
            return $cache[$locale];
        }

        $basePath = dirname(__DIR__).'/resources/lang';
        $english = $this->loadCatalogue($basePath.'/en.json');
        $localized = $locale === 'en' ? [] : $this->loadCatalogue($basePath.'/'.$locale.'.json');

        return $cache[$locale] = array_replace($english, $localized);
    }

    /**
     * @return array<string, string>
     */
    private function loadCatalogue(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? array_filter($decoded, static fn (mixed $value): bool => is_string($value)) : [];
    }
}
