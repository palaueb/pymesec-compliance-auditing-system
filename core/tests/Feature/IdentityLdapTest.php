<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PymeSec\Plugins\IdentityLdap\LdapDirectoryGatewayInterface;
use Tests\TestCase;

class IdentityLdapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_directory_sync_screen_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.identity-ldap.directory&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Directory Sync')
            ->assertSee('Northwind Directory')
            ->assertSee('Lars Heidt');
    }

    public function test_the_ldap_connector_can_sync_people_and_apply_group_mappings(): void
    {
        $this->get('/plugins/identity/ldap?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk();

        $this->app->instance(LdapDirectoryGatewayInterface::class, new class implements LdapDirectoryGatewayInterface
        {
            public function fetchUsers(array $connection): array
            {
                return [
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
                ];
            }
        });

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
}
