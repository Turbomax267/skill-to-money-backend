<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_IN_ESCROW = 'in_escrow';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED_FOR_REVIEW = 'submitted_for_review';
    public const STATUS_REVISION_REQUESTED = 'revision_requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RELEASED = 'released';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'contract_number',
        'mype_profile_id',
        'freelancer_profile_id',
        'service_id',
        'client_project_id',
        'title',
        'description',
        'amount',
        'currency',
        'status',
        'provider',
        'terms',
        'started_at',
        'submitted_at',
        'approved_at',
        'released_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'terms' => 'array',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'released_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function mypeProfile(): BelongsTo
    {
        return $this->belongsTo(MypeProfile::class);
    }

    public function freelancerProfile(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function clientProject(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function escrow(): HasOne
    {
        return $this->hasOne(Escrow::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function deliveryFiles(): HasManyThrough
    {
        return $this->hasManyThrough(DeliveryFile::class, Delivery::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }
}
