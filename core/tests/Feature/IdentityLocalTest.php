<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PymeSec\Plugins\IdentityLocal\IdentityLocalAuthService;
use Tests\TestCase;

class IdentityLocalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_identity_plugin_routes_require_view_permission(): void
    {
        $this->get('/plugins/identity/users?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'identity-user-ava-mason',
                'display_name' => 'Ava Mason',
            ]);

        $this->get('/plugins/identity/memberships?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'membership-org-a-hello',
                'principal_id' => 'principal-org-a',
            ]);

        $this->get('/plugins/identity/users?principal_id=principal-admin&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_identity_screens_render_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.identity-local.users&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('People & Access')
            ->assertSee('Ava Mason')
            ->assertSee('Add person');

        $this->get('/app?menu=plugin.identity-local.memberships&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Organization Access')
            ->assertSee('membership-org-a-hello')
            ->assertSee('Grant access');
    }

    public function test_users_and_memberships_can_be_created_and_updated_from_the_shell_runtime(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/identity/users', [
            ...$payload,
            'menu' => 'plugin.identity-local.users',
            'display_name' => 'Nina Patel',
            'email' => 'nina.patel@northwind.test',
            'job_title' => 'Security coordinator',
            'actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->get('/app?menu=plugin.identity-local.users&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Nina Patel')
            ->assertSee('Security coordinator');

        $userId = DB::table('identity_local_users')
            ->where('email', 'nina.patel@northwind.test')
            ->value('id');
        $subjectPrincipalId = DB::table('identity_local_users')
            ->where('email', 'nina.patel@northwind.test')
            ->value('principal_id');

        $this->assertIsString($userId);
        $this->assertIsString($subjectPrincipalId);

        $this->post('/plugins/identity/memberships', [
            ...$payload,
            'menu' => 'plugin.identity-local.memberships',
            'subject_principal_id' => $subjectPrincipalId,
            'role_keys' => ['asset-viewer', 'identity-viewer'],
            'scope_ids' => ['scope-eu'],
        ])->assertFound();

        $membershipId = DB::table('memberships')
            ->where('principal_id', $subjectPrincipalId)
            ->where('organization_id', 'org-a')
            ->value('id');

        $this->assertIsString($membershipId);

        $this->assertDatabaseHas('authorization_grants', [
            'target_type' => 'membership',
            'target_id' => $membershipId,
            'grant_type' => 'role',
            'value' => 'asset-viewer',
            'context_type' => 'organization',
            'organization_id' => 'org-a',
        ]);

        $this->get(sprintf('/core/authorization/check?principal_id=%s&permission=plugin.asset-catalog.assets.view&organization_id=org-a&membership_ids[]=%s', $subjectPrincipalId, $membershipId))
            ->assertOk()
            ->assertJsonPath('result.status', 'allow');

        $this->post(sprintf('/plugins/identity/users/%s', $userId), [
            ...$payload,
            'menu' => 'plugin.identity-local.users',
            'display_name' => 'Nina Patel',
            'email' => 'nina.patel@northwind.test',
            'job_title' => 'Security operations lead',
            'is_active' => '1',
        ])->assertFound();

        $this->post(sprintf('/plugins/identity/memberships/%s', $membershipId), [
            ...$payload,
            'menu' => 'plugin.identity-local.memberships',
            'subject_principal_id' => $subjectPrincipalId,
            'role_keys' => ['asset-viewer', 'control-viewer'],
            'scope_ids' => ['scope-eu', 'scope-it'],
            'is_active' => '1',
        ])->assertFound();

        $this->get(sprintf('/core/authorization/check?principal_id=%s&permission=plugin.controls-catalog.controls.view&organization_id=org-a&membership_ids[]=%s', $subjectPrincipalId, $membershipId))
            ->assertOk()
            ->assertJsonPath('result.status', 'allow');

        $this->get('/app?menu=plugin.identity-local.memberships&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee($subjectPrincipalId)
            ->assertSee('control-viewer')
            ->assertSee('scope-it');
    }

    public function test_magic_link_auth_sets_and_clears_the_shell_session(): void
    {
        Mail::fake();

        $this->post('/login', [
            'email' => 'ava.mason@northwind.test',
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('identity_local_login_links', 1);

        $issued = $this->app->make(IdentityLocalAuthService::class)->issueMagicLink('ava.mason@northwind.test');

        $this->assertIsArray($issued);
        $this->assertSame('principal-org-a', $issued['user']['principal_id']);

        $this->get(route('plugin.identity-local.auth.consume', ['token' => $issued['token']]))
            ->assertRedirect('/app?organization_id=org-a');

        $this->assertSame('principal-org-a', session('auth.principal_id'));

        $this->get('/app')
            ->assertOk()
            ->assertSee('principal-org-a')
            ->assertSee('Sign out')
            ->assertDontSee('Sign in');

        $this->post('/logout')
            ->assertRedirect('/login');

        $this->assertNull(session('auth.principal_id'));
    }
}
