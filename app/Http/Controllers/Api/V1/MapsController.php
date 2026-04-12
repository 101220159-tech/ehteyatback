<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GoogleMapsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapsController extends Controller
{
    public function geocode(Request $request, GoogleMapsService $maps): JsonResponse
    {
        $data = $request->validate([
            'address' => ['required', 'string', 'max:500'],
            'language' => ['nullable', 'string', 'max:10'],
        ]);

        try {
            $result = $maps->geocode($data['address']);

            return response()->json(['result' => $result]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Geocoding failed.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function placesAutocomplete(Request $request, GoogleMapsService $maps): JsonResponse
    {
        $data = $request->validate([
            'input' => ['required', 'string', 'max:200'],
        ]);

        try {
            $result = $maps->autocomplete($data['input']);

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Places autocomplete failed.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function distanceMatrix(Request $request, GoogleMapsService $maps): JsonResponse
    {
        $data = $request->validate([
            'origin_lat' => ['required', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required', 'numeric', 'between:-180,180'],
            'destination_lat' => ['required', 'numeric', 'between:-90,90'],
            'destination_lng' => ['required', 'numeric', 'between:-180,180'],
            'mode' => ['nullable', 'string', 'in:driving,walking,bicycling,transit'],
        ]);

        try {
            $result = $maps->distanceMatrix(
                (float) $data['origin_lat'],
                (float) $data['origin_lng'],
                (float) $data['destination_lat'],
                (float) $data['destination_lng'],
                (string) ($data['mode'] ?? 'driving'),
            );

            return response()->json(['result' => $result]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Distance calculation failed.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}

