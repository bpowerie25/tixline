<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class AgentController extends Controller
{
    public function index()
    {
        $agents = User::with(['teams', 'role'])->withCount('assignedTickets')->get();

        $lastLogins = ActivityLog::where('action', 'login')
            ->whereIn('user_id', $agents->pluck('id'))
            ->selectRaw('user_id, MAX(created_at) as last_login_at')
            ->groupBy('user_id')
            ->pluck('last_login_at', 'user_id');

        $agents->each(function ($agent) use ($lastLogins) {
            $agent->last_login_at = $lastLogins[$agent->id] ?? null;
        });

        return Inertia::render('Agents/Index', [
            'agents' => $agents,
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

        ActivityLogger::log('agent_created', "Created agent {$agent->name} ({$agent->email})", $agent);

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

        ActivityLogger::log('agent_updated', "Updated agent {$agent->name} ({$agent->email})", $agent);

        return back()->with('success', 'Agent updated.');
    }

    public function sendInvite(User $agent)
    {
        $token = Password::broker()->createToken($agent);
        $resetUrl = url(route('password.reset', $token, false)) . '?email=' . urlencode($agent->email);

        Mail::raw(
            "Hi {$agent->name},\n\n"
            . "You've been added as an agent on " . config('app.name') . ".\n\n"
            . "Please set your password by clicking the link below:\n"
            . "{$resetUrl}\n\n"
            . "This link will expire in 60 minutes.\n\n"
            . "Regards,\n" . config('app.name'),
            function ($message) use ($agent) {
                $message->to($agent->email)
                    ->subject('You\'ve been invited to ' . config('app.name'));
            }
        );

        return back()->with('success', "Invite sent to {$agent->email}.");
    }

    public function destroy(User $agent)
    {
        if ($agent->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        ActivityLogger::log('agent_deleted', "Deleted agent {$agent->name} ({$agent->email})", $agent);

        $agent->delete();

        return back()->with('success', 'Agent deleted.');
    }
}
