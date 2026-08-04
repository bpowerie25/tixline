<?php

namespace App\Http\Controllers;

use App\Models\CannedResponse;
use App\Models\Label;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::with(['assignee', 'team', 'labels']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('requester_email', 'like', "%{$search}%");
            });
        }

        $tickets = $query->latest()->paginate(25)->withQueryString();

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'filters' => $request->only(['status', 'priority', 'team_id', 'assigned_to', 'search']),
            'teams' => Team::all(),
            'agents' => User::all(['id', 'name']),
        ]);
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['assignee', 'team', 'labels', 'form.fields', 'attachments', 'comments' => function ($q) {
            $q->with(['user', 'attachments'])->oldest();
        }]);

        $cannedResponses = CannedResponse::where(function ($q) {
            $q->where('is_shared', true)
              ->orWhere('user_id', auth()->id());
        })->orderBy('name')->get(['id', 'name', 'shortcode', 'body']);

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'teams' => Team::all(),
            'agents' => User::all(['id', 'name']),
            'labels' => Label::all(),
            'cannedResponses' => $cannedResponses,
        ]);
    }

    public function create()
    {
        return Inertia::render('Tickets/Create', [
            'teams' => Team::all(),
            'agents' => User::all(['id', 'name']),
            'labels' => Label::all(),
        ]);
    }

    public function store(Request $request, WorkflowEngine $engine)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'nullable|string',
            'requester_name' => 'required|string|max:255',
            'requester_email' => 'required|email|max:255',
            'priority' => 'in:low,normal,high,urgent',
            'team_id' => 'nullable|exists:teams,id',
            'assigned_to' => 'nullable|exists:users,id',
            'labels' => 'nullable|array',
            'labels.*' => 'exists:labels,id',
            'custom_fields' => 'nullable|array',
        ]);

        $labels = $validated['labels'] ?? [];
        unset($validated['labels']);

        $validated['source'] = 'web';
        $ticket = Ticket::create($validated);

        if (!empty($labels)) {
            $ticket->labels()->sync($labels);
        }

        $engine->run($ticket->fresh(), 'ticket_created');

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket created.');
    }

    public function update(Request $request, Ticket $ticket, WorkflowEngine $engine)
    {
        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:open,pending,resolved,closed',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'team_id' => 'nullable|exists:teams,id',
            'assigned_to' => 'nullable|exists:users,id',
            'labels' => 'nullable|array',
            'labels.*' => 'exists:labels,id',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'resolved' && !$ticket->resolved_at) {
            $validated['resolved_at'] = now();
        }

        $labels = $validated['labels'] ?? null;
        unset($validated['labels']);

        $ticket->update($validated);

        if ($labels !== null) {
            $ticket->labels()->sync($labels);
        }

        $engine->run($ticket->fresh(), 'ticket_updated');

        return back()->with('success', 'Ticket updated.');
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();

        return redirect()->route('tickets.index')
            ->with('success', 'Ticket deleted.');
    }
}
