<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\FcmTokenRequest;
use App\Http\Requests\Auth\ProfileUpdateRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Mail\WelcomeEmail;
use App\Models\Provider;
use App\Models\Role;
use App\Models\User;
use App\Services\FirebaseService;
use App\Support\LebanonMobilePhone;
use App\Traits\HandlesImageUpload;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Registration, login, profile, password flows, and device FCM token.
 *
 * @group Authentication
 */
class AuthController extends Controller
{
    use HandlesImageUpload;

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $asProvider = $request->boolean('register_as_provider')
            || strtolower((string) ($data['role'] ?? '')) === 'provider';

        $roleName = $asProvider ? 'provider' : 'customer';
        $roleId = Role::query()->where('name', $roleName)->value('id')
            ?? throw ValidationException::withMessages(['role' => ['Role not configured. Run seeders.']]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $roleId,
            'phone' => $asProvider
                ? LebanonMobilePhone::normalize($data['provider_phone'] ?? $data['phone'] ?? '')
                : ($data['phone'] ?? null),
        ]);

        if ($asProvider) {
            Provider::query()->create([
                'user_id' => $user->id,
            ]);
        }

        Mail::to($user->email)->queue(new WelcomeEmail($user));

        $device = $request->input('device_name', 'api');
        $token = $user->createToken($device)->plainTextToken;

        return response()->json([
            'message' => 'Registered successfully. Please verify your email.',
            'token' => $token,
            'user' => new UserResource($user->loadForApiSerialization()),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Only enforce email verification for providers and admins.
        if (! $user->hasVerifiedEmail() && $user->hasRole(['provider', 'admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Email address is not verified.',
                'errors' => ['email' => ['Please verify your email before logging in.']],
            ], 403);
        }

        $device = $request->input('device_name', 'api');
        $token = $user->createToken($device)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->loadForApiSerialization()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->loadForApiSerialization());
    }

    /**
     * Lightweight payload for SPAs: current user's role name and effective permission names only.
     *
     * @group Authentication
     */
    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user()->loadForApiSerialization();

        return response()->json([
            'role_name' => $user->role_name,
            'permissions' => $user->effectivePermissionNames(),
        ]);
    }

    /**
     * Debug / partner testing: mirrors dashboard checks your React app can use.
     *
     * @group Authentication
     */
    public function permissionsTest(Request $request): JsonResponse
    {
        $user = $request->user()->loadForApiSerialization();

        return response()->json([
            'message' => 'Use this to test your frontend permissions logic.',
            'role_name' => $user->role_name,
            'permissions' => $user->effectivePermissionNames(),
            'example_checks' => [
                'is_customer' => $user->hasRole('customer'),
                'is_provider' => $user->hasRole('provider'),
                'is_admin' => $user->hasRole(['super_admin', 'admin']),
                'can_create_booking' => $user->hasPermission('create_bookings'),
                'can_manage_services' => $user->hasPermission('manage_services'),
                'can_manage_users' => $user->hasPermission('manage_users'),
            ],
        ]);
    }

    public function updateProfile(ProfileUpdateRequest $request): UserResource
    {
        $user = $request->user();
        $data = $request->safe()->except(['avatar']);
        if ($request->hasFile('avatar')) {
            $path = $this->uploadAvatar($request->file('avatar'));
            $data['avatar_url'] = $this->publicStorageUrl($path);
        }
        $user->update($data);

        return new UserResource($user->fresh()->loadForApiSerialization());
    }

    public function saveFcmToken(FcmTokenRequest $request, FirebaseService $firebase): JsonResponse
    {
        $firebase->saveToken($request->user(), $request->input('fcm_token'));

        return response()->json(['message' => 'FCM token saved.']);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->validated()['password'],
        ]);

        return response()->json(['message' => 'Password updated.']);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->sendResetLink($request->validated());

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status), 'errors' => ['email' => [__($status)]]], 422);
        }

        return response()->json(['message' => __($status)]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $status = Password::broker()->reset(
            $payload,
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status), 'errors' => ['email' => [__($status)]]], 422);
        }

        return response()->json(['message' => __($status)]);
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link.', 'errors' => []], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Already verified.']);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['message' => 'Email verified successfully.']);
    }
}
