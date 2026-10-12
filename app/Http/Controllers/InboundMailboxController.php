<?php

namespace App\Http\Controllers;

use App\Models\InboundMailbox;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class InboundMailboxController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'imap_host' => 'required|string|max:255',
            'imap_port' => 'nullable|integer|min:1|max:65535',
            'imap_encryption' => 'nullable|in:ssl,tls',
            'imap_username' => 'required|string|max:255',
            'imap_password' => 'required|string|max:500',
            'imap_folder' => 'nullable|string|max:255',
            'poll_interval' => 'nullable|integer|min:1|max:60',
            'delete_after_process' => 'boolean',
            'team_id' => 'nullable|exists:teams,id',
            'is_active' => 'boolean',
        ]);

        $mailbox = InboundMailbox::create($validated);

        ActivityLogger::log('mailbox_created', "Created inbound mailbox {$mailbox->name}", $mailbox);

        return back()->with('success', 'Mailbox created.');
    }

    public function update(Request $request, InboundMailbox $mailbox)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'imap_host' => 'required|string|max:255',
            'imap_port' => 'nullable|integer|min:1|max:65535',
            'imap_encryption' => 'nullable|in:ssl,tls',
            'imap_username' => 'required|string|max:255',
            'imap_password' => 'nullable|string|max:500',
            'imap_folder' => 'nullable|string|max:255',
            'poll_interval' => 'nullable|integer|min:1|max:60',
            'delete_after_process' => 'boolean',
            'team_id' => 'nullable|exists:teams,id',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['imap_password'])) {
            unset($validated['imap_password']);
        }

        $mailbox->update($validated);

        ActivityLogger::log('mailbox_updated', "Updated inbound mailbox {$mailbox->name}", $mailbox);

        return back()->with('success', 'Mailbox updated.');
    }

    public function destroy(InboundMailbox $mailbox)
    {
        ActivityLogger::log('mailbox_deleted', "Deleted inbound mailbox {$mailbox->name}", $mailbox);

        $mailbox->delete();

        return back()->with('success', 'Mailbox deleted.');
    }

    public function test(InboundMailbox $mailbox)
    {
        try {
            Artisan::call('support:poll-imap', ['--mailbox' => $mailbox->id]);
            $output = Artisan::output();

            return back()->with('success', 'IMAP poll completed: '.trim($output));
        } catch (\Throwable $e) {
            return back()->with('error', 'IMAP poll failed: '.$e->getMessage());
        }
    }
}
