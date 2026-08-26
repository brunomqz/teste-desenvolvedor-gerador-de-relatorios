<?php

namespace Tests\Unit;

use App\Models\Billing;
use App\Services\InterestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_billing_accrues_compound_interest(): void
    {
        $billing = Billing::factory()->create([
            'status' => 'pending',
            'original_amount' => 1000,
            'monthly_interest_rate' => 0.05,
            'due_date' => Carbon::today()->subDays(30),
        ]);

        $updated = app(InterestService::class)->updatedAmount($billing);

        // 30 dias de atraso = 1 mês cheio → 1000 * 1.05 = 1050
        $this->assertEquals(1050.00, $updated);
    }

    public function test_paid_billing_never_accrues_interest(): void
    {
        $billing = Billing::factory()->create([
            'status' => 'paid',
            'original_amount' => 1000,
            'monthly_interest_rate' => 0.05,
            'due_date' => Carbon::today()->subDays(30),
        ]);

        $updated = app(InterestService::class)->updatedAmount($billing);

        $this->assertEquals(1000.00, $updated);
    }

    public function test_billing_not_yet_due_has_no_interest(): void
    {
        $billing = Billing::factory()->create([
            'status' => 'pending',
            'original_amount' => 1000,
            'monthly_interest_rate' => 0.05,
            'due_date' => Carbon::today()->addDays(10),
        ]);

        $updated = app(InterestService::class)->updatedAmount($billing);

        $this->assertEquals(1000.00, $updated);
    }

    public function test_overdue_days_calculation(): void
    {
        $billing = Billing::factory()->create([
            'due_date' => Carbon::today()->subDays(15),
        ]);

        $overdueDays = app(InterestService::class)->overdueDays($billing);

        $this->assertEquals(15, $overdueDays);
    }
}