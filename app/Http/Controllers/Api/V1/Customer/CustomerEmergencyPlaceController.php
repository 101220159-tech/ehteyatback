<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\EmergencyPlaceFavorite;
use App\Models\EmergencyPlaceHistory;
use App\Services\GooglePlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerEmergencyPlaceController extends Controller
{
    public function nearby(Request $request, GooglePlacesService $places): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'type' => ['required', 'in:hospital,pharmacy'],
            'radius' => ['nullable', 'integer', 'min:500', 'max:50000'],
        ]);

        try {
            $rows = $places->nearbyEmergency(
                (float) $data['latitude'],
                (float) $data['longitude'],
                $data['type'],
                (int) ($data['radius'] ?? 8000),
            );

            return response()->json([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    public function historyIndex(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 30), 100);

        $rows = EmergencyPlaceHistory::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function historyStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'place_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'place_type' => ['required', 'in:hospital,pharmacy'],
            'distance_km' => ['nullable', 'numeric', 'min:0'],
            'action' => ['nullable', 'in:view,directions,search'],
        ]);

        $row = EmergencyPlaceHistory::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
            'action' => $data['action'] ?? 'view',
        ]);

        return response()->json([
            'success' => true,
            'data' => $row,
        ], 201);
    }

    public function favoritesIndex(Request $request): JsonResponse
    {
        $rows = EmergencyPlaceFavorite::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function favoritesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'place_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'place_type' => ['required', 'in:hospital,pharmacy'],
            'phone' => ['nullable', 'string', 'max:64'],
        ]);

        $row = EmergencyPlaceFavorite::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'place_id' => $data['place_id'],
            ],
            [...$data, 'user_id' => $request->user()->id],
        );

        return response()->json([
            'success' => true,
            'data' => $row,
        ], 201);
    }

    public function favoritesDestroy(Request $request, int $id): JsonResponse
    {
        $row = EmergencyPlaceFavorite::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'Removed from favorites',
        ]);
    }
}
