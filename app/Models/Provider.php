<?php

namespace App\Models;

use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'bio',
        'experience_years',
        'rating_avg',
        'total_reviews',
        'is_active',
        'is_busy',
        'is_verified',
        'verified_at',
        'is_vip',
        'subscription_expires_at',
        'allow_chat',
        'avatar_url',
        'latitude',
        'longitude',
        'google_place_id',
    ];

    protected function casts(): array
    {
        return [
            'experience_years'        => 'integer',
            'rating_avg'              => 'decimal:2',
            'total_reviews'           => 'integer',
            'is_active'               => 'boolean',
            'is_busy'                 => 'boolean',
            'is_verified'             => 'boolean',
            'is_vip'                  => 'boolean',
            'allow_chat'              => 'boolean',
            'verified_at'             => 'datetime',
            'subscription_expires_at' => 'datetime',
            'latitude'                => 'decimal:8',
            'longitude'               => 'decimal:8',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'provider_zones')->withTimestamps();
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(ProviderCertification::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProviderDocument::class);
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

    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(ProviderAvailability::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ProviderSchedule::class);
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function hasActiveSubscription(): bool
    {
        return $this->subscription_expires_at !== null
            && $this->subscription_expires_at->isFuture();
    }
}
