<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        // Check if user has any of the specified roles OR is at least the minimum role
        foreach ($roles as $role) {
            if ($user->role === $role) {
                return $next($request);
            }
        }

        // Admin always passes
        if ($user->isAdmin()) {
            return $next($request);
        }

        abort(403, 'Insufficient permissions.');
    }
}
