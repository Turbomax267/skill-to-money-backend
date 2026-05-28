<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketTrend extends Model
{
    protected $fillable = [
        'category_id',
        'title',
        'description',
        'demand_score',
        'average_price',
        'currency',
        'source',
        'period_start',
        'period_end',
    ];

    protected function casts(): array
    {
        return [
            'demand_score' => 'decimal:2',
            'average_price' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

