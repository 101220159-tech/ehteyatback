<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ProfileUpdateRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\FcmTokenRequest;
use App\Http\Resources\AuthUserResource;
use App\Http\Resources\UserResource;
use App\Jobs\SendEmailJob;
use App\Mail\AccountPendingMail;
use App\Mail\WelcomeEmail;
use App\Models\Provider;
use App\Models\Role;
use App\Models\User;
use App\Services\FirebaseService;
use App\Support\Frontend;
use App\Traits\HandlesImageUpload;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

/**
 * @group Authentication
 *
 * Registration, login, Google OAuth, profile, and password flows.
 */
class AuthController extends Controller
{
    use HandlesImageUpload;

    // ── Standard registration ──────────────────────────────────────────────

    /**
     * Register a new user (customer or provider).
     *
     * @bodyParam name string required Full name.
     * @bodyParam email string required Email address.
     * @bodyParam password string required Min 8 chars, confirmed.
     * @bodyParam password_confirmation string required Must match password.
     * @bodyParam role string optional customer|provider (default: customer).
     * @bodyParam phone string optional Phone number.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data       = $request->validated();
        $asProvider = $request->boolean('register_as_provider')
            || strtolower((string) ($data['role'] ?? '')) === 'provider';

        $roleName = $asProvider ? 'provider' : 'customer';
        $roleId   = Role::query()->where('name', $roleName)->value('id')
            ?? throw ValidationException::withMessages(['role' => ['Role not configured. Run seeders.']]);

        $user = User::query()->create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'role_id'  => $roleId,
            'phone'    => $data['phone'] ?? null,
        ]);

        if ($asProvider) {
            Provider::query()->create(['user_id' => $user->id]);
            SendEmailJob::dispatchSync($user->email, new AccountPendingMail($user));
        } else {
            SendEmailJob::dispatchSync($user->email, new WelcomeEmail($user));
        }

        $token = $user->createToken($request->input('device_name', 'api'))->plainTextToken;

        return response()->json([
            'message' => 'Registered successfully. Please verify your email.',
            'token'   => $token,
            'user'    => new UserResource($user->loadForApiSerialization()),
        ], 201);
    }

    // ── Standard login ─────────────────────────────────────────────────────

    /**
     * Login with email and password.
     *
     * @bodyParam email string required
     * @bodyParam password string required
     * @bodyParam device_name string optional Token name. Default: "api"
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $user = User::query()->where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => [__('auth.failed')],
                ]);
            }

            if (! $user->hasVerifiedEmail() && $user->hasRole(['provider', 'admin', 'super_admin'])) {
                return response()->json([
                    'message' => 'Email address is not verified.',
                    'errors'  => ['email' => ['Please verify your email before logging in.']],
                ], 403);
            }

            $user->loadForApiSerialization();
            $token = $user->createToken($request->input('device_name', 'api'))->plainTextToken;

            return response()->json([
                'token' => $token,
                'user'  => new AuthUserResource($user),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Login database error', ['exception' => $e->getMessage()]);

            return response()->json([
                'message' => 'Database is unavailable. Start MySQL/MariaDB and check DB_* in .env.',
            ], 503);
        } catch (\Throwable $e) {
            Log::error('Login failed', ['exception' => $e]);

            return response()->json([
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : 'Login failed. Please try again.',
            ], 500);
        }
    }

    // ── Google OAuth ───────────────────────────────────────────────────────

    /**
     * Redirect to Google OAuth (for browser-based flows).
     */
    public function googleRedirect(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle Google OAuth callback.
     *
     * Returns a Sanctum token on success. The frontend should exchange
     * the `code` from the Google popup for a token here.
     *
     * @bodyParam token string required The ID token from Google Sign-In (for mobile/SPA flows).
     */
    public function googleCallback(Request $request): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable) {
            return response()->json(['message' => 'Invalid Google token.'], 422);
        }

        $customerRoleId = Role::query()->where('name', 'customer')->value('id');

        $user = User::query()->firstOrCreate(
            ['google_id' => $googleUser->getId()],
            [
                'name'              => $googleUser->getName(),
                'email'             => $googleUser->getEmail(),
                'google_id'         => $googleUser->getId(),
                'avatar_url'        => $googleUser->getAvatar(),
                'email_verified_at' => now(),
                'password'          => null,
                'role_id'           => $customerRoleId,
            ]
        );

        // If user registered by email before, link the Google ID
        if (! $user->google_id) {
            $user->update([
                'google_id'  => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar() ?? $user->avatar_url,
            ]);
        }

        $token = $user->createToken('google-oauth')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user->loadForApiSerialization()),
        ]);
    }

    // ── Profile ────────────────────────────────────────────────────────────

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): AuthUserResource
    {
        return new AuthUserResource($request->user()->loadForApiSerialization());
    }

    /**
     * Update own profile (name, phone, avatar, address, location).
     */
    public function updateProfile(ProfileUpdateRequest $request): UserResource
    {
        $user = $request->user();
        $data = $request->safe()->except(['avatar']);

        if ($request->hasFile('avatar')) {
            $data['avatar_url'] = $this->fileToDataUri($request->file('avatar'));
        }

        $user->update($data);

        return new UserResource($user->fresh()->loadForApiSerialization());
    }

    /**
     * Logout (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Return the authenticated user's role and permissions (for SPA routing).
     */
    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user()->loadForApiSerialization();

        return response()->json([
            'role_name'   => $user->role?->name,
            'permissions' => $user->effectivePermissionNames(),
        ]);
    }

    // ── Password ───────────────────────────────────────────────────────────

    /**
     * Change password (authenticated).
     *
     * @bodyParam current_password string required
     * @bodyParam password string required New password, confirmed.
     * @bodyParam password_confirmation string required
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->update(['password' => $request->validated()['password']]);

        return response()->json(['message' => 'Password updated.']);
    }

    /**
     * Send password reset link.
     *
     * @bodyParam email string required
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->sendResetLink($request->validated());

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status), 'errors' => ['email' => [__($status)]]], 422);
        }

        return response()->json(['message' => __($status)]);
    }

    /**
     * Reset password using the token from the email link.
     *
     * @bodyParam token string required
     * @bodyParam email string required
     * @bodyParam password string required New password, confirmed.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->validated(),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status), 'errors' => ['email' => [__($status)]]], 422);
        }

        return response()->json(['message' => __($status)]);
    }

    // ── Email verification ─────────────────────────────────────────────────

    /**
     * Verify email via signed link.
     */
    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::query()->with('role')->findOrFail($id);

        $roleName = $user->role?->name ?? 'customer';
        $dashboard = match (true) {
            in_array($roleName, ['admin', 'super_admin']) => Frontend::url('admin/dashboard'),
            $roleName === 'provider'                      => Frontend::url('provider/dashboard'),
            default                                       => Frontend::url('customer/dashboard'),
        };

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect($dashboard . '?verified=error');
        }

        if (! $user->hasVerifiedEmail()) {
            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }
        }

        return redirect($dashboard . '?verified=1');
    }

    // ── FCM token ──────────────────────────────────────────────────────────

    /**
     * Save FCM device token for push notifications.
     *
     * @bodyParam fcm_token string required
     */
    public function saveFcmToken(FcmTokenRequest $request, FirebaseService $firebase): JsonResponse
    {
        $firebase->saveToken($request->user(), $request->input('fcm_token'));

        return response()->json(['message' => 'FCM token saved.']);
    }
}
