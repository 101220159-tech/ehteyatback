<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Services\ZoneDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerZoneController extends Controller
{
    public function __construct(protected ZoneDetectionService $detector) {}

    /**
     * GET /customer/zones/detect?latitude=...&longitude=...
     * Returns the zone(s) that cover the given coordinates.
     */
    public function detect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $zones = $this->detector->detectZones((float) $data['latitude'], (float) $data['longitude']);

        return response()->json([
            'found'   => $zones->isNotEmpty(),
            'zones'   => $zones->map(fn ($z) => ['id' => $z->id, 'name' => $z->name])->values(),
        ]);
    }
}
