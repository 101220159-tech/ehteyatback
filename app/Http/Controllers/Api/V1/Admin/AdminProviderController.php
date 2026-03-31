<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderResource;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Provider::query()
            ->with('user')
            ->withCount('reviews')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ProviderResource::collection($items)->response();
    }

    public function show(int $id): ProviderResource
    {
        return new ProviderResource(
            Provider::query()->with(['user', 'services.category'])->withCount('reviews')->findOrFail($id)
        );
    }
}
