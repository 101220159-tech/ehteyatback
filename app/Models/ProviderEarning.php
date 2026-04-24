<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderEarning extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider_id',
        'customer_id',
        'booking_id',
        'amount',
        'earned_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'    => 'decimal:2',
            'earned_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
