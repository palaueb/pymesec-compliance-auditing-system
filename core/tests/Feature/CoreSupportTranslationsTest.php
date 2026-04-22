<?php

namespace Tests\Feature;

use Tests\TestCase;

class CoreSupportTranslationsTest extends TestCase
{
    public function test_core_support_catalogues_publish_the_expected_locale_sets(): void
    {
        $basePath = base_path();
        $english = $this->decodeJson($basePath.'/resources/support/en.json');
        $englishKeys = array_keys($english);
        $englishPayload = json_encode($english, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        foreach (['es', 'fr', 'de'] as $locale) {
            $catalogue = $this->decodeJson($basePath.'/resources/support/'.$locale.'.json');

            $this->assertSame(
                $englishKeys,
                array_keys($catalogue),
                sprintf('Locale [%s] must expose the same core support keys as English.', $locale),
            );

            $this->assertNotSame(
                $englishPayload,
                json_encode($catalogue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                sprintf('Locale [%s] must not copy the English core support catalogue verbatim.', $locale),
            );

            $this->assertSame(
                count($english['guide'] ?? []),
                count($catalogue['guide'] ?? []),
                sprintf('Locale [%s] must keep the same number of core support guide entries as English.', $locale),
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
