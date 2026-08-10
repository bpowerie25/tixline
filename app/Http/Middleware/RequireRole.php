<?php

namespace App\Http\Middleware;

use App\Models\Role;
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

        $userRoleName = $user->role?->name;

        // Check if user has any of the specified roles
        foreach ($roles as $role) {
            if ($userRoleName === $role) {
                return $next($request);
            }
        }

        // Admin always passes
        if ($userRoleName === Role::ADMIN) {
            return $next($request);
        }

        abort(403, 'Insufficient permissions.');
    }
}
