<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchResult extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'mype_profile_id',
        'freelancer_profile_id',
        'service_id',
        'compatibility_score',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'compatibility_score' => 'decimal:2',
        ];
    }

    public function mype(): BelongsTo
    {
        return $this->belongsTo(MypeProfile::class, 'mype_profile_id');
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class, 'freelancer_profile_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
