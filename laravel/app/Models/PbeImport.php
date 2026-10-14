<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PbeImport extends Model
{
    protected $fillable = [
        'filename',
        'imported_at',
        'total_rows',
        'user_id',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(PbePayment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
