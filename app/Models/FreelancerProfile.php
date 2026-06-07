<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreelancerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'dni',
        'experience_area',
        'bio',
        'headline',
        'category',
        'suggested_rate',
        'gemini_analysis',
        'profile_photo',
        'location',
        'contact_phone',
        'website',
        'social_links',
        'availability_status',
        'rating',
        'completed_jobs',
        'visibility_score',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'gemini_analysis' => 'array',
            'rating' => 'decimal:2',
            'completed_jobs' => 'integer',
            'visibility_score' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'freelancer_skills')
            ->withPivot('level')
            ->withTimestamps();
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function portfolioProjects(): HasMany
    {
        return $this->hasMany(PortfolioProject::class);
    }
}
