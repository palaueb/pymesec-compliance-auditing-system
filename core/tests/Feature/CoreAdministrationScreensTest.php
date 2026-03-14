<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreAdministrationScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_core_screens_render_inside_the_shell(): void
    {
        $screens = [
            'core.platform' => 'Platform Overview',
            'core.plugins' => 'Installed Modules',
            'core.permissions' => 'Permission Catalog',
            'core.tenancy' => 'Tenant Structure',
            'core.audit' => 'Audit Trail',
            'core.functional-actors' => 'Functional Directory',
        ];

        foreach ($screens as $menu => $title) {
            $this->get(sprintf('/admin?menu=%s&principal_id=principal-admin', $menu))
                ->assertOk()
                ->assertSee($title)
                ->assertDontSee('Route n/a');
        }
    }
}
