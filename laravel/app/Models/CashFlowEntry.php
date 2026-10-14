<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manually entered cash flow amount (a forecast, accrual or anything not on the bank
 * statement). The amount is in the account's normal direction: money in for an income
 * account, money out for an expense / cost-of-sales account. A negative amount reverses it.
 */
class CashFlowEntry extends Model
{
    protected $fillable = [
        'entry_date',
        'category_id',
        'amount',
        'description',
        'user_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Effect on the bank balance: positive for money in, negative for money out. */
    public function signedAmount(): float
    {
        return $this->category?->type === 'income' ? (float) $this->amount : -(float) $this->amount;
    }
}
