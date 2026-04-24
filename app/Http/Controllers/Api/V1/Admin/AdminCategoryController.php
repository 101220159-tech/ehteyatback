<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\ServiceCategory;
use App\Traits\HandlesImageUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
    use HandlesImageUpload;

    public function index(): JsonResponse
    {
        $items = ServiceCategory::query()->orderBy('name')->get();

        return CategoryResource::collection($items)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
        ]);

        if ($request->hasFile('icon')) {
            $data['icon_url'] = $this->fileToDataUri($request->file('icon'));
        }
        unset($data['icon']);

        $cat = ServiceCategory::query()->create($data);

        return (new CategoryResource($cat))->response()->setStatusCode(201);
    }

    /**
     * POST /admin/categories/{id}  (multipart/form-data so icon upload works)
     */
    public function update(Request $request, string $id): CategoryResource
    {
        $cat = ServiceCategory::query()->findOrFail($id);

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'icon'        => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
        ]);

        if ($request->hasFile('icon')) {
            $data['icon_url'] = $this->fileToDataUri($request->file('icon'));
        }
        unset($data['icon']);

        $cat->update($data);

        return new CategoryResource($cat->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        ServiceCategory::query()->where('id', $id)->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
