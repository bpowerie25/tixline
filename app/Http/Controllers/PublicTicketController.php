<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Ticket;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicTicketController extends Controller
{
    public function create(Request $request)
    {
        $form = null;
        if ($request->filled('form')) {
            $form = Form::where('slug', $request->form)
                ->where('is_active', true)
                ->with('fields')
                ->first();
        }

        $forms = Form::where('is_active', true)->get(['id', 'name', 'slug']);

        return Inertia::render('Public/SubmitTicket', [
            'form' => $form,
            'forms' => $forms,
        ]);
    }

    public function store(Request $request, WorkflowEngine $engine)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'nullable|string',
            'requester_name' => 'required|string|max:255',
            'requester_email' => 'required|email|max:255',
            'form_id' => 'nullable|exists:forms,id',
            'custom_fields' => 'nullable|array',
        ]);

        $validated['source'] = 'web';
        $ticket = Ticket::create($validated);

        $engine->run($ticket->fresh(), 'ticket_created');

        return Inertia::render('Public/TicketConfirmation', [
            'ticket' => $ticket->only('reference', 'subject'),
        ]);
    }
}
