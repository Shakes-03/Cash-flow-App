<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    protected $fillable = [
        'bank_import_id',
        'external_id',
        'transaction_date',
        'description',
        'payer',
        'amount',
        'balance_after',
        'type',
        'category_id',
        'category_locked',
        'customer_id',
        'reference',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'category_locked' => 'boolean',
    ];

    public function bankImport(): BelongsTo
    {
        return $this->belongsTo(BankImport::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pbePayment(): HasOne
    {
        return $this->hasOne(PbePayment::class);
    }
}
