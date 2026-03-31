<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Permission::query()->orderBy('name')->paginate($request->integer('per_page', 50));

        return PermissionResource::collection($items)->response();
    }

    public function store(PermissionRequest $request): JsonResponse
    {
        $perm = Permission::query()->create($request->validated());

        return (new PermissionResource($perm))->response()->setStatusCode(201);
    }
}
