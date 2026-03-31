<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.', 'errors' => []], 401);
        }

        $names = $this->normalize($permissions);

        if (! $user->hasAnyPermission($names)) {
            return response()->json(['message' => 'Forbidden.', 'errors' => ['permission' => ['Missing permission.']]], 403);
        }

        return $next($request);
    }

    /** @param  array<int, string>  $permissions */
    private function normalize(array $permissions): array
    {
        $flat = [];
        foreach ($permissions as $p) {
            foreach (array_map('trim', explode(',', $p)) as $part) {
                if ($part !== '') {
                    $flat[] = $part;
                }
            }
        }

        return array_values(array_unique($flat));
    }
}
