<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MypeProfile extends Model
{
    protected $fillable = [
        'user_id',
        'business_name',
        'ruc',
        'industry',
        'description',
        'website',
        'location',
        'profile_photo',
        'views_count',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    protected function casts(): array
    {
        return [
            'views_count' => 'integer',
        ];
    }

    public function clientProjects(): HasMany
    {
        return $this->hasMany(ClientProject::class);
    }
}
