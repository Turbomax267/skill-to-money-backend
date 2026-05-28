<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MypeProfile extends Model
{
    protected $fillable = [
        'user_id',
        'business_name',
        'ruc',
        'industry',
        'contact_name',
        'website',
        'business_description',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

