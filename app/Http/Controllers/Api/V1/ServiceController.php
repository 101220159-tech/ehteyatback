<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = ServiceCategory::query()
            ->orderBy('name')
            ->with(['services' => fn ($q) => $q->orderBy('name')->with('category')])
            ->get();

        return CategoryResource::collection($categories);
    }

    public function show(int $id): ServiceResource
    {
        $service = Service::query()
            ->with('category')
            ->findOrFail($id);

        return new ServiceResource($service);
    }
}
