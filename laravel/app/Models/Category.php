<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'type',
        'is_cost_of_sales',
        'color',
    ];

    protected $casts = [
        'is_cost_of_sales' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function categorizationRules(): HasMany
    {
        return $this->hasMany(CategorizationRule::class);
    }
}
