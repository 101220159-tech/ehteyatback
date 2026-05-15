<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'customer_id',
        'provider_id',
        'service_id',
        'price',
        'scheduled_at',
        'duration_minutes',
        'status',
        'customer_notes',
        'customer_latitude',
        'customer_longitude',
        'customer_address',
        'accepted_at',
        'completed_at',
        'cancelled_at',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at'      => 'datetime',
            'accepted_at'       => 'datetime',
            'completed_at'      => 'datetime',
            'cancelled_at'      => 'datetime',
            'reminder_sent_at'  => 'datetime',
            'customer_latitude' => 'decimal:8',
            'customer_longitude'=> 'decimal:8',
            'duration_minutes'  => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function rescheduleRequests(): HasMany
    {
        return $this->hasMany(BookingRescheduleRequest::class);
    }

    public function latestRescheduleRequest(): HasOne
    {
        return $this->hasOne(BookingRescheduleRequest::class)->latestOfMany();
    }

    public function earning(): HasOne
    {
        return $this->hasOne(ProviderEarning::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(ProviderSchedule::class);
    }
}
