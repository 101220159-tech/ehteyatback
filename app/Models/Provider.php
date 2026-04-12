<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    protected $fillable = [
        'user_id',
        'bio',
        'experience_years',
        'rating_avg',
        'total_reviews',
        // Verification workflow fields (may exist depending on which migrations were applied).
        'status',
        'is_verified',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'experience_years' => 'integer',
            'rating_avg' => 'decimal:2',
            'total_reviews' => 'integer',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function providerServices(): HasMany
    {
        return $this->hasMany(ProviderService::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'provider_services')
            ->withPivot(['id', 'price'])
            ->withTimestamps();
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'provider_zones')
            ->withTimestamps();
    }

    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(ProviderAvailability::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }
}
