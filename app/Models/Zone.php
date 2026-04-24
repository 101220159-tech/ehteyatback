<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'description'];

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'provider_zones')->withTimestamps();
    }
}
