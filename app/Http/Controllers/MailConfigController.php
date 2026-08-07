<?php

namespace App\Http\Controllers;

use App\Models\MailConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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
                'inbound_method' => $config->inbound_method,
                'imap_host' => $config->imap_host,
                'imap_port' => $config->imap_port,
                'imap_encryption' => $config->imap_encryption,
                'imap_username' => $config->imap_username,
                'has_imap_password' => ! empty($config->imap_password),
                'imap_folder' => $config->imap_folder,
                'imap_poll_interval' => $config->imap_poll_interval,
                'imap_delete_after_process' => $config->imap_delete_after_process,
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
            'inbound_method' => 'required|in:none,imap,webhook,postfix',
            'imap_host' => 'nullable|required_if:inbound_method,imap|string|max:255',
            'imap_port' => 'nullable|integer|min:1|max:65535',
            'imap_encryption' => 'nullable|in:ssl,tls',
            'imap_username' => 'nullable|required_if:inbound_method,imap|string|max:255',
            'imap_password' => 'nullable|string|max:500',
            'imap_folder' => 'nullable|string|max:255',
            'imap_poll_interval' => 'nullable|integer|min:1|max:60',
            'imap_delete_after_process' => 'boolean',
        ]);

        $config = MailConfiguration::first();

        if ($config) {
            if (empty($validated['password'])) {
                unset($validated['password']);
            }
            if (empty($validated['imap_password'])) {
                unset($validated['imap_password']);
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

    public function testImap()
    {
        try {
            Artisan::call('support:poll-imap');
            $output = Artisan::output();

            return back()->with('success', 'IMAP poll completed: ' . trim($output));
        } catch (\Throwable $e) {
            return back()->with('error', 'IMAP poll failed: ' . $e->getMessage());
        }
    }
}
