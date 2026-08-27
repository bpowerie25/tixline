<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the tenant for token-authenticated API requests.
 *
 * ResolveTenant only runs on the `web` middleware group, so nothing bound a
 * tenant on API routes. TenantScope no-ops when no tenant is bound, which
 * meant an API token issued by one tenant read and wrote every tenant's
 * tickets. This binds the tenant that owns the authenticated user, so the
 * global scope applies as it does everywhere else.
 *
 * It is prepended to the `api` group rather than added per route because
 * SubstituteBindings lives in that group: route model binding resolves
 * {ticket} before any route middleware runs, so binding the tenant later
 * still let `GET /api/v1/tickets/{id}` load another tenant's ticket. Running
 * ahead of the group means the token has to be resolved here rather than
 * relying on auth:sanctum having set the default guard.
 */
class ResolveApiTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            return $next($request);
        }

        $tenant = null;

        if ($user->tenant_id) {
            $tenant = Tenant::where('id', $user->tenant_id)
                ->where('is_active', true)
                ->first();

            if (! $tenant) {
                abort(403, 'This tenant is not active.');
            }
        }

        // A key presented on another tenant's hostname is rejected rather than
        // quietly served against its own tenant's data. Single-tenant installs
        // resolve no host tenant and fall through.
        $hostTenant = $this->tenantForHost($request);

        if ($hostTenant && $tenant?->id !== $hostTenant->id) {
            abort(403, 'This API key does not belong to the requested tenant.');
        }

        if ($tenant) {
            app()->instance('tenant', $tenant);
        }

        return $next($request);
    }

    protected function tenantForHost(Request $request): ?Tenant
    {
        $host = $request->getHost();

        $tenant = Tenant::where('domain', $host)
            ->where('is_active', true)
            ->first();

        if ($tenant) {
            return $tenant;
        }

        // The core and the cloud app name the same setting differently, and
        // this class is shared by both.
        $baseDomain = config('support.base_domain') ?: config('cloud.base_domain');

        if ($baseDomain && str_ends_with($host, ".{$baseDomain}")) {
            return Tenant::where('slug', str_replace(".{$baseDomain}", '', $host))
                ->where('is_active', true)
                ->first();
        }

        return null;
    }
}
