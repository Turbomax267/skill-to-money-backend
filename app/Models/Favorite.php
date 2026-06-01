<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    protected $fillable = [
        'mype_profile_id',
        'freelancer_profile_id',
    ];

    public function mype(): BelongsTo
    {
        return $this->belongsTo(MypeProfile::class, 'mype_profile_id');
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class, 'freelancer_profile_id');
    }
}
