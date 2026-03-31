<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.', 'errors' => []], 401);
        }

        $names = $this->normalize($roles);

        if (! $user->hasRole($names)) {
            return response()->json(['message' => 'Forbidden.', 'errors' => ['role' => ['Insufficient role.']]], 403);
        }

        return $next($request);
    }

    /** @param  array<int, string>  $roles */
    private function normalize(array $roles): array
    {
        $flat = [];
        foreach ($roles as $r) {
            foreach (array_map('trim', explode(',', $r)) as $part) {
                if ($part !== '') {
                    $flat[] = $part;
                }
            }
        }

        return array_values(array_unique($flat));
    }
}
