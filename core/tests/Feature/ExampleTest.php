<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_core_root_endpoint_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertJson([
                'service' => 'pymesec-core',
                'status' => 'ok',
            ]);
    }

    public function test_the_health_endpoint_returns_a_successful_response(): void
    {
        $this->get('/up')->assertOk();
    }
}
