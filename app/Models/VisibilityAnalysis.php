<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisibilityAnalysis extends Model
{
    protected $fillable = [
        'user_id',
        'service_id',
        'profile_views',
        'service_views',
        'favorite_count',
        'contact_count',
        'visibility_score',
        'analysis_notes',
        'period_start',
        'period_end',
    ];

    protected function casts(): array
    {
        return [
            'profile_views' => 'integer',
            'service_views' => 'integer',
            'favorite_count' => 'integer',
            'contact_count' => 'integer',
            'visibility_score' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

