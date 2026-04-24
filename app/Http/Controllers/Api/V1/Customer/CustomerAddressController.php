<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    /**
     * GET /customer/addresses
     * Return the customer's current address and location from the users table.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'address'      => $user->address,
                'latitude'     => $user->latitude,
                'longitude'    => $user->longitude,
                'has_location' => $user->latitude !== null && $user->longitude !== null,
            ],
        ]);
    }

    /**
     * POST /customer/addresses
     * Set / update the customer's address and location.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address'   => ['required', 'string', 'max:2048'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $request->user()->update($data);

        return response()->json([
            'message' => 'Address saved.',
            'data'    => new UserResource($request->user()->fresh()->loadForApiSerialization()),
        ]);
    }

    /**
     * PUT /customer/addresses/{id}  — kept for route compatibility, behaves same as store.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * DELETE /customer/addresses/{id} — clears the address fields.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->update([
            'address'   => null,
            'latitude'  => null,
            'longitude' => null,
        ]);

        return response()->json(['message' => 'Address cleared.']);
    }
}
