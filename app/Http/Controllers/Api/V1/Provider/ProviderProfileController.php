<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderResource;
use App\Rules\LebanonProviderPhone;
use App\Support\LebanonMobilePhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderProfileController extends Controller
{
    public function show(Request $request): ProviderResource
    {
        $provider = $request->user()->provider;
        abort_if(! $provider, 404, 'Provider profile not found.');

        return new ProviderResource($provider->load(['user', 'services.category'])->loadCount('reviews'));
    }

    public function update(Request $request): ProviderResource
    {
        $provider = $request->user()->provider;
        abort_if(! $provider, 404, 'Provider profile not found.');

        $userData = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', new LebanonProviderPhone],
        ]);

        if (array_key_exists('phone', $userData)) {
            $userData['phone'] = LebanonMobilePhone::normalize($userData['phone'] ?? '') ?? null;
        }

        $providerData = $request->validate([
            'bio'              => ['nullable', 'string'],
            'experience_years' => ['sometimes', 'integer', 'min:0'],
            'latitude'         => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude'        => ['sometimes', 'numeric', 'between:-180,180'],
        ]);

        if ($userData !== []) {
            $request->user()->update($userData);
        }
        if ($providerData !== []) {
            $provider->update($providerData);
        }

        return new ProviderResource($provider->fresh()->load(['user', 'services.category'])->loadCount('reviews'));
    }

    public function storeAvatar(Request $request): JsonResponse
    {
        $provider = $request->user()->provider;
        abort_if(! $provider, 404, 'Provider profile not found.');

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $file    = $request->file('avatar');
        $mime    = $file->getMimeType();
        $dataUri = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file->getRealPath()));

        // Save to both providers and users tables
        $provider->update(['avatar_url' => $dataUri]);
        $request->user()->update(['avatar_url' => $dataUri]);

        return response()->json([
            'success'    => true,
            'message'    => 'Avatar updated.',
            'avatar_url' => $dataUri,
        ]);
    }

    public function publicProfile(Request $request): ProviderResource
    {
        return $this->show($request);
    }

    public function updateLocation(Request $request): JsonResponse
    {
        $provider = $request->user()->provider;
        abort_if(! $provider, 404, 'Provider profile not found.');

        $data = $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address'   => ['nullable', 'string', 'max:2048'],
        ]);

        // Update provider table
        $provider->update([
            'latitude'  => $data['latitude'],
            'longitude' => $data['longitude'],
        ]);

        // Sync coordinates + address to the user table.
        // Use $request->input() to catch any capitalisation the frontend sends (address / Address).
        $address = $data['address']
            ?? $request->input('Address')
            ?? $request->input('address');

        $userUpdate = [
            'latitude'  => $data['latitude'],
            'longitude' => $data['longitude'],
        ];
        if ($address !== null) {
            $userUpdate['address'] = $address;
        }
        $request->user()->update($userUpdate);

        return response()->json([
            'success' => true,
            'message' => 'Location updated',
            'data'    => [
                'latitude'     => $provider->latitude,
                'longitude'    => $provider->longitude,
                'address'      => $request->user()->fresh()->address,
                'has_location' => $provider->latitude !== null,
            ],
        ]);
    }

    public function getLocation(Request $request): JsonResponse
    {
        $provider = $request->user()->provider;
        abort_if(! $provider, 404, 'Provider profile not found.');

        return response()->json([
            'success' => true,
            'data' => [
                'latitude'     => $provider->latitude,
                'longitude'    => $provider->longitude,
                'has_location' => $provider->latitude !== null,
            ],
        ]);
    }
}
