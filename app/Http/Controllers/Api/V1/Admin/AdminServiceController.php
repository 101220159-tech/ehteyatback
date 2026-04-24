<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Traits\HandlesImageUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminServiceController extends Controller
{
    use HandlesImageUpload;

    public function index(Request $request): JsonResponse
    {
        $items = Service::query()->with('category')->orderBy('name')->paginate($request->integer('per_page', 20));

        return ServiceResource::collection($items)->response();
    }

    public function byCategory(string $categoryId): JsonResponse
    {
        $items = Service::query()->where('category_id', $categoryId)->orderBy('name')->get();

        return ServiceResource::collection($items)->response();
    }

    public function store(ServiceRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('icon')) {
            $data['icon_url'] = $this->fileToDataUri($request->file('icon'));
        }
        unset($data['icon']);

        $service = Service::query()->create($data);

        return (new ServiceResource($service->load('category')))->response()->setStatusCode(201);
    }

    /**
     * POST /admin/services/{id}  (multipart/form-data so icon upload works)
     */
    public function update(ServiceRequest $request, string $id): ServiceResource
    {
        $service = Service::query()->findOrFail($id);
        $data    = $request->validated();

        if ($request->hasFile('icon')) {
            $data['icon_url'] = $this->fileToDataUri($request->file('icon'));
        }
        unset($data['icon']);

        $service->update($data);

        return new ServiceResource($service->fresh()->load('category'));
    }

    public function destroy(string $id): JsonResponse
    {
        Service::query()->where('id', $id)->delete();

        return response()->json(['message' => 'Service deleted.']);
    }
}
