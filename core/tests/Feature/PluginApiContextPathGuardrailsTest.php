<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginApiContextPathGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_plugin_api_routes_do_not_use_fragile_repo_relative_api_context_paths(): void
    {
        $routeFiles = glob(base_path('../plugins/*/routes/api.php')) ?: [];

        $this->assertNotEmpty($routeFiles, 'Expected plugin API route files to exist.');

        foreach ($routeFiles as $routeFile) {
            $contents = (string) file_get_contents($routeFile);

            $this->assertStringNotContainsString(
                "dirname(__DIR__, 3).'/core/routes/api_context.php'",
                $contents,
                sprintf('Plugin API route file [%s] uses a fragile core path include.', $routeFile),
            );

            if (str_contains($contents, 'api_context.php')) {
                $this->assertStringContainsString(
                    "require base_path('routes/api_context.php');",
                    $contents,
                    sprintf('Plugin API route file [%s] must include api_context via base_path().', $routeFile),
                );
            }
        }
    }
}
