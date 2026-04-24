<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin — Areas
 *
 * Manage areas inside zones. Each area carries a polygon
 * (array of {lat, lng} coordinates) drawn on Google Maps.
 */
class AdminAreaController extends Controller
{
    /**
     * List all areas in a zone.
     */
    public function index(string $zoneId): JsonResponse
    {
        $zone  = Zone::findOrFail($zoneId);
        $areas = $zone->areas()->orderBy('name')->get();

        return response()->json(['data' => $areas]);
    }

    /**
     * Create an area inside a zone.
     *
     * @bodyParam name string required Neighbourhood name. Example: "Hamra"
     * @bodyParam coordinates array required Array of {lat, lng} objects from Google Maps frontend.
     * @bodyParam coordinates[].lat number required Latitude.
     * @bodyParam coordinates[].lng number required Longitude.
     */
    public function store(Request $request, string $zoneId): JsonResponse
    {
        $zone = Zone::findOrFail($zoneId);

        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'coordinates'         => 'required|array|min:3',
            'coordinates.*.lat'   => 'required|numeric|between:-90,90',
            'coordinates.*.lng'   => 'required|numeric|between:-180,180',
        ]);

        $area = Area::create([
            'zone_id'     => $zone->id,
            'name'        => $data['name'],
            'coordinates' => $data['coordinates'],
        ]);

        return response()->json(['message' => 'Area created.', 'data' => $area], 201);
    }

    /**
     * Show a single area.
     */
    public function show(string $zoneId, string $areaId): JsonResponse
    {
        $area = Area::where('zone_id', $zoneId)->findOrFail($areaId);

        return response()->json(['data' => $area]);
    }

    /**
     * Update an area.
     */
    public function update(Request $request, string $zoneId, string $areaId): JsonResponse
    {
        $area = Area::where('zone_id', $zoneId)->findOrFail($areaId);

        $data = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'coordinates'         => 'sometimes|array|min:3',
            'coordinates.*.lat'   => 'required_with:coordinates|numeric|between:-90,90',
            'coordinates.*.lng'   => 'required_with:coordinates|numeric|between:-180,180',
        ]);

        $area->update($data);

        return response()->json(['message' => 'Area updated.', 'data' => $area->fresh()]);
    }

    /**
     * Delete an area.
     */
    public function destroy(string $zoneId, string $areaId): JsonResponse
    {
        Area::where('zone_id', $zoneId)->findOrFail($areaId)->delete();

        return response()->json(['message' => 'Area deleted.']);
    }
}
