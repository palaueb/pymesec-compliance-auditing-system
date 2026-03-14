<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_support_screen_renders_the_aggregated_reference(): void
    {
        $this->get('/app?menu=core.support&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Support and Concepts')
            ->assertSee('Concept Index')
            ->assertSee('Organization')
            ->assertSee('Asset')
            ->assertSee('Risk')
            ->assertSee('Relationship Map');
    }

    public function test_the_shell_shows_the_language_selector(): void
    {
        $this->get('/app?menu=core.support&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Language')
            ->assertSee('English')
            ->assertSee('Español');
    }
}
