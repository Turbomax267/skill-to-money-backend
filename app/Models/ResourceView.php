<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceView extends Model
{
    protected $fillable = [
        'viewer_user_id',
        'resource_type',
        'resource_id',
        'viewed_on',
    ];

    protected function casts(): array
    {
        return [
            'viewed_on' => 'date',
        ];
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_user_id');
    }
}
