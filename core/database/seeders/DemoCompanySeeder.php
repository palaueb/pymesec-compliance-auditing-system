<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        // Canonical demo dataset used both for local preview and test bootstrap.
        $this->call([
            CoreTenancySeeder::class,
            DemoIdentityLocalSeeder::class,
            DemoIdentityLdapSeeder::class,
        ]);
    }
}
