<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderService extends Model
{
    use HasUuids;

    protected $fillable = ['provider_id', 'service_id', 'price'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Bookings for this provider + catalog service. */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'service_id', 'service_id')
            ->whereColumn('bookings.provider_id', 'provider_services.provider_id');
    }
}
