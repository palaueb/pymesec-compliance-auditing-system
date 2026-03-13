<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoIdentityLocalSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('identity_local_users')->insertOrIgnore([
            [
                'id' => 'identity-user-ava-mason',
                'principal_id' => 'principal-org-a',
                'organization_id' => 'org-a',
                'username' => 'ava.mason',
                'display_name' => 'Ava Mason',
                'email' => 'ava.mason@northwind.test',
                'password_hash' => null,
                'password_enabled' => false,
                'magic_link_enabled' => true,
                'job_title' => 'Compliance manager',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'identity-user-ruben-ortega',
                'principal_id' => 'principal-org-b-ops',
                'organization_id' => 'org-b',
                'username' => 'ruben.ortega',
                'display_name' => 'Ruben Ortega',
                'email' => 'ruben.ortega@bluewave.test',
                'password_hash' => null,
                'password_enabled' => false,
                'magic_link_enabled' => true,
                'job_title' => 'Operations supervisor',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'identity-user-platform-admin',
                'principal_id' => 'principal-admin',
                'organization_id' => 'org-a',
                'username' => 'platform.admin',
                'display_name' => 'Platform Administrator',
                'email' => 'platform.admin@pymesec.test',
                'password_hash' => null,
                'password_enabled' => false,
                'magic_link_enabled' => true,
                'job_title' => 'Platform administration',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
