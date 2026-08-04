<?php

namespace App\Http\Controllers;

use App\Mail\TicketReply;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CommentController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'is_internal' => 'boolean',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['type'] = ($validated['is_internal'] ?? false) ? 'note' : 'reply';

        unset($validated['attachments']);
        $comment = $ticket->comments()->create($validated);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/' . $ticket->id, 'public');
                $comment->attachments()->create([
                    'filename' => $file->hashName(),
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'path' => $path,
                ]);
            }
        }

        if (!($validated['is_internal'] ?? false)) {
            if (!$ticket->first_responded_at) {
                $ticket->update(['first_responded_at' => now()]);
            }

            Mail::to($ticket->requester_email)->send(new TicketReply($ticket, $comment));
        }

        return back()->with('success', 'Comment added.');
    }
}
