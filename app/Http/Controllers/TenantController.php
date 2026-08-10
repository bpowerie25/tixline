<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function index()
    {
        return Inertia::render('Tenants/Index', [
            'tenants' => Tenant::withCount('users', 'tickets')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Tenants/Edit', [
            'tenant' => null,
        ]);
    }

    public function show(Tenant $tenant)
    {
        return Inertia::render('Tenants/Edit', [
            'tenant' => $tenant,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->validationRules());

        $validated['slug'] = Str::slug($validated['name']);
        $validated = $this->handleFileUploads($request, $validated);

        Tenant::create($validated);

        return redirect()->route('tenants.index')
            ->with('success', 'Tenant created.');
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate($this->validationRules($tenant->id));

        $validated['slug'] = Str::slug($validated['name']);
        $validated = $this->handleFileUploads($request, $validated, $tenant);

        $tenant->update($validated);

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
        $tenant->delete();

        return back()->with('success', 'Tenant deleted.');
    }
}
