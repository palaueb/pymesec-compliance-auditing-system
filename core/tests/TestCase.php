<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Database\Seeders\TestDatabaseSeeder;
use PymeSec\Core\Plugins\PluginStateStore;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('organizations')) {
            $this->seed(TestDatabaseSeeder::class);
        }
    }

    protected function tearDown(): void
    {
        if (app()->bound(PluginStateStore::class)) {
            app(PluginStateStore::class)->clear();
        }

        parent::tearDown();
    }
}
