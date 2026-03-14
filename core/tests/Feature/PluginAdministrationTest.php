<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PymeSec\Core\Plugins\PluginStateStore;
use Tests\TestCase;

class PluginAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_disable_a_plugin_from_the_shell(): void
    {
        $this->post('/core/plugins/hello-world/disable', [
            'principal_id' => 'principal-admin',
            'menu' => 'core.plugins',
        ])->assertRedirect(route('core.admin.index', ['principal_id' => 'principal-admin', 'menu' => 'core.plugins']))
            ->assertSessionHas('status');

        $effective = $this->app->make(PluginStateStore::class)->effectiveEnabled(config('plugins.enabled', []));

        $this->assertNotContains('hello-world', $effective);
    }

    public function test_platform_admin_cannot_disable_a_plugin_that_has_enabled_dependents(): void
    {
        $this->post('/core/plugins/actor-directory/disable', [
            'principal_id' => 'principal-admin',
            'menu' => 'core.plugins',
        ])->assertRedirect(route('core.admin.index', ['principal_id' => 'principal-admin', 'menu' => 'core.plugins']))
            ->assertSessionHas('error');

        $effective = $this->app->make(PluginStateStore::class)->effectiveEnabled(config('plugins.enabled', []));

        $this->assertContains('actor-directory', $effective);
    }
}
