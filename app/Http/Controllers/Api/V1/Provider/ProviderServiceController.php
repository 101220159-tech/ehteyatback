<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderServiceRequest;
use App\Http\Resources\ProviderServiceResource;
use App\Models\ProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderServiceController extends Controller
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
        $items = ProviderService::query()
            ->where('provider_id', $provider->id)
            ->with(['service.category'])
            ->orderBy('id')
            ->paginate($request->integer('per_page', 20));

        return ProviderServiceResource::collection($items)->response();
    }

    public function store(ProviderServiceRequest $request): JsonResponse
    {
        $provider = $this->provider($request);
        $data = $request->validated();

        $row = ProviderService::query()->firstOrCreate(
            [
                'provider_id' => $provider->id,
                'service_id' => $data['service_id'],
            ],
            ['price' => $data['price']]
        );

        if (! $row->wasRecentlyCreated) {
            $row->update(['price' => $data['price']]);
        }

        return (new ProviderServiceResource($row->load(['service.category'])))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $id): ProviderServiceResource
    {
        $provider = $this->provider($request);
        $row = ProviderService::query()->where('provider_id', $provider->id)->findOrFail($id);
        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
        ]);
        $row->update($data);

        return new ProviderServiceResource($row->fresh()->load(['service.category']));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $provider = $this->provider($request);
        ProviderService::query()->where('provider_id', $provider->id)->where('id', $id)->delete();

        return response()->json(['message' => 'Offering removed.']);
    }
}
