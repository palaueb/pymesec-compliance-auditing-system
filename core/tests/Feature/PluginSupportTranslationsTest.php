<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PluginSupportTranslationsTest extends TestCase
{
    #[DataProvider('supportCatalogProvider')]
    public function test_plugin_support_catalogues_publish_the_expected_locale_sets(string $pluginId): void
    {
        $basePath = dirname(base_path()).'/plugins/'.$pluginId;
        $manifest = $this->decodeJson($basePath.'/plugin.json');

        $this->assertSame(
            ['en', 'es', 'fr', 'de'],
            $manifest['support']['supported_locales'] ?? null,
            sprintf('Plugin [%s] must declare English, Spanish, French, and German support locales.', $pluginId),
        );

        $english = $this->decodeJson($basePath.'/resources/support/en.json');
        $englishKeys = array_keys($english);
        $englishPayload = json_encode($english, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        foreach (['es', 'fr', 'de'] as $locale) {
            $support = $this->decodeJson($basePath.'/resources/support/'.$locale.'.json');

            $this->assertSame(
                $englishKeys,
                array_keys($support),
                sprintf('Locale [%s] for plugin [%s] must expose the same support keys as English.', $locale, $pluginId),
            );

            $this->assertNotSame(
                $englishPayload,
                json_encode($support, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                sprintf('Locale [%s] for plugin [%s] must not copy the English support catalogue verbatim.', $locale, $pluginId),
            );
        }
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function supportCatalogProvider(): array
    {
        return [
            ['assessments-audits'],
            ['automation-catalog'],
            ['actor-directory'],
            ['asset-catalog'],
            ['collaboration'],
            ['continuity-bcm'],
            ['data-flows-privacy'],
            ['evidence-management'],
            ['findings-remediation'],
            ['identity-ldap'],
            ['identity-local'],
            ['policy-exceptions'],
            ['questionnaires'],
            ['risk-management'],
            ['third-party-risk'],
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
