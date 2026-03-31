<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_api_landing_json(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Backend API — use /api routes from your frontend app']);
    }
}
