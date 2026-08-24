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
        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')
                ->constrained('clients')
                ->restrictOnDelete();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('monthly_interest_rate', 6, 4);
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('status')->default('pending');
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->index(['status', 'due_date']);
            $table->index('issue_date');
            $table->index('due_date');
            $table->index(['client_id', 'status']);   
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billings');
    }
};
