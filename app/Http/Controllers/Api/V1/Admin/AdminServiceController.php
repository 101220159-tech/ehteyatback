<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Service::query()->with('category')->orderBy('name')->paginate($request->integer('per_page', 20));

        return ServiceResource::collection($items)->response();
    }

    public function store(ServiceRequest $request): JsonResponse
    {
        $service = Service::query()->create($request->validated());

        return (new ServiceResource($service->load('category')))->response()->setStatusCode(201);
    }

    public function update(ServiceRequest $request, int $id): ServiceResource
    {
        $service = Service::query()->findOrFail($id);
        $service->update($request->validated());

        return new ServiceResource($service->fresh()->load('category'));
    }

    public function destroy(int $id): JsonResponse
    {
        Service::query()->where('id', $id)->delete();

        return response()->json(['message' => 'Service deleted.']);
    }
}
