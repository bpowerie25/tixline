<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['type'] = ($validated['is_internal'] ?? false) ? 'note' : 'reply';

        $ticket->comments()->create($validated);

        if (!$ticket->first_responded_at && !($validated['is_internal'] ?? false)) {
            $ticket->update(['first_responded_at' => now()]);
        }

        return back()->with('success', 'Comment added.');
    }
}
