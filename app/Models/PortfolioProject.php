<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortfolioProject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'freelancer_profile_id',
        'category_id',
        'title',
        'description',
        'image_path',
        'file_path',
        'external_url',
        'project_order',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'project_order' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class, 'freelancer_profile_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
