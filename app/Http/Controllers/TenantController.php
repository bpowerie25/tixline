<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function index()
    {
        return Inertia::render('Tenants/Index', [
            'tenants' => $this->visibleTenants()->withCount('users', 'tickets')->get(),
        ]);
    }

    public function create()
    {
        $this->assertMayManageTenants();

        return Inertia::render('Tenants/Edit', [
            'tenant' => null,
        ]);
    }

    /**
     * Whether this install lets one admin administer more than one tenant.
     *
     * Self-hosted, yes: the tenants screen is the skinning feature, and the
     * only admin is the person who owns the server. Hosted, emphatically not
     * -- every one of these routes is reachable by any customer's own admin,
     * because tenants.manage is the permission the hosted app also uses for
     * self-service account deletion.
     */
    protected function isOperator(): bool
    {
        return ! config('support.multi_tenant');
    }

    /**
     * A tenant record has no tenant_id -- it is the tenant -- so nothing
     * scopes these bindings. Without this check a customer's admin could
     * read, rename and delete any other customer's account by changing the
     * id in the URL, and did: verified before this was added.
     */
    protected function assertOwnTenant(Tenant $tenant): void
    {
        if ($this->isOperator()) {
            return;
        }

        $current = app()->bound('tenant') ? app('tenant') : null;

        abort_if($current === null || $tenant->id !== $current->id, 404);
    }

    protected function assertMayManageTenants(): void
    {
        // Hosted tenants come from registration, which provisions a plan, an
        // admin and an inbound address with them. One made here would have
        // none of that.
        abort_unless($this->isOperator(), 403, 'Tenants are created by registration.');
    }

    protected function visibleTenants()
    {
        if ($this->isOperator()) {
            return Tenant::query();
        }

        $current = app()->bound('tenant') ? app('tenant') : null;

        return Tenant::whereKey($current?->id ?? 0);
    }

    public function show(Tenant $tenant)
    {
        $this->assertOwnTenant($tenant);

        return Inertia::render('Tenants/Edit', [
            'tenant' => $tenant,
        ]);
    }

    public function store(Request $request)
    {
        $this->assertMayManageTenants();

        $validated = $request->validate($this->validationRules());

        $validated['slug'] = Str::slug($validated['name']);
        $validated = $this->handleFileUploads($request, $validated);

        $tenant = Tenant::create($validated);

        ActivityLogger::log('tenant_created', "Created tenant {$tenant->name}", $tenant);

        return redirect()->route('tenants.index')
            ->with('success', 'Tenant created.');
    }

    public function update(Request $request, Tenant $tenant)
    {
        $this->assertOwnTenant($tenant);

        $validated = $request->validate($this->validationRules($tenant->id));

        $validated['slug'] = Str::slug($validated['name']);
        $validated = $this->handleFileUploads($request, $validated, $tenant);

        $tenant->update($validated);

        ActivityLogger::log('tenant_updated', "Updated tenant {$tenant->name}", $tenant);

        return redirect()->route('tenants.index')
            ->with('success', 'Tenant updated.');
    }

    protected function validationRules(?int $tenantId = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255|unique:tenants'.($tenantId ? ",domain,{$tenantId}" : ''),
            'logo_url' => 'nullable|string|max:500',
            'favicon_url' => 'nullable|string|max:500',
            'header_height' => 'nullable|string|in:small,medium,large,xlarge',
            'logo_file' => 'nullable|image|max:2048',
            'favicon_file' => 'nullable|image|max:512',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'accent_color' => 'nullable|string|max:7',
            'header_bg_color' => 'nullable|string|max:7',
            'header_text_color' => 'nullable|string|max:7',
            'sidebar_bg_color' => 'nullable|string|max:7',
            'custom_css' => 'nullable|string',
            'font_family' => 'nullable|string|max:255',
            'portal_title' => 'nullable|string|max:255',
            'portal_welcome_text' => 'nullable|string',
            'support_email' => 'nullable|email|max:255',
            'reply_email_mode' => 'nullable|string|in:notification,full',
            'announcement_enabled' => 'boolean',
            'announcement_text' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];
    }

    protected function handleFileUploads(Request $request, array $validated, ?Tenant $tenant = null): array
    {
        if ($request->hasFile('logo_file')) {
            if ($tenant?->logo_url && str_starts_with($tenant->logo_url, '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $tenant->logo_url));
            }
            $path = $request->file('logo_file')->store('tenants/logos', 'public');
            $validated['logo_url'] = '/storage/'.$path;
        }

        if ($request->hasFile('favicon_file')) {
            if ($tenant?->favicon_url && str_starts_with($tenant->favicon_url, '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $tenant->favicon_url));
            }
            $path = $request->file('favicon_file')->store('tenants/favicons', 'public');
            $validated['favicon_url'] = '/storage/'.$path;
        }

        unset($validated['logo_file'], $validated['favicon_file']);

        return $validated;
    }

    public function destroy(Tenant $tenant)
    {
        $this->assertOwnTenant($tenant);

        $tenant->delete();

        return back()->with('success', 'Tenant deleted.');
    }
}
