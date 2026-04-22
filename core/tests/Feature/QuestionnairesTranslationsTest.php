<?php

namespace Tests\Feature;

use PymeSec\Core\Questionnaires\Contracts\QuestionnaireEngineInterface;
use Tests\TestCase;

class QuestionnairesTranslationsTest extends TestCase
{
    public function test_questionnaire_engine_publishes_locale_catalogues_and_labels(): void
    {
        $basePath = dirname(base_path()).'/plugins/questionnaires';
        $manifest = $this->decodeJson($basePath.'/plugin.json');

        $this->assertSame(
            ['en', 'es', 'fr', 'de'],
            $manifest['translations']['supported_locales'] ?? null,
        );

        $english = $this->decodeJson($basePath.'/resources/lang/en.json');
        $englishKeys = array_keys($english);
        $englishPayload = json_encode($english, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        foreach (['es', 'fr', 'de'] as $locale) {
            $catalogue = $this->decodeJson($basePath.'/resources/lang/'.$locale.'.json');

            $this->assertSame(
                $englishKeys,
                array_keys($catalogue),
                sprintf('Locale [%s] must expose the same questionnaire translation keys as English.', $locale),
            );

            $this->assertNotSame(
                $englishPayload,
                json_encode($catalogue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                sprintf('Locale [%s] must not copy the English questionnaire catalogue verbatim.', $locale),
            );
        }

        $engine = $this->app->make(QuestionnaireEngineInterface::class);
        $previousLocale = app()->getLocale();

        try {
            foreach (['es', 'fr', 'de'] as $locale) {
                $catalogue = $this->decodeJson($basePath.'/resources/lang/'.$locale.'.json');
                app()->setLocale($locale);

                $this->assertSame(
                    [
                        'yes-no' => $catalogue['plugin.questionnaires.response_type.yes_no'],
                        'long-text' => $catalogue['plugin.questionnaires.response_type.long_text'],
                        'date' => $catalogue['plugin.questionnaires.response_type.date'],
                        'evidence-list' => $catalogue['plugin.questionnaires.response_type.evidence_list'],
                    ],
                    $engine->responseTypes(),
                );

                $this->assertSame(
                    [
                        'draft' => $catalogue['plugin.questionnaires.response_status.draft'],
                        'sent' => $catalogue['plugin.questionnaires.response_status.sent'],
                        'submitted' => $catalogue['plugin.questionnaires.response_status.submitted'],
                        'under-review' => $catalogue['plugin.questionnaires.response_status.under_review'],
                        'accepted' => $catalogue['plugin.questionnaires.response_status.accepted'],
                        'needs-follow-up' => $catalogue['plugin.questionnaires.response_status.needs_follow_up'],
                    ],
                    $engine->responseStatuses(),
                );

                $this->assertSame(
                    [
                        'none' => $catalogue['plugin.questionnaires.attachment_mode.none'],
                        'supporting-document' => $catalogue['plugin.questionnaires.attachment_mode.supporting_document'],
                        'supporting-evidence' => $catalogue['plugin.questionnaires.attachment_mode.supporting_evidence'],
                    ],
                    $engine->attachmentModes(),
                );
            }
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
