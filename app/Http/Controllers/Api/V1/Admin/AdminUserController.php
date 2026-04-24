<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with(['role.permissions', 'directPermissions'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'confirmed', Password::defaults()],
            'password_confirmation' => ['required', 'string'],
            'role_id'               => ['required', 'uuid', 'exists:roles,id'],
            'phone'                 => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::query()->create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => $data['password'],
            'email_verified_at' => now(),
            'role_id'           => $data['role_id'],
            'phone'             => $data['phone'] ?? null,
        ]);

        return (new UserResource($user->loadForApiSerialization()))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): UserResource
    {
        return new UserResource(
            User::query()->with(['role.permissions', 'directPermissions', 'provider'])->findOrFail($id)
        );
    }

    public function update(Request $request, string $id): UserResource
    {
        $user = User::query()->findOrFail($id);

        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'email'    => ['sometimes', 'email', 'unique:users,email,'.$id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role_id'  => ['sometimes', 'uuid', 'exists:roles,id'],
            'phone'    => ['nullable', 'string', 'max:50'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return new UserResource($user->fresh()->loadForApiSerialization());
    }

    public function destroy(string $id): JsonResponse
    {
        User::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'User deleted.']);
    }

    public function assignRole(Request $request, string $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        $data = $request->validate([
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
        ]);

        $user->update(['role_id' => $data['role_id']]);

        return response()->json([
            'message' => 'Role assigned.',
            'user'    => new UserResource($user->fresh()->loadForApiSerialization()),
        ]);
    }

    public function grantPermission(Request $request, string $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        $data = $request->validate([
            'permission_ids'   => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['uuid', 'exists:permissions,id'],
        ]);

        $permissions = Permission::query()->whereIn('id', $data['permission_ids'])->get();
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return response()->json([
            'message' => 'Permission(s) granted.',
            'user'    => new UserResource($user->fresh()->loadForApiSerialization()),
        ]);
    }
}
