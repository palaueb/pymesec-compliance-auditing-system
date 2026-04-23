<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PluginUiTranslationsTest extends TestCase
{
    #[DataProvider('uiCatalogProvider')]
    public function test_plugin_ui_catalogues_publish_the_expected_locale_sets(string $pluginId): void
    {
        $basePath = dirname(base_path()).'/plugins/'.$pluginId;
        $english = $this->decodeJson($basePath.'/resources/lang/en.json');
        $englishKeys = array_keys($english);
        $englishPayload = json_encode($english, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        foreach (['es', 'fr', 'de'] as $locale) {
            $ui = $this->decodeJson($basePath.'/resources/lang/'.$locale.'.json');

            $this->assertSame(
                $englishKeys,
                array_keys($ui),
                sprintf('Locale [%s] for plugin [%s] must expose the same UI keys as English.', $locale, $pluginId),
            );

            $this->assertNotSame(
                $englishPayload,
                json_encode($ui, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                sprintf('Locale [%s] for plugin [%s] must not copy the English UI catalogue verbatim.', $locale, $pluginId),
            );
        }
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function uiCatalogProvider(): array
    {
        return [
            ['assessments-audits'],
            ['policy-exceptions'],
            ['findings-remediation'],
        ];
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
