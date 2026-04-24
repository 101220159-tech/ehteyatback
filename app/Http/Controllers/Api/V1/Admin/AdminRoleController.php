<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Role::query()->with('permissions')->orderBy('name')->paginate($request->integer('per_page', 30));

        return RoleResource::collection($items)->response();
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $permIds = $data['permission_ids'] ?? [];
        unset($data['permission_ids']);

        $role = Role::query()->create($data);
        if ($permIds !== []) {
            $role->permissions()->sync($permIds);
        }

        return (new RoleResource($role->load('permissions')))->response()->setStatusCode(201);
    }

    public function update(RoleRequest $request, string $id): RoleResource
    {
        $role = Role::query()->findOrFail($id);
        $data = $request->validated();
        unset($data['permission_ids']);

        $role->update($data);
        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->input('permission_ids', []));
        }

        return new RoleResource($role->fresh()->load('permissions'));
    }

    public function destroy(string $id): JsonResponse
    {
        Role::query()->where('id', $id)->delete();

        return response()->json(['message' => 'Role deleted.']);
    }
}
