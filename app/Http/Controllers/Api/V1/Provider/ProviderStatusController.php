<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Provider — Status
 *
 * Provider toggles their own active/inactive status.
 * Blocked if subscription has expired.
 */
class ProviderStatusController extends Controller
{
    /**
     * Get current status.
     */
    public function show(Request $request): JsonResponse
    {
        $provider = $request->user()->provider;

        return response()->json([
            'is_active'               => $provider->is_active,
            'is_busy'                 => $provider->is_busy,
            'is_vip'                  => $provider->is_vip,
            'subscription_expires_at' => $provider->subscription_expires_at,
            'subscription_active'     => $provider->hasActiveSubscription(),
        ]);
    }

    /**
     * Toggle own active/inactive status.
     *
     * Provider cannot go active if their subscription has expired.
     *
     * @bodyParam is_active boolean required
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:online,busy,offline'],
        ]);

        $provider = $request->user()->provider;

        $isActive = $data['status'] !== 'offline';

        if ($isActive && ! $provider->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Your subscription has expired. Please renew to go active.',
                'errors'  => ['subscription' => ['Subscription expired.']],
            ], 403);
        }

        $provider->update([
            'is_active' => $isActive,
            'is_busy'   => $data['status'] === 'busy',
        ]);

        return response()->json([
            'message'   => 'Status updated.',
            'status'    => $data['status'],
            'is_active' => $provider->is_active,
            'is_busy'   => $provider->is_busy,
        ]);
    }
}
