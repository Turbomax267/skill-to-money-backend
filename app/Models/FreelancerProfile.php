<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreelancerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'professional_title',
        'experience_level',
        'availability_status',
        'visibility_score',
        'rating',
        'completed_jobs',
    ];

    protected function casts(): array
    {
        return [
            'visibility_score' => 'decimal:2',
            'rating' => 'decimal:2',
            'completed_jobs' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

