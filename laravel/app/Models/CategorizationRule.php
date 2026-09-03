<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategorizationRule extends Model
{
    protected $fillable = [
        'keyword',
        'category_id',
        'match_type',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
