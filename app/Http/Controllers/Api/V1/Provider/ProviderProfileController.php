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
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', new LebanonProviderPhone],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        if (array_key_exists('phone', $userData)) {
            $userData['phone'] = LebanonMobilePhone::normalize($userData['phone'] ?? '') ?? null;
        }

        $providerData = $request->validate([
            'bio' => ['nullable', 'string'],
            'experience_years' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($userData !== []) {
            $request->user()->update($userData);
        }
        if ($providerData !== []) {
            $provider->update($providerData);
        }

        return new ProviderResource($provider->fresh()->load(['user', 'services.category'])->loadCount('reviews'));
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:2048'],
        ]);

        $request->user()->update([
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'address' => $data['address'] ?? $request->user()->address,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Location updated',
            'data' => [
                'latitude' => $request->user()->latitude,
                'longitude' => $request->user()->longitude,
                'address' => $request->user()->address,
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
