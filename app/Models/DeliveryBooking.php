<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryBooking extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'pickup_location',
        'dropoff_location',
        'vehicle_type',
        'package_weight',
        'package_quantity',
        'package_type',
        'fragile',
        'distance_km',
        'estimated_duration_minutes',
        'shipping_price',
        'route_data',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'pickup_location'            => 'array',
            'dropoff_location'           => 'array',
            'package_weight'           => 'decimal:2',
            'package_quantity'         => 'integer',
            'fragile'                  => 'boolean',
            'distance_km'              => 'decimal:3',
            'estimated_duration_minutes'=> 'decimal:2',
            'shipping_price'           => 'decimal:2',
            'route_data'               => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
