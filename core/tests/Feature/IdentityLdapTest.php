<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PymeSec\Plugins\IdentityLdap\LdapDirectoryGatewayInterface;
use PymeSec\Plugins\IdentityLdap\IdentityLdapService;
use Tests\TestCase;

class IdentityLdapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_directory_sync_screen_renders_inside_the_shell(): void
    {
        $this->get('/admin?menu=plugin.identity-ldap.directory&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Directory Sync')
            ->assertSee('Governance page. Directory connector settings and group mappings live here')
            ->assertSee('Northwind Directory')
            ->assertSee('Lars Heidt');
    }

    public function test_the_ldap_connector_can_sync_people_and_apply_group_mappings(): void
    {
        $this->get('/plugins/identity/ldap?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk();

        $this->app->instance(LdapDirectoryGatewayInterface::class, $this->fakeGateway(syncUsers: [
            [
                'external_subject' => 'uid=lars.heidt,ou=People,dc=northwind,dc=test',
                'username' => 'lars.heidt',
                'email' => 'lars.heidt@northwind.test',
                'display_name' => 'Lars Heidt',
                'job_title' => 'Infrastructure lead',
                'group_names' => [
                    'cn=it-services,ou=Groups,dc=northwind,dc=test',
                    'cn=eu-operations,ou=Groups,dc=northwind,dc=test',
                ],
                'is_active' => true,
            ],
            [
                'external_subject' => 'uid=dirk.koch,ou=People,dc=northwind,dc=test',
                'username' => 'dirk.koch',
                'email' => 'dirk.koch@northwind.test',
                'display_name' => 'Dirk Koch',
                'job_title' => 'Field service lead',
                'group_names' => [
                    'cn=it-services,ou=Groups,dc=northwind,dc=test',
                ],
                'is_active' => true,
            ],
        ]));

        $this->post('/plugins/identity/ldap/sync', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'membership_ids' => ['membership-org-a-hello'],
        ])->assertFound();

        $this->assertDatabaseHas('identity_local_users', [
            'auth_provider' => 'ldap',
            'external_subject' => 'uid=dirk.koch,ou=People,dc=northwind,dc=test',
            'email' => 'dirk.koch@northwind.test',
            'organization_id' => 'org-a',
            'is_active' => true,
        ]);

        $dirkPrincipalId = DB::table('identity_local_users')
            ->where('email', 'dirk.koch@northwind.test')
            ->value('principal_id');

        $this->assertIsString($dirkPrincipalId);

        $membershipId = DB::table('memberships')
            ->where('principal_id', $dirkPrincipalId)
            ->where('organization_id', 'org-a')
            ->where('id', 'like', 'membership-ldap-org-a-%')
            ->value('id');

        $this->assertIsString($membershipId);

        $this->assertDatabaseHas('memberships', [
            'id' => $membershipId,
            'principal_id' => $dirkPrincipalId,
            'organization_id' => 'org-a',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('authorization_grants', [
            'target_type' => 'membership',
            'target_id' => $membershipId,
            'grant_type' => 'role',
            'value' => 'asset-viewer',
            'context_type' => 'organization',
            'organization_id' => 'org-a',
        ]);

        $this->assertDatabaseHas('membership_scope', [
            'membership_id' => $membershipId,
            'scope_id' => 'scope-it',
        ]);

        $this->assertDatabaseHas('identity_local_users', [
            'email' => 'marta.soler@northwind.test',
            'auth_provider' => 'ldap',
            'is_active' => false,
        ]);

        $this->get('/plugins/identity/ldap?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'display_name' => 'Dirk Koch',
                'username' => 'dirk.koch',
            ]);
    }

    public function test_directory_password_login_respects_username_mode_and_creates_an_ldap_session_after_email_verification(): void
    {
        Mail::fake();

        $this->app->instance(LdapDirectoryGatewayInterface::class, $this->fakeGateway(
            authResponses: [
                'username:lars.heidt' => [
                    'external_subject' => 'uid=lars.heidt,ou=People,dc=northwind,dc=test',
                    'username' => 'lars.heidt',
                    'email' => 'lars.heidt@northwind.test',
                    'display_name' => 'Lars Heidt',
                    'job_title' => 'Infrastructure lead',
                    'group_names' => [],
                    'is_active' => true,
                ],
            ],
        ));

        $this->post('/login', [
            'login' => 'lars.heidt',
            'password' => 'directory-pass-123',
        ])->assertRedirect('/login/verify');

        $this->assertSame('principal-ldap-lars-heidt', session('auth.pending_principal_id'));
        $this->assertSame('identity-ldap', session('auth.pending_provider'));
        $this->assertDatabaseCount('identity_local_login_codes', 1);

        $challenge = $this->app->make(IdentityLdapService::class)->beginPasswordLogin('lars.heidt', 'directory-pass-123');

        $this->assertIsArray($challenge);
        $this->assertSame('principal-ldap-lars-heidt', $challenge['user']['principal_id']);

        $this->post('/login/verify', [
            'code' => $challenge['code'],
        ])->assertRedirect('/app?organization_id=org-a');

        $this->assertSame('principal-ldap-lars-heidt', session('auth.principal_id'));
        $this->assertSame('identity-ldap', session('auth.provider'));
    }

    public function test_directory_password_login_requires_email_when_the_connector_uses_email_mode(): void
    {
        Mail::fake();

        DB::table('identity_ldap_connections')
            ->where('organization_id', 'org-a')
            ->update([
                'login_mode' => 'email',
                'updated_at' => now(),
            ]);

        $this->app->instance(LdapDirectoryGatewayInterface::class, $this->fakeGateway(
            authResponses: [
                'email:lars.heidt@northwind.test' => [
                    'external_subject' => 'uid=lars.heidt,ou=People,dc=northwind,dc=test',
                    'username' => 'lars.heidt',
                    'email' => 'lars.heidt@northwind.test',
                    'display_name' => 'Lars Heidt',
                    'job_title' => 'Infrastructure lead',
                    'group_names' => [],
                    'is_active' => true,
                ],
            ],
        ));

        $this->post('/login', [
            'login' => 'lars.heidt',
            'password' => 'directory-pass-123',
        ])->assertRedirect('/login');

        $this->assertNull(session('auth.pending_principal_id'));
        $this->assertDatabaseCount('identity_local_login_codes', 0);

        $this->post('/login', [
            'login' => 'lars.heidt@northwind.test',
            'password' => 'directory-pass-123',
        ])->assertRedirect('/login/verify');

        $this->assertSame('principal-ldap-lars-heidt', session('auth.pending_principal_id'));
        $this->assertSame('identity-ldap', session('auth.pending_provider'));
    }

    public function test_directory_magic_link_fallback_respects_the_live_connector_setting(): void
    {
        Mail::fake();

        $this->post('/login', [
            'login' => 'lars.heidt',
            'use_email_link' => '1',
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('identity_local_login_links', 1);

        $issued = $this->app->make(IdentityLdapService::class)->issueMagicLink('lars.heidt');

        $this->assertIsArray($issued);
        $this->assertSame('principal-ldap-lars-heidt', $issued['user']['principal_id']);

        $this->get(route('plugin.identity-local.auth.consume', ['token' => $issued['token']]))
            ->assertRedirect('/app?organization_id=org-a');

        $this->assertSame('principal-ldap-lars-heidt', session('auth.principal_id'));
        $this->assertSame('identity-ldap', session('auth.provider'));

        DB::table('identity_ldap_connections')
            ->where('organization_id', 'org-a')
            ->update([
                'fallback_email_enabled' => false,
                'updated_at' => now(),
            ]);

        $this->post('/login', [
            'login' => 'lars.heidt',
            'use_email_link' => '1',
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('identity_local_login_links', 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $syncUsers
     * @param  array<string, array<string, mixed>>  $authResponses
     */
    private function fakeGateway(array $syncUsers = [], array $authResponses = []): LdapDirectoryGatewayInterface
    {
        return new class($syncUsers, $authResponses) implements LdapDirectoryGatewayInterface
        {
            /**
             * @param  array<int, array<string, mixed>>  $syncUsers
             * @param  array<string, array<string, mixed>>  $authResponses
             */
            public function __construct(
                private readonly array $syncUsers,
                private readonly array $authResponses,
            ) {}

            public function fetchUsers(array $connection): array
            {
                return $this->syncUsers;
            }

            public function authenticate(array $connection, string $login, string $password): ?array
            {
                if ($password !== 'directory-pass-123') {
                    return null;
                }

                $loginMode = (string) ($connection['login_mode'] ?? 'username');
                $key = sprintf('%s:%s', $loginMode, strtolower(trim($login)));

                return $this->authResponses[$key] ?? null;
            }
        };
    }
}
