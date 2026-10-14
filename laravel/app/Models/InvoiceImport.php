<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceImport extends Model
{
    protected $fillable = [
        'filename',
        'imported_at',
        'status',
        'total_rows',
        'duplicates_skipped',
        'user_id',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
