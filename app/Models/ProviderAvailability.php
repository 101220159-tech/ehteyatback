<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderAvailability extends Model
{
    use HasUuids;

    protected $table = 'provider_availability';

    protected $fillable = [
        'provider_id', 'day_of_week', 'start_time', 'end_time', 'is_available',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
