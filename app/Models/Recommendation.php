<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    protected $fillable = [
        'user_id',
        'recommendation_type',
        'title',
        'description',
        'score',
        'data',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

