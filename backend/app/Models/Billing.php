<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Billing extends Model
{
    /** @use HasFactory<\Database\Factories\BillingFactory> */
    use HasFactory;

        protected $fillable = [
        'client_id',
        'original_amount',
        'monthly_interest_rate',
        'issue_date',
        'due_date',
        'status',
        'observations',
    ];

    protected $casts = [
        'original_amount'       => 'decimal:2',
        'monthly_interest_rate' => 'decimal:4',
        'issue_date'            => 'date',
        'due_date'              => 'date',
    ];

    public function getConfirmStatus(): string 
    {
        if ($this->status === 'paid') {
            return 'paid';
        }

        return $this->due_date->isPast() ? 'overdue' : 'pending';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
