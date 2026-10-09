<?php

namespace App\Http\Controllers;

use App\Mail\CustomerPasswordReset;
use App\Models\ActivityLog;
use App\Models\CannedResponse;
use App\Models\Customer;
use App\Models\Label;
use App\Models\SpamFilterEntry;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\SpamLearner;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->visibleTicketsQuery()->with(['assignee', 'team', 'labels', 'tags']);

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

        if ($request->filled('ai_flagged')) {
            $query->where('ai_flagged', true);
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
            'filters' => array_merge($request->only(['priority', 'team_id', 'assigned_to', 'search', 'ai_flagged']), ['status' => $status]),
            'teams' => Team::all(),
            'agents' => User::all(['id', 'name']),
        ]);
    }

    public function show(Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $ticket->load(['assignee', 'team', 'labels', 'tags', 'form.fields', 'attachments', 'duplicateOf:id,reference,subject', 'comments' => function ($q) {
            $q->with(['user', 'attachments'])->oldest();
        }]);

        $cannedResponses = CannedResponse::where(function ($q) {
            $q->where('is_shared', true)
                ->orWhere('user_id', auth()->id());
        })->orderBy('name')->get(['id', 'name', 'shortcode', 'body']);

        $hasCustomerAccount = Customer::withoutGlobalScopes()
            ->where('email', $ticket->requester_email)
            ->exists();

        $requesterTickets = auth()->user()->visibleTicketsQuery()
            ->where('requester_email', $ticket->requester_email)
            ->where('id', '!=', $ticket->id)
            ->latest()
            ->limit(10)
            ->get(['id', 'reference', 'subject', 'status', 'created_at']);

        $duplicates = $ticket->duplicates()->get(['id', 'reference', 'subject', 'status']);

        // Build unified timeline from activity_logs + comments
        $logEntries = ActivityLog::with('user:id,name')
            ->where('subject_type', 'App\\Models\\Ticket')
            ->where('subject_id', $ticket->id)
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id' => 'log_' . $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'created_at' => $log->created_at,
            ]);

        // Derive activity entries from comments (covers history before logging was added)
        $commentEntries = $ticket->comments->map(function ($comment) use ($ticket) {
            if ($comment->type === 'system') {
                $action = 'ticket_system';
                $description = 'System update';
            } elseif ($comment->is_internal) {
                $name = $comment->user?->name ?? 'System';
                $action = 'ticket_note_added';
                $description = "{$name} added an internal note";
            } else {
                // No user_id means requester replied (e.g. via email)
                $name = $comment->user?->name ?? $ticket->requester_name;
                $action = $comment->user_id ? 'ticket_replied' : 'requester_replied';
                $description = "{$name} replied";
            }
            return [
                'id' => 'comment_' . $comment->id,
                'action' => $action,
                'description' => $description,
                'created_at' => $comment->created_at,
            ];
        });

        // Always include a "Ticket created" entry from the ticket itself
        $createdEntry = collect([[
            'id' => 'created',
            'action' => 'ticket_created',
            'description' => "Ticket created by {$ticket->requester_name}",
            'created_at' => $ticket->created_at,
        ]]);

        // Merge, deduplicate (prefer comment-derived entries over duplicate log entries), sort newest first
        $logTimestamps = $logEntries
            ->filter(fn ($e) => in_array($e['action'], ['ticket_replied', 'ticket_replied_and_closed', 'ticket_note_added']))
            ->pluck('created_at')
            ->map(fn ($dt) => $dt->format('Y-m-d H:i'));

        $filteredComments = $commentEntries->filter(function ($e) use ($logTimestamps) {
            return ! $logTimestamps->contains($e['created_at']->format('Y-m-d H:i'));
        });

        // Remove any duplicate ticket_created log entries since we always add one
        $filteredLogs = $logEntries->filter(fn ($e) => $e['action'] !== 'ticket_created');

        $activityLogs = $filteredLogs->concat($filteredComments)->concat($createdEntry)
            ->sortByDesc('created_at')
            ->values();

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'teams' => Team::all(),
            'agents' => User::all(['id', 'name']),
            'labels' => Label::all(),
            'cannedResponses' => $cannedResponses,
            'hasCustomerAccount' => $hasCustomerAccount,
            'requesterTickets' => $requesterTickets,
            'duplicates' => $duplicates,
            'activityLogs' => $activityLogs,
        ]);
    }

    public function requester(string $email)
    {
        $tickets = auth()->user()->visibleTicketsQuery()
            ->where('requester_email', $email)
            ->with(['assignee', 'team'])
            ->latest()
            ->paginate(25);

        $requesterName = $tickets->first()?->requester_name ?? $email;

        return Inertia::render('Tickets/Requester', [
            'email' => $email,
            'requesterName' => $requesterName,
            'tickets' => $tickets,
        ]);
    }

    public function myReassignments()
    {
        $ticketIds = ActivityLog::where('user_id', auth()->id())
            ->where('action', 'ticket_updated')
            ->where('subject_type', 'App\\Models\\Ticket')
            ->whereNotNull('properties->changes->team_id')
            ->pluck('subject_id')
            ->unique();

        $tickets = Ticket::whereIn('id', $ticketIds)
            ->with(['assignee', 'team'])
            ->latest()
            ->paginate(25);

        return Inertia::render('Tickets/MyReassignments', [
            'tickets' => $tickets,
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
            $agentName = auth()->user()->name;
            $parts = [];
            if (isset($changes['status'])) {
                $parts[] = "changed status to {$changes['status']}";
            }
            if (isset($changes['priority'])) {
                $parts[] = "changed priority to {$changes['priority']}";
            }
            if (array_key_exists('team_id', $changes)) {
                $teamName = $changes['team_id'] ? Team::find($changes['team_id'])?->name ?? 'Unknown' : 'Unassigned';
                $parts[] = "reassigned team to {$teamName}";
            }
            if (array_key_exists('assigned_to', $changes)) {
                $assigneeName = $changes['assigned_to'] ? User::find($changes['assigned_to'])?->name ?? 'Unknown' : 'Unassigned';
                $parts[] = "assigned to {$assigneeName}";
            }
            $description = $agentName . ' ' . (empty($parts) ? "updated ticket" : implode(', ', $parts));
            ActivityLogger::log('ticket_updated', $description, $ticket, ['changes' => $changes]);
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

        if (! auth()->user()->canSeeTicket($fresh)) {
            return redirect()->route('tickets.index')
                ->with('warning', "Ticket {$ticket->reference} has been reassigned. You no longer have access to view it.");
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

    public function sendPasswordReset(Ticket $ticket)
    {
        $customer = Customer::withoutGlobalScopes()
            ->where('email', $ticket->requester_email)
            ->first();

        if (! $customer) {
            return back()->with('warning', 'No customer account found for this requester.');
        }

        $token = Str::random(64);

        DB::table('customer_password_resets')->where('email', $customer->email)->delete();
        DB::table('customer_password_resets')->insert([
            'email' => $customer->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $resetUrl = url("/portal/reset-password/{$token}?email=" . urlencode($customer->email));
        Mail::to($customer->email)->send(new CustomerPasswordReset($resetUrl));

        return back()->with('success', "Password reset email sent to {$customer->email}.");
    }

    public function markDuplicate(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'duplicate_of' => 'required|exists:tickets,id',
        ]);

        if ($validated['duplicate_of'] == $ticket->id) {
            return back()->withErrors(['duplicate_of' => 'A ticket cannot be a duplicate of itself.']);
        }

        $original = Ticket::find($validated['duplicate_of']);

        if ($original->duplicate_of) {
            return back()->withErrors(['duplicate_of' => 'The target ticket is itself a duplicate. Please select the original ticket.']);
        }

        $ticket->update([
            'duplicate_of' => $validated['duplicate_of'],
            'status' => 'closed',
            'resolved_at' => $ticket->resolved_at ?? now(),
        ]);

        $ticket->comments()->create([
            'body' => "This ticket was marked as a duplicate of <a href=\"" . route('tickets.show', $original->id) . "\">{$original->reference}</a>.",
            'type' => 'system',
            'is_internal' => false,
        ]);

        ActivityLogger::log('ticket_marked_duplicate', "Marked {$ticket->reference} as duplicate of {$original->reference}", $ticket, [
            'duplicate_of' => $original->id,
        ]);

        return back()->with('success', "Ticket marked as duplicate of {$original->reference} and closed.");
    }

    public function searchTickets(Request $request)
    {
        $search = $request->validate(['q' => 'required|string|min:2'])['q'];

        $tickets = $request->user()->visibleTicketsQuery()
            ->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            })
            ->whereNull('duplicate_of')
            ->limit(10)
            ->get(['id', 'reference', 'subject', 'status']);

        return response()->json($tickets);
    }
}
