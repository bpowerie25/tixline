<?php

namespace App\Http\Controllers;

use App\Mail\TicketReply;
use App\Models\Ticket;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CommentController extends Controller
{
    public function store(Request $request, Ticket $ticket, AttachmentService $attachmentService)
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'is_internal' => 'boolean',
            'close_ticket' => 'boolean',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240',
        ]);

        $closeTicket = $validated['close_ticket'] ?? false;

        $validated['user_id'] = $request->user()->id;
        $validated['type'] = ($validated['is_internal'] ?? false) ? 'note' : 'reply';

        unset($validated['attachments'], $validated['close_ticket']);
        $comment = $ticket->comments()->create($validated);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachmentService->storeUploadedFile($file, $comment);
            }
        }

        if (! ($validated['is_internal'] ?? false)) {
            if (! $ticket->first_responded_at) {
                $ticket->update(['first_responded_at' => now()]);
            }

            // Auto-set to pending after agent reply (awaiting customer response)
            if (! $closeTicket && in_array($ticket->status, ['open'])) {
                $ticket->update(['status' => 'pending']);
            }

            Mail::to($ticket->requester_email)->send(new TicketReply($ticket, $comment));
        }

        if ($closeTicket) {
            $ticket->update(['status' => 'closed', 'resolved_at' => $ticket->resolved_at ?? now()]);
        }

        return back()->with('success', $closeTicket ? 'Reply sent and ticket closed.' : 'Comment added.');
    }
}
