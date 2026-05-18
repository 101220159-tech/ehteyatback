<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyPlaceFavorite extends Model
{
    protected $fillable = [
        'user_id',
        'place_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'place_type',
        'phone',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
