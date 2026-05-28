<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchResult extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'service_request_id',
        'service_id',
        'mype_id',
        'freelancer_id',
        'match_score',
        'match_reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'match_score' => 'decimal:2',
            'match_reason' => 'array',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function mype(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mype_id');
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }
}

