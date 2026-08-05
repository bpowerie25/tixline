<?php

namespace App\Http\Controllers;

use App\Models\Label;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;
use App\Rules\ValidWorkflowRegex;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkflowController extends Controller
{
    public function index()
    {
        return Inertia::render('Workflows/Index', [
            'workflows' => Workflow::orderBy('priority', 'desc')->get(),
            'teams' => Team::all(),
            'agents' => User::all(['id', 'name']),
            'labels' => Label::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'required|string',
            'events' => 'nullable|array',
            'events.*.entity' => 'required|string',
            'events.*.action' => 'required|string',
            'conditions' => ['required', 'array', new ValidWorkflowRegex],
            'actions' => 'required|array',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ]);

        Workflow::create($validated);

        return back()->with('success', 'Workflow created.');
    }

    public function update(Request $request, Workflow $workflow)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'required|string',
            'events' => 'nullable|array',
            'events.*.entity' => 'required|string',
            'events.*.action' => 'required|string',
            'conditions' => ['required', 'array', new ValidWorkflowRegex],
            'actions' => 'required|array',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ]);

        $workflow->update($validated);

        return back()->with('success', 'Workflow updated.');
    }

    public function destroy(Workflow $workflow)
    {
        $workflow->delete();

        return back()->with('success', 'Workflow deleted.');
    }
}
