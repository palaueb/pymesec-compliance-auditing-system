<?php

namespace Tests\Feature;

use PymeSec\Core\Collaboration\Contracts\CollaborationEngineInterface;
use Tests\TestCase;

class CollaborationTranslationsTest extends TestCase
{
    public function test_collaboration_engine_publishes_locale_catalogues_and_labels(): void
    {
        $basePath = dirname(base_path()).'/plugins/collaboration';
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
                sprintf('Locale [%s] must expose the same collaboration translation keys as English.', $locale),
            );

            $this->assertNotSame(
                $englishPayload,
                json_encode($catalogue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                sprintf('Locale [%s] must not copy the English collaboration catalogue verbatim.', $locale),
            );
        }

        $engine = $this->app->make(CollaborationEngineInterface::class);
        $previousLocale = app()->getLocale();

        try {
            foreach (['es', 'fr', 'de'] as $locale) {
                $catalogue = $this->decodeJson($basePath.'/resources/lang/'.$locale.'.json');
                app()->setLocale($locale);

                $this->assertSame(
                    [
                        'active' => $catalogue['plugin.collaboration.collaborator_lifecycle.active'],
                        'blocked' => $catalogue['plugin.collaboration.collaborator_lifecycle.blocked'],
                    ],
                    $engine->collaboratorLifecycleStates(),
                );

                $this->assertSame(
                    [
                        'comment' => $catalogue['plugin.collaboration.draft_type.comment'],
                        'request' => $catalogue['plugin.collaboration.draft_type.request'],
                    ],
                    $engine->draftTypes(),
                );

                $this->assertSame(
                    [
                        'open' => $catalogue['plugin.collaboration.request_status.open'],
                        'in-progress' => $catalogue['plugin.collaboration.request_status.in_progress'],
                        'waiting' => $catalogue['plugin.collaboration.request_status.waiting'],
                        'done' => $catalogue['plugin.collaboration.request_status.done'],
                        'cancelled' => $catalogue['plugin.collaboration.request_status.cancelled'],
                    ],
                    $engine->requestStatuses(),
                );

                $this->assertSame(
                    [
                        'low' => $catalogue['plugin.collaboration.request_priority.low'],
                        'normal' => $catalogue['plugin.collaboration.request_priority.normal'],
                        'high' => $catalogue['plugin.collaboration.request_priority.high'],
                        'urgent' => $catalogue['plugin.collaboration.request_priority.urgent'],
                    ],
                    $engine->requestPriorities(),
                );

                $this->assertSame(
                    [
                        'review' => $catalogue['plugin.collaboration.handoff_state.review'],
                        'remediation' => $catalogue['plugin.collaboration.handoff_state.remediation'],
                        'approval' => $catalogue['plugin.collaboration.handoff_state.approval'],
                        'closed-loop' => $catalogue['plugin.collaboration.handoff_state.closed_loop'],
                    ],
                    $engine->handoffStates(),
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
