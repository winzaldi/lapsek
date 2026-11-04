<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    public function test_api_health_endpoint_returns_minimal_status_and_request_id(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertExactJson(['status' => 'ok'])
            ->assertHeader('X-Request-Id');
    }
}
