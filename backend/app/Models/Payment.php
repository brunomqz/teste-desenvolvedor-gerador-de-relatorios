<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
        use HasFactory;

    protected $fillable = [
        'billing_id',
        'payment_date',
        'paid_amount',
        'interest_amount',
    ];

    protected $casts = [
        'payment_date'      => 'date',
        'paid_amount'       => 'decimal:2',
        'interest_amount'   => 'decimal:2',
    ];

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }
}
