<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin — Payments & Subscriptions
 *
 * Record provider payments and manage subscription expiry.
 */
class AdminPaymentController extends Controller
{
    /**
     * List all payments (filterable by provider / status / type).
     *
     * @queryParam provider_id string Filter by provider UUID.
     * @queryParam status string Filter by status: pending|completed|expired.
     * @queryParam type string Filter by type: subscription|vip_upgrade.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with('provider.user')
            ->when($request->provider_id, fn ($q) => $q->where('provider_id', $request->provider_id))
            ->when($request->status,      fn ($q) => $q->where('status', $request->status))
            ->when($request->type,        fn ($q) => $q->where('type', $request->type))
            ->latest('paid_at');

        return response()->json(['data' => $query->paginate(20)]);
    }

    /**
     * Record a payment from a provider and update their subscription / VIP status.
     *
     * @bodyParam provider_id string required Provider UUID.
     * @bodyParam amount number required Amount paid. Example: 50.00
     * @bodyParam type string required subscription|vip_upgrade
     * @bodyParam description string optional Notes.
     * @bodyParam paid_at string required ISO 8601 datetime. Example: 2026-04-16T10:00:00Z
     * @bodyParam expires_at string required ISO 8601 subscription expiry. Example: 2026-07-16T10:00:00Z
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id' => 'required|uuid|exists:providers,id',
            'amount'      => 'required|numeric|min:0',
            'type'        => 'required|in:subscription,vip_upgrade',
            'description' => 'nullable|string',
            'paid_at'     => 'required|date',
            'expires_at'  => 'required|date|after:paid_at',
        ]);

        $payment = Payment::create([
            ...$data,
            'status' => 'completed',
        ]);

        // Update provider status based on payment type
        $provider = Provider::findOrFail($data['provider_id']);

        if ($data['type'] === 'subscription') {
            $provider->update([
                'subscription_expires_at' => $data['expires_at'],
                'is_active'               => true,
            ]);
        } elseif ($data['type'] === 'vip_upgrade') {
            $provider->update(['is_vip' => true]);
        }

        return response()->json([
            'message' => 'Payment recorded and provider updated.',
            'data'    => $payment->load('provider.user'),
        ], 201);
    }

    /**
     * Show a single payment.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => Payment::with('provider.user')->findOrFail($id)]);
    }

    /**
     * Toggle allow_chat for a provider (admin only).
     *
     * @bodyParam allow_chat boolean required
     */
    public function toggleProviderChat(Request $request, string $providerId): JsonResponse
    {
        $data     = $request->validate(['allow_chat' => 'required|boolean']);
        $provider = Provider::findOrFail($providerId);
        $provider->update(['allow_chat' => $data['allow_chat']]);

        return response()->json(['message' => 'allow_chat updated.', 'allow_chat' => $provider->allow_chat]);
    }

    /**
     * Toggle VIP status for a provider (admin only).
     *
     * @bodyParam is_vip boolean required
     */
    public function toggleProviderVip(Request $request, string $providerId): JsonResponse
    {
        $data     = $request->validate(['is_vip' => 'required|boolean']);
        $provider = Provider::findOrFail($providerId);
        $provider->update(['is_vip' => $data['is_vip']]);

        return response()->json(['message' => 'VIP status updated.', 'is_vip' => $provider->is_vip]);
    }
}
