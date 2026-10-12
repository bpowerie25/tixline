<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index()
    {
        return Inertia::render('Teams/Index', [
            'teams' => Team::withCount('members', 'tickets')->with('members:id,name,email,team_id')->get(),
            'agents' => User::all(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'is_restricted' => 'boolean',
            'allow_admin_view' => 'boolean',
            'default_assignee_id' => 'nullable|exists:users,id',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $team = Team::create($validated);

        ActivityLogger::log('team_created', "Created team {$team->name}", $team);

        return back()->with('success', 'Team created.');
    }

    public function update(Request $request, Team $team)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'is_restricted' => 'boolean',
            'allow_admin_view' => 'boolean',
            'default_assignee_id' => 'nullable|exists:users,id',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $team->update($validated);

        ActivityLogger::log('team_updated', "Updated team {$team->name}", $team);

        return back()->with('success', 'Team updated.');
    }

    public function destroy(Team $team)
    {
        ActivityLogger::log('team_deleted', "Deleted team {$team->name}", $team);

        $team->delete();

        return back()->with('success', 'Team deleted.');
    }

    public function addMember(Request $request, Team $team)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $team->members()->syncWithoutDetaching([$validated['user_id']]);

        $agent = User::find($validated['user_id']);
        ActivityLogger::log('team_member_added', "Added {$agent->name} to team {$team->name}", $team);

        return back()->with('success', 'Agent added to team.');
    }

    public function removeMember(Team $team, User $user)
    {
        $team->members()->detach($user->id);

        ActivityLogger::log('team_member_removed', "Removed {$user->name} from team {$team->name}", $team);

        return back()->with('success', 'Agent removed from team.');
    }
}
