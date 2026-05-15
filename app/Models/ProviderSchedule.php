<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderSchedule extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider_id',
        'booking_id',
        'scheduled_date',
        'scheduled_time',
        'duration_minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date'   => 'date',
            'duration_minutes' => 'integer',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
