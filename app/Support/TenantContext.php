<?php

namespace App\Support;

use App\Models\Tenant;
use Closure;

/**
 * Binds the tenant that scoped queries resolve against.
 *
 * TenantScope and BelongsToTenant both read the "tenant" container binding.
 * HTTP requests get it from the ResolveTenant middleware, but console commands,
 * queued jobs and the scheduler have no request to resolve from -- and when
 * nothing is bound the global scope silently applies no constraint at all, so
 * queries run across every tenant and new records are written without a
 * tenant_id. Anything that touches tenant data outside a request must therefore
 * establish the context explicitly.
 */
class TenantContext
{
    /**
     * Run a callback with the given tenant bound, restoring whatever was bound
     * before -- including nothing -- once it finishes or throws.
     */
    public static function run(?Tenant $tenant, Closure $callback): mixed
    {
        $app = app();
        $wasBound = $app->bound('tenant');
        $previous = $wasBound ? $app->make('tenant') : null;

        if ($tenant) {
            $app->instance('tenant', $tenant);
        } elseif ($wasBound) {
            $app->forgetInstance('tenant');
        }

        try {
            return $callback();
        } finally {
            if ($wasBound) {
                $app->instance('tenant', $previous);
            } else {
                $app->forgetInstance('tenant');
            }
        }
    }

    /**
     * The currently bound tenant, if any.
     */
    public static function current(): ?Tenant
    {
        return app()->bound('tenant') ? app('tenant') : null;
    }

    /**
     * Whether tenant data is currently scoped to a single tenant.
     */
    public static function isBound(): bool
    {
        return static::current() !== null;
    }
}
