<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $items = ServiceCategory::query()->orderBy('name')->get();

        return CategoryResource::collection($items)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $cat = ServiceCategory::query()->create($data);

        return (new CategoryResource($cat))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id): CategoryResource
    {
        $cat = ServiceCategory::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
        $cat->update($data);

        return new CategoryResource($cat->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        ServiceCategory::query()->where('id', $id)->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
