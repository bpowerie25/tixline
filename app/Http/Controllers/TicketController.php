<?php

namespace App\Http\Controllers;

use App\Models\CannedResponse;
use App\Models\Label;
use App\Models\SpamFilterEntry;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\SpamLearner;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->visibleTicketsQuery()->with(['assignee', 'team', 'labels']);

        $status = $request->filled('status') ? $request->status : 'open';
        if ($status !== 'all') {
            $query->where('status', $status);
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
            'filters' => array_merge($request->only(['priority', 'team_id', 'assigned_to', 'search']), ['status' => $status]),
            'teams' => Team::all(),
            'agents' => User::all(['id', 'name']),
        ]);
    }

    public function show(Ticket $ticket)
    {
        $this->authorize('view', $ticket);

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

        if (! empty($labels)) {
            $ticket->labels()->sync($labels);
        }

        $engine->run($ticket->fresh(), 'ticket_created');

        ActivityLogger::log('ticket_created', "Created ticket {$ticket->reference}: {$ticket->subject}", $ticket);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket created.');
    }

    public function update(Request $request, Ticket $ticket, WorkflowEngine $engine)
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:open,pending,resolved,closed',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'team_id' => 'nullable|exists:teams,id',
            'assigned_to' => 'nullable|exists:users,id',
            'labels' => 'nullable|array',
            'labels.*' => 'exists:labels,id',
        ]);

        if (isset($validated['status']) && in_array($validated['status'], ['resolved', 'closed']) && ! $ticket->resolved_at) {
            $validated['resolved_at'] = now();
        }

        $labels = $validated['labels'] ?? null;
        unset($validated['labels']);

        if (array_key_exists('assigned_to', $validated) && $validated['assigned_to'] != $ticket->assigned_to) {
            $this->authorize('assign', $ticket);
        }

        // Track changes for field_changed events
        $oldValues = $ticket->only(['status', 'priority', 'team_id', 'assigned_to']);

        $ticket->update($validated);

        if ($labels !== null) {
            $ticket->labels()->sync($labels);
        }

        $fresh = $ticket->fresh();
        $engine->run($fresh, 'ticket_updated');

        $changes = array_diff_assoc($validated, $oldValues);
        if (! empty($changes)) {
            ActivityLogger::log('ticket_updated', "Updated ticket {$ticket->reference}", $ticket, ['changes' => $changes]);
        }

        // Fire specific field change events
        if (
            (isset($validated['assigned_to']) && $oldValues['assigned_to'] != $fresh->assigned_to) ||
            (isset($validated['team_id']) && $oldValues['team_id'] != $fresh->team_id)
        ) {
            $engine->run($fresh, 'ticket_assigned');
        }
        if (isset($validated['status']) && $oldValues['status'] != $fresh->status) {
            $engine->run($fresh, 'ticket_status_changed');
        }
        if (isset($validated['priority']) && $oldValues['priority'] != $fresh->priority) {
            $engine->run($fresh, 'ticket_priority_changed');
        }

        return back()->with('success', 'Ticket updated.');
    }

    public function destroy(Ticket $ticket)
    {
        $this->authorize('delete', $ticket);

        ActivityLogger::log('ticket_deleted', "Deleted ticket {$ticket->reference}: {$ticket->subject}", $ticket);

        $ticket->delete();

        return redirect()->route('tickets.index')
            ->with('success', 'Ticket deleted.');
    }

    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tickets,id',
            'action' => 'required|in:close,resolve,delete,spam,assign',
            'assigned_to' => 'nullable|exists:users,id',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        if ($validated['action'] === 'assign' && empty($validated['assigned_to']) && empty($validated['team_id'])) {
            return back()->withErrors(['assign' => 'Please select an agent or team to assign to.']);
        }

        $tickets = Ticket::whereIn('id', $validated['ids'])->get();
        $count = $tickets->count();

        switch ($validated['action']) {
            case 'assign':
                $update = [];
                $parts = [];

                if (! empty($validated['assigned_to'])) {
                    $agent = User::findOrFail($validated['assigned_to']);
                    $update['assigned_to'] = $agent->id;
                    $parts[] = $agent->name;
                }

                if (! empty($validated['team_id'])) {
                    $team = Team::findOrFail($validated['team_id']);
                    $update['team_id'] = $team->id;
                    $parts[] = $team->name;
                }

                Ticket::whereIn('id', $validated['ids'])->update($update);

                $label = implode(' / ', $parts);
                ActivityLogger::log('tickets_bulk_assigned', "Bulk assigned {$count} tickets to {$label}", null, ['ids' => $validated['ids']] + $update);

                return back()->with('success', "{$count} tickets assigned to {$label}.");

            case 'close':
                Ticket::whereIn('id', $validated['ids'])->update([
                    'status' => 'closed',
                    'resolved_at' => now(),
                ]);

                ActivityLogger::log('tickets_bulk_closed', "Bulk closed {$count} tickets", null, ['ids' => $validated['ids']]);

                return back()->with('success', "{$count} tickets closed.");

            case 'resolve':
                Ticket::whereIn('id', $validated['ids'])->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                ]);

                ActivityLogger::log('tickets_bulk_resolved', "Bulk resolved {$count} tickets", null, ['ids' => $validated['ids']]);

                return back()->with('success', "{$count} tickets resolved.");

            case 'delete':
                ActivityLogger::log('tickets_bulk_deleted', "Bulk deleted {$count} tickets", null, ['ids' => $validated['ids']]);

                Ticket::whereIn('id', $validated['ids'])->delete();

                return back()->with('success', "{$count} tickets deleted.");

            case 'spam':
                $learner = app(SpamLearner::class);

                // Blocklist individual sender emails and learn from content
                $emails = $tickets->pluck('requester_email')
                    ->map(fn ($email) => strtolower($email))
                    ->unique();

                foreach ($emails as $email) {
                    SpamFilterEntry::firstOrCreate(
                        ['type' => 'blocklist', 'value' => $email],
                        ['reason' => 'Auto-blocked: marked as spam by agent']
                    );
                }

                // Learn spam patterns from ticket content
                foreach ($tickets as $ticket) {
                    $learner->learnFromTicket($ticket);
                }

                ActivityLogger::log('tickets_bulk_spam', "Marked {$count} tickets as spam, blocklisted {$emails->count()} sender(s)", null, ['ids' => $validated['ids']]);

                Ticket::whereIn('id', $validated['ids'])->delete();

                return back()->with('success', "{$count} tickets deleted, " . $emails->count() . " sender(s) blocklisted, and spam patterns learned.");
        }

        return back();
    }
}
