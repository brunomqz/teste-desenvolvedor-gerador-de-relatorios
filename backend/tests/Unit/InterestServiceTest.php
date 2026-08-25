<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class InterestServiceTest extends TestCase
{

    /**
     * Verifica se uma cobrança paga não acumula juros.
     */
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


    /**
     * Verifica se uma cobrança vencida acumula juros compostos.
     */
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


    /**
     * Verifica se uma cobrança que ainda não venceu não acumula juros.
     */
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
}
