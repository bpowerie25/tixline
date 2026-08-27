<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Rules\TenantScoped;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;

class TicketApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::with(['assignee:id,name', 'team:id,name', 'labels:id,name,color']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        return $query->latest()->paginate($request->input('per_page', 25));
    }

    public function show(Ticket $ticket)
    {
        $ticket->load([
            'assignee:id,name',
            'team:id,name',
            'labels:id,name,color',
            'comments' => fn ($q) => $q->with('user:id,name')->oldest(),
        ]);

        // API consumers get the raw body deliberately
        $ticket->makeVisible('body');
        $ticket->comments->each->makeVisible('body');

        return $ticket;
    }

    public function store(Request $request, WorkflowEngine $engine)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'nullable|string',
            'requester_name' => 'required|string|max:255',
            'requester_email' => 'required|email|max:255',
            'priority' => 'in:low,normal,high,urgent',
            'team_id' => ['nullable', TenantScoped::exists('teams')],
            'assigned_to' => ['nullable', TenantScoped::exists('users')],
            'labels' => 'nullable|array',
            'labels.*' => [TenantScoped::exists('labels')],
            'custom_fields' => 'nullable|array',
        ]);

        $labels = $validated['labels'] ?? [];
        unset($validated['labels']);
        $validated['source'] = 'api';

        $ticket = Ticket::create($validated);

        if (! empty($labels)) {
            $ticket->labels()->sync($labels);
        }

        $engine->run($ticket->fresh(), 'ticket_created');

        return response()->json($ticket->load('assignee:id,name', 'team:id,name', 'labels'), 201);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:open,pending,resolved,closed',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'team_id' => ['nullable', TenantScoped::exists('teams')],
            'assigned_to' => ['nullable', TenantScoped::exists('users')],
            'labels' => 'nullable|array',
            'labels.*' => [TenantScoped::exists('labels')],
        ]);

        if (isset($validated['status']) && $validated['status'] === 'resolved' && ! $ticket->resolved_at) {
            $validated['resolved_at'] = now();
        }

        $labels = $validated['labels'] ?? null;
        unset($validated['labels']);

        $ticket->update($validated);

        if ($labels !== null) {
            $ticket->labels()->sync($labels);
        }

        return $ticket->fresh()->load('assignee:id,name', 'team:id,name', 'labels');
    }

    public function addComment(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['type'] = ($validated['is_internal'] ?? false) ? 'note' : 'reply';

        $comment = $ticket->comments()->create($validated);

        if (! $ticket->first_responded_at && ! ($validated['is_internal'] ?? false)) {
            $ticket->update(['first_responded_at' => now()]);
        }

        // API consumers get the raw body deliberately
        $comment->makeVisible('body');

        return response()->json($comment->load('user:id,name'), 201);
    }
}
