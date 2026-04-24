<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider_id',
        'amount',
        'type',
        'description',
        'paid_at',
        'expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:2',
            'paid_at'    => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
