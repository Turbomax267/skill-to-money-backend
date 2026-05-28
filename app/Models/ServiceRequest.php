<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceRequest extends Model
{
    protected $fillable = [
        'mype_id',
        'category_id',
        'title',
        'description',
        'budget_min',
        'budget_max',
        'expected_delivery_days',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'expected_delivery_days' => 'integer',
        ];
    }

    public function mype(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mype_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MatchResult::class);
    }
}

