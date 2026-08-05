<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Ticket;
use App\Services\WorkflowEngine;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CustomerPortalController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Portal/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $this->ensureCustomerLoginNotRateLimited($request);

        if (Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($this->customerThrottleKey($request));
            $request->session()->regenerate();

            return redirect()->route('portal.tickets');
        }

        RateLimiter::hit($this->customerThrottleKey($request));

        return back()->withErrors([
            'email' => 'Invalid credentials.',
        ]);
    }

    protected function customerThrottleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());
    }

    protected function ensureCustomerLoginNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->customerThrottleKey($request), 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->customerThrottleKey($request));

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function showRegister()
    {
        return Inertia::render('Portal/Register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers',
            'password' => 'required|string|min:8|confirmed',
            'organization' => 'nullable|string|max:255',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $customer = Customer::create($validated);

        Auth::guard('customer')->login($customer);

        return redirect()->route('portal.tickets');
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    public function tickets()
    {
        $customer = Auth::guard('customer')->user();
        $tickets = Ticket::where('requester_email', $customer->email)
            ->with('team:id,name')
            ->latest()
            ->paginate(20);

        return Inertia::render('Portal/Tickets', [
            'tickets' => $tickets,
            'customer' => $customer,
        ]);
    }

    public function showTicket(int $ticket)
    {
        $customer = Auth::guard('customer')->user();

        $ticket = Ticket::where('requester_email', $customer->email)
            ->findOrFail($ticket);

        $ticket->load(['team:id,name', 'comments' => function ($q) {
            $q->where('is_internal', false)->with('user:id,name')->oldest();
        }]);

        return Inertia::render('Portal/TicketDetail', [
            'ticket' => $ticket,
            'customer' => $customer,
        ]);
    }

    public function replyToTicket(Request $request, int $ticket)
    {
        $customer = Auth::guard('customer')->user();

        $ticket = Ticket::where('requester_email', $customer->email)
            ->findOrFail($ticket);

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $ticket->comments()->create([
            'body' => $validated['body'],
            'type' => 'reply',
            'is_internal' => false,
        ]);

        if ($ticket->status === 'resolved') {
            $ticket->update(['status' => 'open']);
        }

        return back()->with('success', 'Reply sent.');
    }

    public function createTicket(Request $request, WorkflowEngine $engine)
    {
        $customer = Auth::guard('customer')->user();

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'nullable|string',
        ]);

        $ticket = Ticket::create([
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'requester_name' => $customer->name,
            'requester_email' => $customer->email,
            'source' => 'web',
        ]);

        $engine->run($ticket, 'ticket_created');

        return redirect()->route('portal.ticket', $ticket)
            ->with('success', 'Ticket created.');
    }
}
