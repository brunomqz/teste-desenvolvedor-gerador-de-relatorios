<?php 

namespace App\Services;

use App\Models\Billing;
use Carbon\Carbon;

class InterestService
{
    /**
     * Atualização dinâmica do valor da fatura 
     * Base na taxa de juros mensal e nos dias de atraso
     */
    public function updatedAmount(Billing $billing, ?Carbon $reference = null): float
    {
        $overdueDays = $this->overdueDays($billing, $reference);

        if ($billing->status === 'paid' || $overdueDays <= 0) {
            return (float) $billing->original_amount;
        }

        $amount = (float) $billing->original_amount;
        $rate = (float) $billing->monthly_interest_rate;

        return round($amount * (1 + $rate) ** ($overdueDays / 30), 2);
    }

    public function overdueDays(Billing $billing, ?Carbon $reference = null): int
    {
        $reference ??= Carbon::today();
        $dueDate = Carbon::parse($billing->due_date);

        return max(0, $dueDate->diffInDays($reference, false));
    }
}   