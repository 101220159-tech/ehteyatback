<?php

namespace App\Models;

use App\Mail\PasswordResetEmail;
use App\Mail\VerifyEmailMail;
use App\Traits\HasNotifications;
use App\Traits\HasRolesAndPermissions;
use Database\Factories\UserFactory;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone', 'role_id', 'latitude', 'longitude', 'fcm_token', 'avatar_url'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword, HasApiTokens, HasFactory, HasNotifications, HasRolesAndPermissions;

    /**
     * Eager-load relations used by {@see \App\Http\Resources\UserResource} (role, nested permissions, provider, direct grants).
     */
    public function loadForApiSerialization(): static
    {
        return $this->loadMissing(['role.permissions', 'provider', 'directPermissions']);
    }

    /** The associated role's name (`roles.name`); use for APIs such as `GET /auth/permissions`. */
    protected function roleName(): Attribute
    {
        return Attribute::get(fn () => $this->role?->name);
    }

    public function signedEmailVerificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'api.v1.verification.verify',
            now()->addMinutes(60),
            [
                'id' => $this->getKey(),
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
        $email = urlencode($this->getEmailForPasswordReset());
        $resetUrl = rtrim(config('app.url'), '/').'/api/v1/auth/reset-password?token='.$token.'&email='.$email;

        Mail::to($this->getEmailForPasswordReset())->queue(new PasswordResetEmail($this, $resetUrl));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function provider(): HasOne
    {
        return $this->hasOne(Provider::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_id');
    }

    public function customerReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'client_id');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class, 'client_id');
    }
}
