<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_id' => Billing::factory(),
            'payment_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'paid_amount' => fake()->randomFloat(2, 100, 20000),
            'interest_amount' => fake()->randomFloat(2, 0, 500),
        ];
    }
}
