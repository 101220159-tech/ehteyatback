<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRoleOrPermission
{
    public function handle(Request $request, Closure $next, string $roles, string $permissions): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.', 'errors' => []], 401);
        }

        $roleList = array_values(array_filter(array_map('trim', explode(',', $roles))));
        $permList = array_values(array_filter(array_map('trim', explode(',', $permissions))));

        $okRoles = $roleList !== [] && $user->hasRole($roleList);
        $okPerm = $permList !== [] && $user->hasAnyPermission($permList);

        if (! $okRoles && ! $okPerm) {
            return response()->json(['message' => 'Forbidden.', 'errors' => ['access' => ['Missing role or permission.']]], 403);
        }

        return $next($request);
    }
}
