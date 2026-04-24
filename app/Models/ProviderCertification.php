<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderCertification extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider_id',
        'title',
        'issuer',
        'file_url',
        'issued_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'  => 'date',
            'expires_at' => 'date',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
