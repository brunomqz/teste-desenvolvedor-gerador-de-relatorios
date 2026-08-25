<?php

namespace Database\Factories;

use App\Models\Billing;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Billing>
 */
class BillingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issueDate = fake()->dateTimeBetween('-2 years', '-1 day');
        $dueDate = Carbon::parse($issueDate)->addDays(fake()->numberBetween(15, 60));

        return [
            'client_id' => Client::factory(),
            'original_amount' => fake()->randomFloat(2, 100, 20000),
            'monthly_interest_rate' => fake()->randomFloat(4, 0.01, 0.12),
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'status' => 'pending',
            'observations' => fake()->optional(0.3)->sentence(),
        ];
    }

    // Cobrança paga
    public function paid(): static
    {
        return $this->state(fn () => ['status' => 'paid']);
    }

    // Cobrança vencida, mas ainda não paga
    public function overdue(): static
    {
        return $this->state(function () {
            $dueDate = fake()->dateTimeBetween('-6 months', '-1 day');
            return [
                'issue_date' => Carbon::parse($dueDate)->subDays(fake()->numberBetween(15, 60)),
                'due_date' => $dueDate,
                'status' => 'pending',
            ];
        });
    }

    // Cobrança atual, em dia 
    public function current(): static
    {
        return $this->state(function () {
            $dueDate = fake()->dateTimeBetween('+1 day', '+3 months');
            return [
                'issue_date' => Carbon::parse($dueDate)->subDays(fake()->numberBetween(15, 60)),
                'due_date' => $dueDate,
                'status' => 'pending',
            ];
        });
    }
}
