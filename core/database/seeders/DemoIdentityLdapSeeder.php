<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoIdentityLdapSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('identity_ldap_connections')->insertOrIgnore([
            [
                'id' => 'ldap-org-a',
                'organization_id' => 'org-a',
                'name' => 'Northwind Directory',
                'host' => 'ldap.northwind.test',
                'port' => 389,
                'base_dn' => 'ou=People,dc=northwind,dc=test',
                'bind_dn' => 'cn=readonly,dc=northwind,dc=test',
                'bind_password' => 'demo-bind-token',
                'user_dn_attribute' => 'uid',
                'mail_attribute' => 'mail',
                'display_name_attribute' => 'cn',
                'job_title_attribute' => 'title',
                'group_attribute' => 'memberOf',
                'login_mode' => 'username',
                'sync_interval_minutes' => 120,
                'user_filter' => '(objectClass=person)',
                'fallback_email_enabled' => true,
                'is_enabled' => true,
                'last_sync_started_at' => now()->subMinutes(8),
                'last_sync_completed_at' => now()->subMinutes(7),
                'last_sync_status' => 'success',
                'last_sync_message' => '2 cached people refreshed from the external directory.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('identity_ldap_group_mappings')->insertOrIgnore([
            [
                'id' => 'ldap-mapping-org-a-it',
                'connection_id' => 'ldap-org-a',
                'ldap_group' => 'cn=it-services,ou=Groups,dc=northwind,dc=test',
                'role_keys' => json_encode(['asset-viewer', 'control-viewer'], JSON_THROW_ON_ERROR),
                'scope_ids' => json_encode(['scope-it'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'ldap-mapping-org-a-eu',
                'connection_id' => 'ldap-org-a',
                'ldap_group' => 'cn=eu-operations,ou=Groups,dc=northwind,dc=test',
                'role_keys' => json_encode(['asset-viewer', 'risk-viewer'], JSON_THROW_ON_ERROR),
                'scope_ids' => json_encode(['scope-eu'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('identity_local_users')->insertOrIgnore([
            [
                'id' => 'identity-user-ldap-lars-heidt',
                'principal_id' => 'principal-ldap-lars-heidt',
                'auth_provider' => 'ldap',
                'external_subject' => 'uid=lars.heidt,ou=People,dc=northwind,dc=test',
                'directory_source' => 'ldap-org-a',
                'directory_groups' => json_encode([
                    'cn=it-services,ou=Groups,dc=northwind,dc=test',
                    'cn=eu-operations,ou=Groups,dc=northwind,dc=test',
                ], JSON_THROW_ON_ERROR),
                'directory_synced_at' => now()->subMinutes(7),
                'organization_id' => 'org-a',
                'username' => 'lars.heidt',
                'display_name' => 'Lars Heidt',
                'email' => 'lars.heidt@northwind.test',
                'password_hash' => null,
                'password_enabled' => false,
                'magic_link_enabled' => true,
                'job_title' => 'Infrastructure lead',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'identity-user-ldap-marta-soler',
                'principal_id' => 'principal-ldap-marta-soler',
                'auth_provider' => 'ldap',
                'external_subject' => 'uid=marta.soler,ou=People,dc=northwind,dc=test',
                'directory_source' => 'ldap-org-a',
                'directory_groups' => json_encode([], JSON_THROW_ON_ERROR),
                'directory_synced_at' => now()->subMinutes(7),
                'organization_id' => 'org-a',
                'username' => 'marta.soler',
                'display_name' => 'Marta Soler',
                'email' => 'marta.soler@northwind.test',
                'password_hash' => null,
                'password_enabled' => false,
                'magic_link_enabled' => true,
                'job_title' => 'Vendor manager',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('memberships')->insertOrIgnore([
            [
                'id' => 'membership-ldap-org-a-lars-heidt',
                'principal_id' => 'principal-ldap-lars-heidt',
                'organization_id' => 'org-a',
                'roles' => json_encode(['asset-viewer', 'control-viewer', 'risk-viewer'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'membership-ldap-org-a-marta-soler',
                'principal_id' => 'principal-ldap-marta-soler',
                'organization_id' => 'org-a',
                'roles' => json_encode([], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('membership_scope')->insertOrIgnore([
            [
                'membership_id' => 'membership-ldap-org-a-lars-heidt',
                'scope_id' => 'scope-eu',
            ],
            [
                'membership_id' => 'membership-ldap-org-a-lars-heidt',
                'scope_id' => 'scope-it',
            ],
        ]);

        DB::table('authorization_grants')->insertOrIgnore([
            [
                'id' => 'seed-grant-ldap-001',
                'target_type' => 'membership',
                'target_id' => 'membership-ldap-org-a-lars-heidt',
                'grant_type' => 'role',
                'value' => 'asset-viewer',
                'context_type' => 'organization',
                'organization_id' => 'org-a',
                'scope_id' => null,
                'is_system' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'seed-grant-ldap-002',
                'target_type' => 'membership',
                'target_id' => 'membership-ldap-org-a-lars-heidt',
                'grant_type' => 'role',
                'value' => 'control-viewer',
                'context_type' => 'organization',
                'organization_id' => 'org-a',
                'scope_id' => null,
                'is_system' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'seed-grant-ldap-003',
                'target_type' => 'membership',
                'target_id' => 'membership-ldap-org-a-lars-heidt',
                'grant_type' => 'role',
                'value' => 'risk-viewer',
                'context_type' => 'organization',
                'organization_id' => 'org-a',
                'scope_id' => null,
                'is_system' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
