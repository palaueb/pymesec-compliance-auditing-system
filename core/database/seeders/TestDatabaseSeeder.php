<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TestDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SystemBootstrapSeeder::class,
            CoreTenancySeeder::class,
            DemoIdentityLocalSeeder::class,
            DemoIdentityLdapSeeder::class,
        ]);
    }
}
