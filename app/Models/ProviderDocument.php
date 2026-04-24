<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderDocument extends Model
{
    use HasUuids;

    protected $fillable = ['provider_id', 'type', 'title', 'file_url'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
