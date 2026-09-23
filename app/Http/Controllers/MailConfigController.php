<?php

namespace App\Http\Controllers;

use App\Models\InboundMailbox;
use App\Models\MailConfiguration;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class MailConfigController extends Controller
{
    public function index()
    {
        $config = MailConfiguration::where('tenant_id', auth()->user()->tenant_id)->first();

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
            ] : null,
            'mailboxes' => InboundMailbox::where('tenant_id', auth()->user()->tenant_id)
                ->with('team:id,name')
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'imap_host' => $m->imap_host,
                    'imap_port' => $m->imap_port,
                    'imap_encryption' => $m->imap_encryption,
                    'imap_username' => $m->imap_username,
                    'has_imap_password' => ! empty($m->imap_password),
                    'imap_folder' => $m->imap_folder,
                    'poll_interval' => $m->poll_interval,
                    'delete_after_process' => $m->delete_after_process,
                    'team_id' => $m->team_id,
                    'team_name' => $m->team?->name,
                    'is_active' => $m->is_active,
                ]),
            'teams' => Team::where('tenant_id', auth()->user()->tenant_id)
                ->select('id', 'name')
                ->get(),
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
            'password' => 'nullable|string',
            'from_address' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'inbound_method' => 'required|in:none,imap,webhook,postfix',
        ]);

        $config = MailConfiguration::where('tenant_id', auth()->user()->tenant_id)->first();

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
            $config = MailConfiguration::where('tenant_id', auth()->user()->tenant_id)->first();

            if ($config && $config->is_active) {
                Mail::purge('smtp');

                config([
                    'mail.default' => $config->mailer,
                    "mail.mailers.{$config->mailer}.transport" => $config->mailer,
                    "mail.mailers.{$config->mailer}.host" => $config->host,
                    "mail.mailers.{$config->mailer}.port" => $config->port,
                    "mail.mailers.{$config->mailer}.encryption" => $config->encryption,
                    "mail.mailers.{$config->mailer}.username" => $config->username,
                    "mail.mailers.{$config->mailer}.password" => $config->password,
                    'mail.from.address' => $config->from_address ?: config('mail.from.address'),
                    'mail.from.name' => $config->from_name ?: config('mail.from.name'),
                ]);
            }

            Mail::raw('This is a test email from Tixline to verify your mail configuration is working correctly.', function ($message) use ($request) {
                $message->to($request->test_email)
                    ->subject('Tixline Mail Configuration Test');
            });

            return back()->with('success', 'Test email sent to '.$request->test_email);
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to send test email: '.$e->getMessage());
        }
    }
}
