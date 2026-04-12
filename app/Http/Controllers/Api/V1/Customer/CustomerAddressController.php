<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Address\StoreAddressRequest;
use App\Http\Requests\Customer\Address\UpdateAddressRequest;
use App\Http\Resources\UserAddressResource;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = UserAddress::query()
            ->where('user_id', $request->user()->id)
            ->with('city')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15));

        return UserAddressResource::collection($addresses)->response();
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (($data['is_default'] ?? false) === true) {
            UserAddress::query()
                ->where('user_id', $request->user()->id)
                ->update(['is_default' => false]);
        }

        $address = UserAddress::query()->create([
            'user_id' => $request->user()->id,
            'address' => $data['address'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'city_id' => $data['city_id'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'address_type' => $data['address_type'] ?? 'home',
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return (new UserAddressResource($address->load('city')))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $id): UserAddressResource
    {
        $address = UserAddress::query()
            ->where('user_id', $request->user()->id)
            ->with('city')
            ->findOrFail($id);

        return new UserAddressResource($address);
    }

    public function update(UpdateAddressRequest $request, int $id): JsonResponse
    {
        $address = UserAddress::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $data = $request->validated();

        if (array_key_exists('is_default', $data) && (bool) $data['is_default'] === true) {
            UserAddress::query()
                ->where('user_id', $request->user()->id)
                ->update(['is_default' => false]);
        }

        // Prevent setting lat/lng to null if they're not included in the request.
        $address->fill($data);
        $address->save();

        return (new UserAddressResource($address->fresh()->load('city')))->response();
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $address = UserAddress::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $address->delete();

        return response()->json(['message' => 'Address deleted.']);
    }
}

