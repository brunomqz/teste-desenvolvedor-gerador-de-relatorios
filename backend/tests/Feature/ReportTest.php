<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_filters_by_status(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        Billing::factory()->create([
            'client_id' => $client->id,
            'status' => 'paid',
            'due_date' => Carbon::today(),
        ]);
        Billing::factory()->create([
            'client_id' => $client->id,
            'status' => 'pending',
            'due_date' => Carbon::today(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson(
            '/api/reports/billings?' . http_build_query([
                'date_from' => Carbon::today()->subDay()->toDateString(),
                'date_to' => Carbon::today()->addDay()->toDateString(),
                'date_base' => 'due',
                'status' => 'paid',
            ])
        );

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'paid');
    }

    public function test_report_filters_by_client(): void
    {
        $user = User::factory()->create();
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        Billing::factory()->create(['client_id' => $clientA->id, 'due_date' => Carbon::today()]);
        Billing::factory()->create(['client_id' => $clientB->id, 'due_date' => Carbon::today()]);

        $response = $this->actingAs($user, 'sanctum')->getJson(
            '/api/reports/billings?' . http_build_query([
                'date_from' => Carbon::today()->subDay()->toDateString(),
                'date_to' => Carbon::today()->addDay()->toDateString(),
                'date_base' => 'due',
                'client_id' => $clientA->id,
            ])
        );

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_report_totals_are_calculated_correctly(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        Billing::factory()->create([
            'client_id' => $client->id,
            'status' => 'paid',
            'original_amount' => 500,
            'monthly_interest_rate' => 0,
            'due_date' => Carbon::today(),
        ]);
        Billing::factory()->create([
            'client_id' => $client->id,
            'status' => 'pending',
            'original_amount' => 300,
            'monthly_interest_rate' => 0,
            'due_date' => Carbon::today(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson(
            '/api/reports/billings?' . http_build_query([
                'date_from' => Carbon::today()->subDay()->toDateString(),
                'date_to' => Carbon::today()->addDay()->toDateString(),
                'date_base' => 'due',
            ])
        );

        $response->assertStatus(200);
        $response->assertJsonPath('totals.count', 2);
        $response->assertJsonPath('totals.total_original_amount', 800);
        $response->assertJsonPath('totals.total_paid', 500);
        $response->assertJsonPath('totals.total_pending', 300);
    }

    public function test_payment_date_filter_requires_paid_status(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson(
            '/api/reports/billings?' . http_build_query([
                'date_from' => Carbon::today()->subDay()->toDateString(),
                'date_to' => Carbon::today()->addDay()->toDateString(),
                'date_base' => 'payment',
            ])
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('date_base');
    }
}