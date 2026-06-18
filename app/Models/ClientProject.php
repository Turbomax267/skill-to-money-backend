<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientProject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'mype_profile_id',
        'title',
        'category',
        'description',
        'budget_min',
        'budget_max',
        'expected_delivery_days',
        'status',
        'progress',
        'views_count',
        'ai_generated',
    ];

    protected function casts(): array
    {
        return [
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'expected_delivery_days' => 'integer',
            'progress' => 'integer',
            'views_count' => 'integer',
            'ai_generated' => 'boolean',
        ];
    }

    public function mypeProfile(): BelongsTo
    {
        return $this->belongsTo(MypeProfile::class);
    }

    public function contracts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
