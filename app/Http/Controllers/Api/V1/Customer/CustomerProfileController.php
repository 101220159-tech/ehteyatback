<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Traits\HandlesImageUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerProfileController extends Controller
{
    use HandlesImageUpload;

    public function storeAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $file    = $request->file('avatar');
        $mime    = $file->getMimeType();
        $dataUri = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file->getRealPath()));

        $request->user()->update(['avatar_url' => $dataUri]);

        return response()->json([
            'success'    => true,
            'message'    => 'Avatar updated.',
            'avatar_url' => $dataUri,
        ]);
    }

    public function update(Request $request): UserResource
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:50'],
            'address' => ['sometimes', 'string', 'max:2048'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
        ]);

        $user->update($data);

        return new UserResource($user->fresh()->loadForApiSerialization());
    }

    public function updateLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:2048'],
        ]);

        $user = $request->user();
        $user->update([
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'address' => $data['address'] ?? $user->address,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Location updated',
            'data' => [
                'latitude' => $user->latitude,
                'longitude' => $user->longitude,
                'address' => $user->address,
            ],
        ]);
    }

    public function getLocation(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'latitude' => $user->latitude,
                'longitude' => $user->longitude,
                'address' => $user->address,
                'has_location' => $user->latitude !== null && $user->longitude !== null,
            ],
        ]);
    }
}

