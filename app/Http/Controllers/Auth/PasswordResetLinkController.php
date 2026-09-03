<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Temporarily unbind the tenant so TenantScope doesn't filter
        // the user lookup — the user may not belong to the resolved tenant,
        // or no tenant may have resolved on this unauthenticated page.
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        app()->forgetInstance('tenant');

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } finally {
            if ($tenant) {
                app()->instance('tenant', $tenant);
            }
        }

        if ($status == Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
