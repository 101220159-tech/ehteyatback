<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function userAddresses(): HasMany
    {
        return $this->hasMany(UserAddress::class, 'city_id');
    }
}

