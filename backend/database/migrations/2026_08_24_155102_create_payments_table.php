<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')
                ->constrained('billings')
                ->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('paid_amount', 12, 2);
            $table->decimal('interest_amount', 12, 2);
            $table->timestamps();

            $table->index('billing_id');
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
