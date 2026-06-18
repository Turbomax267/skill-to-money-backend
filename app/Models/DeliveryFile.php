<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryFile extends Model
{
    protected $fillable = [
        'delivery_id',
        'original_name',
        'file_path',
        'mime_type',
        'size',
        'is_preview',
        'is_final',
        'downloadable',
        'watermark_text',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_preview' => 'boolean',
            'is_final' => 'boolean',
            'downloadable' => 'boolean',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
