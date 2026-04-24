<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRescheduleRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'booking_id',
        'requested_by',
        'proposed_at',
        'message',
        'status',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'proposed_at'  => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
