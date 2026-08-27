<?php

namespace App\Http\Controllers;

use App\Models\BusinessHours;
use App\Models\SlaPolicy;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SlaPolicyController extends Controller
{
    public function index()
    {
        return Inertia::render('SLA/Index', [
            'policies' => SlaPolicy::orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 END")->get(),
            'hasBusinessHours' => BusinessHours::first() !== null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,normal,high,urgent|unique:sla_policies,priority',
            'first_response_hours' => 'required|integer|min:1',
            'resolution_hours' => 'required|integer|min:1',
            'use_business_hours' => 'boolean',
            'is_active' => 'boolean',
        ]);

        SlaPolicy::create($validated);

        return back()->with('success', 'SLA policy created.');
    }

    public function update(Request $request, SlaPolicy $slaPolicy)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'first_response_hours' => 'required|integer|min:1',
            'resolution_hours' => 'required|integer|min:1',
            'use_business_hours' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $slaPolicy->update($validated);

        return back()->with('success', 'SLA policy updated.');
    }

    public function destroy(SlaPolicy $slaPolicy)
    {
        $slaPolicy->delete();

        return back()->with('success', 'SLA policy deleted.');
    }
}
