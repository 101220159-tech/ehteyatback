<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxiBooking extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'pickup_location',
        'destination_location',
        'passenger_count',
        'vehicle_type',
        'distance_km',
        'estimated_duration_minutes',
        'estimated_price',
        'route_data',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'pickup_location'              => 'array',
            'destination_location'       => 'array',
            'passenger_count'            => 'integer',
            'distance_km'                => 'decimal:3',
            'estimated_duration_minutes'   => 'decimal:2',
            'estimated_price'            => 'decimal:2',
            'route_data'                 => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
