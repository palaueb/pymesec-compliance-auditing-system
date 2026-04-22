<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrameworkIso27001TranslationsTest extends TestCase
{
    public function test_iso27001_framework_pack_publishes_the_expected_locale_catalogues(): void
    {
        $basePath = dirname(base_path()).'/plugins/framework-iso27001';
        $manifest = $this->decodeJson($basePath.'/plugin.json');

        $this->assertSame(
            ['en', 'es', 'fr', 'de'],
            $manifest['translations']['supported_locales'] ?? null,
        );

        $english = $this->decodeJson($basePath.'/resources/lang/en.json');
        $englishKeys = array_keys($english);

        foreach (['es', 'fr', 'de'] as $locale) {
            $catalogue = $this->decodeJson($basePath.'/resources/lang/'.$locale.'.json');

            $this->assertSame(
                $englishKeys,
                array_keys($catalogue),
                sprintf('Locale [%s] must expose the same ISO 27001 translation keys as English.', $locale),
            );

            $this->assertNotSame(
                $english['plugin.framework-iso27001.framework.description'],
                $catalogue['plugin.framework-iso27001.framework.description'],
                sprintf('Locale [%s] must not reuse the English framework description verbatim.', $locale),
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
