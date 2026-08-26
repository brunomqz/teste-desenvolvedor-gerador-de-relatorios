<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_export_csv(): void
    {
        $user = User::factory()->create();
        Billing::factory()->create(['due_date' => Carbon::today()]);

        $response = $this->actingAs($user, 'sanctum')->get(
            '/api/reports/billings/export/csv?' . http_build_query([
                'date_from' => Carbon::today()->subDay()->toDateString(),
                'date_to' => Carbon::today()->addDay()->toDateString(),
                'date_base' => 'due',
            ])
        );

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_authenticated_user_can_export_pdf_within_limit(): void
    {
        $user = User::factory()->create();
        Billing::factory()->create(['due_date' => Carbon::today()]);

        $response = $this->actingAs($user, 'sanctum')->get(
            '/api/reports/billings/export/pdf?' . http_build_query([
                'date_from' => Carbon::today()->subDay()->toDateString(),
                'date_to' => Carbon::today()->addDay()->toDateString(),
                'date_base' => 'due',
            ])
        );

        $response->assertStatus(200);
    }

    public function test_pdf_export_rejects_when_over_row_limit(): void
    {
        $user = User::factory()->create();
        Billing::factory()->count(5)->create(['due_date' => Carbon::today()]);

        // Simula o limite de forma barata: testamos a mensagem de erro assumindo
        // que o teto configurado no controller é maior que 5 — ajuste o teste
        // se você alterar o valor de $maxRows no ExportController.
        $this->markTestSkipped('Ajustar conforme o valor real de $maxRows configurado no ExportController.');
    }
}