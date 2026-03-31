<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderResource;
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
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

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
}
