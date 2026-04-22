<?php

namespace Tests\Feature;

use Tests\TestCase;

class ControlsCatalogTranslationsTest extends TestCase
{
    public function test_controls_catalog_publishes_the_expected_locale_catalogues(): void
    {
        $basePath = dirname(base_path()).'/plugins/controls-catalog';
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
                sprintf('Locale [%s] must expose the same controls catalog translation keys as English.', $locale),
            );

            $this->assertNotSame(
                $english['plugin.controls-catalog.screen.catalog.subtitle'],
                $catalogue['plugin.controls-catalog.screen.catalog.subtitle'],
                sprintf('Locale [%s] must not reuse the English controls catalog subtitle verbatim.', $locale),
            );
        }

        $englishSupport = $this->decodeJson($basePath.'/resources/support/en.json');
        $englishSupportKeys = array_keys($englishSupport);

        foreach (['es', 'fr', 'de'] as $locale) {
            $support = $this->decodeJson($basePath.'/resources/support/'.$locale.'.json');

            $this->assertSame(
                $englishSupportKeys,
                array_keys($support),
                sprintf('Locale [%s] must expose the same controls support keys as English.', $locale),
            );

            $this->assertNotSame(
                $englishSupport['guide'][0]['title'],
                $support['guide'][0]['title'],
                sprintf('Locale [%s] must not reuse the English controls guide title verbatim.', $locale),
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
