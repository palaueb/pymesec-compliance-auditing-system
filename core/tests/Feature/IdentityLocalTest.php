<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $this->get('/admin?menu=plugin.identity-local.users&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('People & Access')
            ->assertSee('Ava Mason')
            ->assertSee('Add person')
            ->assertSee('Open');

        $this->get('/app?menu=plugin.identity-local.memberships&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Organization Access')
            ->assertSee('membership-org-a-hello')
            ->assertSee('Grant access')
            ->assertSee('Operational workspaces')
            ->assertSee('Access administration')
            ->assertSee('Open');
    }

    public function test_the_identity_detail_views_render_without_errors(): void
    {
        // Membership detail view — exercises the @php block that triggered the @php(...) bug
        $this->get('/app?menu=plugin.identity-local.memberships&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello&selected_membership_id=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Access administration')
            ->assertSee('Identity operator')         // label shown in display section
            ->assertSee('Directory sync operator')   // label shown in display section
            ->assertSee('Functional profiles')
            ->assertSee('Manage responsibilities')
            ->assertSee('Hold')                      // multi-select hint
            ->assertSee('Save access');

        // User detail view — exercises the same pattern in users.blade.php
        $this->get('/admin?menu=plugin.identity-local.users&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello&user_id=identity-user-ava-mason')
            ->assertOk()
            ->assertSee('Ava Mason');
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
            'username' => 'nina.patel',
            'email' => 'nina.patel@northwind.test',
            'job_title' => 'Security coordinator',
            'actor_id' => 'actor-ava-mason',
            'magic_link_enabled' => '1',
        ])->assertFound();

        $userId = DB::table('identity_local_users')
            ->where('email', 'nina.patel@northwind.test')
            ->value('id');
        $subjectPrincipalId = DB::table('identity_local_users')
            ->where('email', 'nina.patel@northwind.test')
            ->value('principal_id');

        $this->assertIsString($userId);
        $this->assertIsString($subjectPrincipalId);

        $this->get('/admin?menu=plugin.identity-local.users&user_id='.$userId.'&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Nina Patel')
            ->assertSee('nina.patel')
            ->assertSee('Security coordinator');

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
            'username' => 'nina.ops',
            'email' => 'nina.patel@northwind.test',
            'job_title' => 'Security operations lead',
            'password_enabled' => '1',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
            'magic_link_enabled' => '1',
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

        $this->get('/app?menu=plugin.identity-local.memberships&selected_membership_id='.$membershipId.'&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee($subjectPrincipalId)
            ->assertSee('control-viewer')
            ->assertSee('scope-it');
    }

    public function test_local_people_can_be_deleted_from_the_people_screen(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-hello',
            'menu' => 'plugin.identity-local.users',
        ];

        $this->post('/plugins/identity/users', [
            ...$payload,
            'display_name' => 'Delete Me',
            'username' => 'delete.me',
            'email' => 'delete.me@northwind.test',
            'job_title' => 'Temporary access',
            'magic_link_enabled' => '1',
        ])->assertFound();

        $userId = DB::table('identity_local_users')
            ->where('email', 'delete.me@northwind.test')
            ->value('id');
        $principalId = DB::table('identity_local_users')
            ->where('email', 'delete.me@northwind.test')
            ->value('principal_id');

        $this->assertIsString($userId);
        $this->assertIsString($principalId);

        $this->post('/plugins/identity/memberships', [
            ...$payload,
            'menu' => 'plugin.identity-local.memberships',
            'subject_principal_id' => $principalId,
            'role_keys' => ['asset-viewer'],
            'scope_ids' => ['scope-eu'],
        ])->assertFound();

        $membershipId = DB::table('memberships')
            ->where('principal_id', $principalId)
            ->where('organization_id', 'org-a')
            ->value('id');

        $this->assertIsString($membershipId);

        $this->post(sprintf('/plugins/identity/users/%s/delete', $userId), $payload)
            ->assertFound();

        $this->assertDatabaseMissing('identity_local_users', ['id' => $userId]);
        $this->assertDatabaseMissing('memberships', ['id' => $membershipId]);
        $this->assertDatabaseMissing('authorization_grants', [
            'target_type' => 'membership',
            'target_id' => $membershipId,
        ]);
    }

    public function test_directory_backed_people_cannot_be_deleted_from_the_local_people_screen(): void
    {
        $userId = DB::table('identity_local_users')
            ->where('auth_provider', 'ldap')
            ->value('id');

        $this->assertIsString($userId);

        $this->post(sprintf('/plugins/identity/users/%s/delete', $userId), [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_id' => 'membership-org-a-hello',
            'menu' => 'plugin.identity-local.users',
        ])->assertFound();

        $this->assertDatabaseHas('identity_local_users', ['id' => $userId]);
    }

    public function test_magic_link_auth_sets_and_clears_the_shell_session(): void
    {
        Mail::fake();

        $this->post('/login', [
            'login' => 'ava.mason',
            'use_email_link' => '1',
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('identity_local_login_links', 1);

        $issued = $this->app->make(IdentityLocalAuthService::class)->issueMagicLink('ava.mason');

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

    public function test_password_auth_requires_email_code_before_creating_the_shell_session(): void
    {
        Mail::fake();

        DB::table('identity_local_users')
            ->where('id', 'identity-user-ava-mason')
            ->update([
                'password_hash' => Hash::make('secret-pass-123'),
                'password_enabled' => true,
                'magic_link_enabled' => true,
                'updated_at' => now(),
            ]);

        $this->post('/login', [
            'login' => 'ava.mason',
            'password' => 'secret-pass-123',
        ])->assertRedirect('/login/verify');

        $this->assertSame('principal-org-a', session('auth.pending_principal_id'));
        $this->assertDatabaseCount('identity_local_login_codes', 1);

        $challenge = $this->app->make(IdentityLocalAuthService::class)->beginPasswordLogin('ava.mason', 'secret-pass-123');

        $this->assertIsArray($challenge);
        $this->assertSame('principal-org-a', $challenge['user']['principal_id']);

        $this->post('/login/verify', [
            'code' => $challenge['code'],
        ])->assertRedirect('/app?organization_id=org-a');

        $this->assertSame('principal-org-a', session('auth.principal_id'));
        $this->assertNull(session('auth.pending_principal_id'));
    }

    public function test_first_run_setup_wizard_creates_the_initial_platform_admin(): void
    {
        DB::table('identity_local_users')->delete();

        $this->get('/app')->assertRedirect('/setup');

        $this->get('/setup')
            ->assertOk()
            ->assertSee('Create the first administrator');

        $this->post('/setup', [
            'display_name' => 'Root Admin',
            'username' => 'root.admin',
            'email' => 'root.admin@pymesec.test',
            'password' => 'initial-pass-123',
            'password_confirmation' => 'initial-pass-123',
        ])->assertRedirect('/login');

        $principalId = DB::table('identity_local_users')
            ->where('username', 'root.admin')
            ->value('principal_id');

        $this->assertIsString($principalId);

        $this->assertDatabaseHas('authorization_grants', [
            'target_type' => 'principal',
            'target_id' => $principalId,
            'grant_type' => 'role',
            'value' => 'platform-admin',
            'context_type' => 'platform',
        ]);

        $membershipId = DB::table('memberships')
            ->where('principal_id', $principalId)
            ->where('organization_id', 'org-a')
            ->value('id');

        $this->assertIsString($membershipId);

        $this->assertDatabaseHas('authorization_grants', [
            'target_type' => 'membership',
            'target_id' => $membershipId,
            'grant_type' => 'role',
            'value' => 'identity-operator',
            'context_type' => 'organization',
            'organization_id' => 'org-a',
        ]);

        $this->assertDatabaseHas('authorization_grants', [
            'target_type' => 'membership',
            'target_id' => $membershipId,
            'grant_type' => 'role',
            'value' => 'identity-ldap-operator',
            'context_type' => 'organization',
            'organization_id' => 'org-a',
        ]);

        foreach ([
            'asset-operator',
            'control-operator',
            'risk-operator',
            'findings-operator',
            'policy-operator',
            'privacy-operator',
            'continuity-operator',
            'evidence-operator',
        ] as $roleKey) {
            $this->assertDatabaseHas('authorization_grants', [
                'target_type' => 'membership',
                'target_id' => $membershipId,
                'grant_type' => 'role',
                'value' => $roleKey,
                'context_type' => 'organization',
                'organization_id' => 'org-a',
            ]);
        }
    }
}
