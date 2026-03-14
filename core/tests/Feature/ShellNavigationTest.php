<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShellNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shell_returns_not_implemented_for_unknown_menu_requests(): void
    {
        $this->get('/app?menu=plugin.missing.screen&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Screen unavailable')
            ->assertSee('plugin.missing.screen');
    }

    public function test_the_shell_returns_not_implemented_for_unavailable_menu_requests(): void
    {
        $this->get('/app?menu=plugin.identity-ldap.directory&principal_id=principal-admin&organization_id=org-a')
            ->assertOk()
            ->assertSee('Screen unavailable')
            ->assertSee('plugin.identity-ldap.directory');
    }
}
