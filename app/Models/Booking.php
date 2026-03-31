<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'client_id', 'provider_id', 'service_id', 'booking_date', 'status',
        'client_latitude', 'client_longitude', 'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'client_latitude' => 'decimal:8',
            'client_longitude' => 'decimal:8',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function customer(): BelongsTo
    {
        return $this->client();
    }

    public function user(): BelongsTo
    {
        return $this->client();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
