<?php

namespace App\Http\Controllers;

use App\Mail\CustomerAccountCreated;
use App\Models\Customer;
use App\Models\Form;
use App\Models\Ticket;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

        // Auto-create customer account if one doesn't exist
        $accountCreated = false;
        $customer = Customer::where('email', $validated['requester_email'])->first();

        if (! $customer) {
            $temporaryPassword = Str::random(12);
            $customer = Customer::create([
                'name' => $validated['requester_name'],
                'email' => $validated['requester_email'],
                'password' => $temporaryPassword,
            ]);

            Mail::to($customer->email)->send(
                new CustomerAccountCreated($customer, $temporaryPassword, $ticket->reference)
            );

            $accountCreated = true;
        }

        return Inertia::render('Public/TicketConfirmation', [
            'ticket' => $ticket->only('reference', 'subject'),
            'accountCreated' => $accountCreated,
        ]);
    }
}
