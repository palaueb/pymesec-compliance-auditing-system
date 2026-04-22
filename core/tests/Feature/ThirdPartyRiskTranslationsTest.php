<?php

namespace Tests\Feature;

use Tests\TestCase;

class ThirdPartyRiskTranslationsTest extends TestCase
{
    public function test_third_party_risk_publishes_locale_catalogues(): void
    {
        $basePath = dirname(base_path()).'/plugins/third-party-risk';
        $manifest = $this->decodeJson($basePath.'/plugin.json');
        $english = $this->decodeJson($basePath.'/resources/lang/en.json');

        $this->assertSame(['en', 'es', 'fr', 'de'], $manifest['translations']['supported_locales'] ?? null);
        $this->assertSame(['en', 'es', 'fr', 'de'], $manifest['support']['supported_locales'] ?? null);

        foreach (['es', 'fr', 'de'] as $locale) {
            $catalogue = $this->decodeJson($basePath.'/resources/lang/'.$locale.'.json');

            $this->assertArrayHasKey('plugin.third-party-risk.nav.root', $catalogue);
            $this->assertArrayHasKey('plugin.third-party-risk.screen.register.title', $catalogue);
            $this->assertArrayHasKey('All vendors', $catalogue);
            $this->assertArrayHasKey('Open', $catalogue);

            $this->assertNotSame(
                $english['plugin.third-party-risk.nav.root'] ?? null,
                $catalogue['plugin.third-party-risk.nav.root'] ?? null,
                sprintf('Locale [%s] must not reuse the English third-party-risk menu label verbatim.', $locale),
            );

            $this->assertNotSame(
                $english['plugin.third-party-risk.screen.register.title'] ?? null,
                $catalogue['plugin.third-party-risk.screen.register.title'] ?? null,
                sprintf('Locale [%s] must not reuse the English third-party-risk screen title verbatim.', $locale),
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
