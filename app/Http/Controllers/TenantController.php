<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255|unique:tenants',
            'logo_url' => 'nullable|string|max:500',
            'favicon_url' => 'nullable|string|max:500',
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
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        Tenant::create($validated);

        return redirect()->route('tenants.index')
            ->with('success', 'Tenant created.');
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255|unique:tenants,domain,' . $tenant->id,
            'logo_url' => 'nullable|string|max:500',
            'favicon_url' => 'nullable|string|max:500',
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
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $tenant->update($validated);

        return redirect()->route('tenants.index')
            ->with('success', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();

        return back()->with('success', 'Tenant deleted.');
    }
}
