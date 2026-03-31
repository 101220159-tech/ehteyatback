<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_api_health(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonStructure(['status', 'timestamp', 'environment', 'database', 'cache', 'app']);
    }
}
