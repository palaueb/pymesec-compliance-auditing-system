<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_root_redirects_to_login_when_no_session_is_present(): void
    {
        $this->get('/')
            ->assertRedirect(route('plugin.identity-local.auth.login'));
    }

    public function test_the_health_endpoint_returns_a_successful_response(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_the_app_shell_preview_renders_successfully_with_explicit_context(): void
    {
        $this->get('/app?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Workspace Dashboard')
            ->assertSee('Today in your workspace')
            ->assertSee('Assets')
            ->assertDontSee('Core Shell Preview')
            ->assertDontSee('A first visual pass of the left-hand shell');
    }
}
