<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyPlaceHistory extends Model
{
    protected $fillable = [
        'user_id',
        'place_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'place_type',
        'distance_km',
        'action',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'distance_km' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
