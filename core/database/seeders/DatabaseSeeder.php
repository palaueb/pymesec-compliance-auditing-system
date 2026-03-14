<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $profile = (string) env('APP_INSTALL_PROFILE', 'system');

        $seeder = match ($profile) {
            'demo' => DemoCompanySeeder::class,
            'test' => TestDatabaseSeeder::class,
            default => SystemBootstrapSeeder::class,
        };

        $this->call([$seeder]);
    }
}
