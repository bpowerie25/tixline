<?php

namespace App\Http\Controllers;

use App\Models\SlaPolicy;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SlaPolicyController extends Controller
{
    public function index()
    {
        return Inertia::render('SLA/Index', [
            'policies' => SlaPolicy::orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 END")->get(),
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
            'is_active' => 'boolean',
        ]);

        $policy = SlaPolicy::create($validated);

        ActivityLogger::log('sla_policy_created', "Created SLA policy {$policy->name}", $policy);

        return back()->with('success', 'SLA policy created.');
    }

    public function update(Request $request, SlaPolicy $slaPolicy)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'first_response_hours' => 'required|integer|min:1',
            'resolution_hours' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $slaPolicy->update($validated);

        ActivityLogger::log('sla_policy_updated', "Updated SLA policy {$slaPolicy->name}", $slaPolicy);

        return back()->with('success', 'SLA policy updated.');
    }

    public function destroy(SlaPolicy $slaPolicy)
    {
        ActivityLogger::log('sla_policy_deleted', "Deleted SLA policy {$slaPolicy->name}", $slaPolicy);

        $slaPolicy->delete();

        return back()->with('success', 'SLA policy deleted.');
    }
}
