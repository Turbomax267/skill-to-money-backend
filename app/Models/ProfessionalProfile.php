<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalProfile extends Model
{
    protected $fillable = [
        'user_id',
        'headline',
        'category',
        'bio',
        'description',
        'location',
        'hourly_rate',
        'skills',
        'social_links',
        'photo_url',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'skills' => 'array',
            'social_links' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
