<?php

namespace Tests\Feature;

use Tests\TestCase;

class AssetCatalogTranslationsTest extends TestCase
{
    public function test_asset_catalog_publishes_the_expected_locale_catalogues(): void
    {
        $basePath = dirname(base_path()).'/plugins/asset-catalog';
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
                sprintf('Locale [%s] must expose the same asset catalog translation keys as English.', $locale),
            );

            $this->assertNotSame(
                $english['plugin.asset-catalog.screen.catalog.title'],
                $catalogue['plugin.asset-catalog.screen.catalog.title'],
                sprintf('Locale [%s] must not reuse the English asset catalog title verbatim.', $locale),
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
