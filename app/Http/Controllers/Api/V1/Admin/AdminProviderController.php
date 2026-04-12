<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderResource;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Provider::query()
            ->with('user')
            ->withCount('reviews')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ProviderResource::collection($items)->response();
    }

    public function show(int $id): ProviderResource
    {
        return new ProviderResource(
            Provider::query()->with(['user', 'services.category'])->withCount('reviews')->findOrFail($id)
        );
    }

    /**
     * Verify / unverify a provider (toggle verification + role).
     *
     * POST /api/v1/admin/providers/{id}/verify
     * Body: { "is_verified": true|false, "verification_notes"?: string }
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $provider = Provider::query()->with('user')->findOrFail($id);

        $data = $request->validate([
            'is_verified' => ['required', 'boolean'],
            // Accepted for frontend compatibility; we don't store it in the current schema.
            'verification_notes' => ['nullable', 'string'],
        ]);

        $shouldVerify = (bool) $data['is_verified'];

        // Role assignment drives permissions (role has provider permissions via RolePermissionSeeder).
        $provider->user?->assignRole($shouldVerify ? 'provider' : 'customer');

        // Login requires email verification (AuthController checks hasVerifiedEmail()).
        // When the admin verifies the provider, we also mark their email as verified.
        if ($provider->user) {
            if ($shouldVerify) {
                // Use forceFill so it works even if `email_verified_at` is not mass-assignable.
                $provider->user->forceFill(['email_verified_at' => now()])->save();
            } else {
                $provider->user->forceFill(['email_verified_at' => null])->save();
            }
        }

        $updates = [];
        if (Schema::hasColumn('providers', 'is_verified')) {
            $updates['is_verified'] = $shouldVerify;
        }
        if (Schema::hasColumn('providers', 'verified_at')) {
            $updates['verified_at'] = $shouldVerify ? now() : null;
        }
        if (Schema::hasColumn('providers', 'status')) {
            $updates['status'] = $shouldVerify ? 'active' : 'pending';
        }

        if ($updates !== []) {
            $provider->update($updates);
        }

        $provider = $provider->fresh(['user', 'services.category'])->loadCount('reviews');

        return response()->json([
            'success' => true,
            'message' => $shouldVerify ? 'Provider verified successfully' : 'Provider unverified',
            'data' => new ProviderResource($provider),
        ]);
    }

    /**
     * Approve a provider (verified + provider role).
     *
     * POST /api/v1/admin/providers/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        $provider = Provider::query()->with('user')->findOrFail($id);

        // Role assignment + permissions
        $provider->user?->assignRole('provider');

        // Login requires email verification (AuthController checks hasVerifiedEmail()).
        if ($provider->user) {
            // Use forceFill so it works even if `email_verified_at` is not mass-assignable.
            $provider->user->forceFill(['email_verified_at' => now()])->save();
        }

        $updates = [];
        if (Schema::hasColumn('providers', 'is_verified')) {
            $updates['is_verified'] = true;
        }
        if (Schema::hasColumn('providers', 'verified_at')) {
            $updates['verified_at'] = now();
        }
        if (Schema::hasColumn('providers', 'status')) {
            $updates['status'] = 'active';
        }

        if ($updates !== []) {
            $provider->update($updates);
        }

        $provider = $provider->fresh(['user', 'services.category'])->loadCount('reviews');

        return response()->json([
            'success' => true,
            'message' => 'Provider approved and verified successfully',
            'data' => new ProviderResource($provider),
        ]);
    }
}
