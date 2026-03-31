<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRequest;
use App\Http\Resources\AvailabilityResource;
use App\Models\ProviderAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderAvailabilityController extends Controller
{
    protected function provider(Request $request)
    {
        $p = $request->user()->provider;
        abort_if(! $p, 404, 'Provider profile not found.');

        return $p;
    }

    public function index(Request $request): JsonResponse
    {
        $provider = $this->provider($request);
        $items = ProviderAvailability::query()
            ->where('provider_id', $provider->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return AvailabilityResource::collection($items)->response();
    }

    public function store(AvailabilityRequest $request): JsonResponse
    {
        $provider = $this->provider($request);
        $row = ProviderAvailability::query()->create([
            ...$request->validated(),
            'provider_id' => $provider->id,
        ]);

        return (new AvailabilityResource($row))->response()->setStatusCode(201);
    }

    public function update(AvailabilityRequest $request, int $id): AvailabilityResource
    {
        $provider = $this->provider($request);
        $row = ProviderAvailability::query()->where('provider_id', $provider->id)->findOrFail($id);
        $row->update($request->validated());

        return new AvailabilityResource($row->fresh());
    }
}
