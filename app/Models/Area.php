<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Area extends Model
{
    use HasUuids;

    protected $fillable = ['zone_id', 'name', 'coordinates'];

    protected function casts(): array
    {
        return [
            // Coordinates is a JSON array of {lat, lng} objects sent from Google Maps frontend
            'coordinates' => 'array',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
