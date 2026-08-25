<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Client;
use App\Models\Billing;
use App\Models\Payment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User para testes
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@admin.com',
            'password' => bcrypt('admin123'),
        ]);

        $this->command->info('Creating clients...');
        $clients = Client::factory(2000)->create();

        $this->command->info('Creating paid bills...');
        Billing::factory(30000)
            ->paid()
            ->recycle($clients)
            ->create()
            ->each(function (Billing $billing) {
                Payment::factory()->create([
                    'billing_id' => $billing->id,
                    'payment_date' => $billing->due_date->addDays(rand(-5, 15)),
                ]);
            });

        $this->command->info('Creating overdue bills (not paid)...');
        Billing::factory(20000)
            ->overdue()
            ->recycle($clients)
            ->create();

        $this->command->info('Creating current bills...');
        Billing::factory(20000)
            ->current()
            ->recycle($clients)
            ->create();

        $this->command->info('Finished: ' . Billing::count() . ' bills, ' . Payment::count() . ' payments.');
    
    }
}
