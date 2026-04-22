<?php

namespace Tests\Feature;

use Tests\TestCase;

class DataFlowsPrivacyTranslationsTest extends TestCase
{
    public function test_data_flows_privacy_publishes_the_expected_locale_catalogues(): void
    {
        $basePath = dirname(base_path()).'/plugins/data-flows-privacy';
        $manifest = $this->decodeJson($basePath.'/plugin.json');

        $this->assertSame(
            ['en', 'es', 'fr', 'de'],
            $manifest['translations']['supported_locales'] ?? null,
        );

        $this->assertSame(
            ['en', 'es', 'fr', 'de'],
            $manifest['support']['supported_locales'] ?? null,
        );

        $english = $this->decodeJson($basePath.'/resources/lang/en.json');
        $englishKeys = array_keys($english);

        foreach (['es', 'fr', 'de'] as $locale) {
            $catalogue = $this->decodeJson($basePath.'/resources/lang/'.$locale.'.json');

            $this->assertSame(
                $englishKeys,
                array_keys($catalogue),
                sprintf('Locale [%s] must expose the same data-flows privacy translation keys as English.', $locale),
            );

            $this->assertNotSame(
                $english['plugin.data-flows-privacy.screen.register.subtitle'],
                $catalogue['plugin.data-flows-privacy.screen.register.subtitle'],
                sprintf('Locale [%s] must not reuse the English data flows register subtitle verbatim.', $locale),
            );

            $this->assertNotSame(
                $english['plugin.data-flows-privacy.screen.activities.subtitle'],
                $catalogue['plugin.data-flows-privacy.screen.activities.subtitle'],
                sprintf('Locale [%s] must not reuse the English processing activities subtitle verbatim.', $locale),
            );
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
