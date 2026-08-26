<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_report(): void
    {
        $response = $this->getJson('/api/reports/billings?date_from=2026-01-01&date_to=2026-12-31&date_base=due');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_export_csv(): void
    {
        $response = $this->getJson('/api/reports/billings/export/csv?date_from=2026-01-01&date_to=2026-12-31&date_base=due');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_export_pdf(): void
    {
        $response = $this->getJson('/api/reports/billings/export/pdf?date_from=2026-01-01&date_to=2026-12-31&date_base=due');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_list_clients(): void
    {
        $response = $this->getJson('/api/clients');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_list_billings(): void
    {
        $response = $this->getJson('/api/billings');

        $response->assertStatus(401);
    }
}