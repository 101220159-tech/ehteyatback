<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'client_id', 'provider_id', 'rating', 'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function customer(): BelongsTo
    {
        return $this->client();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
