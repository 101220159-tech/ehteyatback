<?php

namespace App\Models;

use App\Mail\PasswordResetEmail;
use App\Mail\VerifyEmailMail;
use App\Traits\HasNotifications;
use App\Traits\HasRolesAndPermissions;
use Database\Factories\UserFactory;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword, HasApiTokens, HasFactory, HasNotifications, HasRolesAndPermissions, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'phone',
        'role_id',
        'latitude',
        'longitude',
        'address',
        'fcm_token',
        'avatar_url',
        'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────────

    public function provider(): HasOne
    {
        return $this->hasOne(Provider::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function customerReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'customer_id');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class, 'customer_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function loadForApiSerialization(): static
    {
        return $this->loadMissing(['role.permissions', 'provider', 'directPermissions']);
    }

    public function signedEmailVerificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'api.v1.verification.verify',
            now()->addMinutes(60),
            [
                'id'   => $this->getKey(),
                'hash' => sha1($this->getEmailForVerification()),
            ]
        );
    }

    public function sendEmailVerificationNotification(): void
    {
        Mail::to($this->email)->queue(new VerifyEmailMail($this));
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $email    = urlencode($this->getEmailForPasswordReset());
        $resetUrl = rtrim(config('app.url'), '/').'/api/v1/auth/reset-password?token='.$token.'&email='.$email;

        Mail::to($this->getEmailForPasswordReset())->queue(new PasswordResetEmail($this, $resetUrl));
    }
}
