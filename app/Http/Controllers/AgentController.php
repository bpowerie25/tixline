<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentController extends Controller
{
    public function index()
    {
        return Inertia::render('Agents/Index', [
            'agents' => User::with(['teams', 'role'])->withCount('assignedTickets')->get(),
            'teams' => Team::all(['id', 'name']),
            'roles' => Role::all(['id', 'name', 'display_name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'exists:teams,id',
        ]);

        $teamIds = $validated['team_ids'] ?? [];
        unset($validated['team_ids']);

        $agent = User::create($validated);
        $agent->teams()->sync($teamIds);

        return back()->with('success', 'Agent created.');
    }

    public function update(Request $request, User $agent)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $agent->id,
            'password' => 'nullable|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'exists:teams,id',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $teamIds = $validated['team_ids'] ?? [];
        unset($validated['team_ids']);

        $agent->update($validated);
        $agent->teams()->sync($teamIds);

        return back()->with('success', 'Agent updated.');
    }

    public function destroy(User $agent)
    {
        if ($agent->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $agent->delete();

        return back()->with('success', 'Agent deleted.');
    }
}
