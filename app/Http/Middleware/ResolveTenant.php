<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant) {
            app()->instance('tenant', $tenant);

            Inertia::share('tenant', [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'logo_url' => $tenant->logo_url,
                'favicon_url' => $tenant->favicon_url,
                'header_height' => $tenant->header_height ?? 'medium',
                'primary_color' => $tenant->primary_color,
                'secondary_color' => $tenant->secondary_color,
                'accent_color' => $tenant->accent_color,
                'header_bg_color' => $tenant->header_bg_color,
                'header_text_color' => $tenant->header_text_color,
                'sidebar_bg_color' => $tenant->sidebar_bg_color,
                'custom_css' => $tenant->custom_css,
                'font_family' => $tenant->font_family,
                'portal_title' => $tenant->portal_title,
                'portal_welcome_text' => $tenant->portal_welcome_text,
                'css_variables' => $tenant->cssVariables(),
            ]);
        } else {
            Inertia::share('tenant', null);
        }

        return $next($request);
    }

    protected function resolveTenant(Request $request): ?Tenant
    {
        $host = $request->getHost();

        // Try custom domain first
        $tenant = Tenant::where('domain', $host)
            ->where('is_active', true)
            ->first();

        if ($tenant) {
            return $tenant;
        }

        // Try subdomain
        $baseDomain = config('support.base_domain');
        if ($baseDomain && str_ends_with($host, ".{$baseDomain}")) {
            $slug = str_replace(".{$baseDomain}", '', $host);

            return Tenant::where('slug', $slug)
                ->where('is_active', true)
                ->first();
        }

        // Try from authenticated user's tenant
        if ($request->user() && $request->user()->tenant_id) {
            return Tenant::where('id', $request->user()->tenant_id)
                ->where('is_active', true)
                ->first();
        }

        return null;
    }
}
