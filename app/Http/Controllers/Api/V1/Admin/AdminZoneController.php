<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Admin — Zones
 *
 * Manage geographic zones. Each zone groups multiple areas.
 */
class AdminZoneController extends Controller
{
    /**
     * List all zones with their areas.
     */
    public function index(): JsonResponse
    {
        $zones = Zone::with('areas')->orderBy('name')->get();

        return response()->json(['data' => $zones]);
    }

    /**
     * Create a zone.
     *
     * @bodyParam name string required Zone name. Example: "Beirut West"
     * @bodyParam description string optional Short description.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:zones,name',
            'description' => 'nullable|string',
        ]);

        $zone = Zone::create($data);

        return response()->json(['message' => 'Zone created.', 'data' => $zone->load('areas')], 201);
    }

    /**
     * Show a single zone with its areas.
     */
    public function show(string $id): JsonResponse
    {
        $zone = Zone::with('areas')->findOrFail($id);

        return response()->json(['data' => $zone]);
    }

    /**
     * Update a zone.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $zone = Zone::findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255|unique:zones,name,'.$id,
            'description' => 'nullable|string',
        ]);

        $zone->update($data);

        return response()->json(['message' => 'Zone updated.', 'data' => $zone->fresh()->load('areas')]);
    }

    /**
     * Delete a zone (cascades to its areas and clears provider zone assignment).
     */
    public function destroy(string $id): JsonResponse
    {
        Zone::findOrFail($id)->delete();

        return response()->json(['message' => 'Zone deleted.']);
    }

    /**
     * Assign a provider to multiple zones at once.
     *
     * @bodyParam zone_ids array required Array of zone UUIDs.
     * @bodyParam zone_ids[] string required Zone UUID.
     */
    public function assignProviderToZones(Request $request, string $providerId): JsonResponse
    {
        $provider = \App\Models\Provider::findOrFail($providerId);

        $data = $request->validate([
            'zone_ids'   => 'required|array|min:1',
            'zone_ids.*' => 'uuid|exists:zones,id',
        ]);

        $provider->zones()->syncWithoutDetaching($data['zone_ids']);

        return response()->json([
            'message'  => count($data['zone_ids']).' zone(s) assigned to provider.',
            'zone_ids' => $data['zone_ids'],
        ]);
    }

    /**
     * List providers assigned to a zone.
     */
    public function zoneProviders(string $id): JsonResponse
    {
        $zone = Zone::with('providers.user')->findOrFail($id);

        return response()->json(['data' => $zone->providers]);
    }

    /**
     * Assign one or more providers to a zone.
     *
     * @bodyParam provider_ids array required Array of provider UUIDs.
     * @bodyParam provider_ids[] string required Provider UUID.
     */
    public function assignProvider(Request $request, string $id): JsonResponse
    {
        $zone = Zone::findOrFail($id);

        $data = $request->validate([
            'provider_ids'   => 'required|array|min:1',
            'provider_ids.*' => 'uuid|exists:providers,id',
        ]);

        // syncWithoutDetaching keeps existing assignments intact
        $zone->providers()->syncWithoutDetaching($data['provider_ids']);

        return response()->json([
            'message'      => count($data['provider_ids']).' provider(s) assigned to zone.',
            'zone_id'      => $zone->id,
            'provider_ids' => $data['provider_ids'],
        ]);
    }

    /**
     * Remove a provider from a zone.
     */
    public function removeProvider(string $id, string $providerId): JsonResponse
    {
        $zone = Zone::findOrFail($id);
        $zone->providers()->detach($providerId);

        return response()->json(['message' => 'Provider removed from zone.']);
    }
}
