<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_register_payment(): void
    {
        $user = User::factory()->create();
        $billing = Billing::factory()->create([
            'status' => 'pending',
            'original_amount' => 1000,
            'monthly_interest_rate' => 0.05,
            'due_date' => Carbon::today()->subDays(30),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/billings/{$billing->id}/payments", [
            'payment_date' => Carbon::today()->toDateString(),
            'paid_amount' => 1050.00,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('payments', [
            'billing_id' => $billing->id,
            'paid_amount' => 1050.00,
        ]);

        $billing->refresh();
        $this->assertEquals('paid', $billing->status);
    }

    public function test_payment_freezes_interest_amount_at_registration(): void
    {
        $user = User::factory()->create();
        $billing = Billing::factory()->create([
            'status' => 'pending',
            'original_amount' => 1000,
            'monthly_interest_rate' => 0.05,
            'due_date' => Carbon::today()->subDays(30),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/billings/{$billing->id}/payments", [
            'payment_date' => Carbon::today()->toDateString(),
            'paid_amount' => 1050.00,
        ]);

        $payment = $billing->payments()->first();

        // Juros: 1050 (atualizado) - 1000 (original) = 50
        $this->assertEquals(50.00, $payment->interest_amount);
    }

    public function test_cannot_pay_already_paid_billing(): void
    {
        $user = User::factory()->create();
        $billing = Billing::factory()->create(['status' => 'paid']);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/billings/{$billing->id}/payments", [
            'payment_date' => Carbon::today()->toDateString(),
            'paid_amount' => 100.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_register_payment(): void
    {
        $billing = Billing::factory()->create(['status' => 'pending']);

        $response = $this->postJson("/api/billings/{$billing->id}/payments", [
            'payment_date' => Carbon::today()->toDateString(),
            'paid_amount' => 100.00,
        ]);

        $response->assertStatus(401);
    }
}