<?php

namespace App\Http\Controllers;

use App\Models\MailConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class MailConfigController extends Controller
{
    public function index()
    {
        $config = MailConfiguration::first();

        return Inertia::render('Settings/Mail', [
            'config' => $config ? [
                'id' => $config->id,
                'mailer' => $config->mailer,
                'host' => $config->host,
                'port' => $config->port,
                'encryption' => $config->encryption,
                'username' => $config->username,
                'has_password' => ! empty($config->password),
                'from_address' => $config->from_address,
                'from_name' => $config->from_name,
                'is_active' => $config->is_active,
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mailer' => 'required|in:smtp,ses,postmark,sendmail,log',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'encryption' => 'nullable|in:tls,ssl',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:500',
            'from_address' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $config = MailConfiguration::first();

        if ($config) {
            if (empty($validated['password'])) {
                unset($validated['password']);
            }
            $config->update($validated);
        } else {
            $config = MailConfiguration::create($validated);
        }

        return back()->with('success', 'Mail configuration saved.');
    }

    public function test(Request $request)
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        try {
            Mail::raw('This is a test email from Tixline to verify your mail configuration is working correctly.', function ($message) use ($request) {
                $message->to($request->test_email)
                    ->subject('Tixline Mail Configuration Test');
            });

            return back()->with('success', 'Test email sent to ' . $request->test_email);
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to send test email: ' . $e->getMessage());
        }
    }
}
