<?php

namespace App\Http\Controllers;

use App\DTOs\InboundMessage;
use App\Models\InboundEmail;
use App\Services\InboundEmailProcessor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InboundEmailReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = InboundEmail::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return Inertia::render('Settings/InboundEmails', [
            'emails' => $query->paginate(25)->through(fn ($email) => [
                'id' => $email->id,
                'message_id' => $email->message_id,
                'from_email' => $email->payload['from_email'] ?? '',
                'from_name' => $email->payload['from_name'] ?? '',
                'subject' => $email->payload['subject'] ?? '',
                'status' => $email->status,
                'result' => $email->result,
                'created_at' => $email->created_at->toDateTimeString(),
                'processed_at' => $email->processed_at?->toDateTimeString(),
            ]),
            'filter' => $request->status ?? 'all',
            'counts' => [
                'all' => InboundEmail::count(),
                'processed' => InboundEmail::where('status', 'processed')->count(),
                'rejected' => InboundEmail::where('status', 'rejected')->count(),
                'failed' => InboundEmail::where('status', 'failed')->count(),
                'pending' => InboundEmail::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function show(InboundEmail $inboundEmail)
    {
        return Inertia::render('Settings/InboundEmailDetail', [
            'email' => [
                'id' => $inboundEmail->id,
                'message_id' => $inboundEmail->message_id,
                'from_email' => $inboundEmail->payload['from_email'] ?? '',
                'from_name' => $inboundEmail->payload['from_name'] ?? '',
                'subject' => $inboundEmail->payload['subject'] ?? '',
                'body' => $inboundEmail->payload['body'] ?? '',
                'headers' => $inboundEmail->payload['headers'] ?? [],
                'status' => $inboundEmail->status,
                'result' => $inboundEmail->result,
                'auth_results' => $inboundEmail->auth_results,
                'created_at' => $inboundEmail->created_at->toDateTimeString(),
                'processed_at' => $inboundEmail->processed_at?->toDateTimeString(),
            ],
        ]);
    }

    public function reprocess(InboundEmail $inboundEmail, InboundEmailProcessor $processor)
    {
        if (! $inboundEmail->payload) {
            return back()->with('error', 'No payload available to reprocess.');
        }

        $payload = $inboundEmail->payload;

        $message = new InboundMessage(
            messageId: $inboundEmail->message_id . '-reprocess-' . time(),
            fromEmail: $payload['from_email'] ?? '',
            fromName: $payload['from_name'] ?? '',
            subject: $payload['subject'] ?? '',
            body: $payload['body'] ?? '',
            headers: $payload['headers'] ?? [],
            attachments: [],
        );

        $result = $processor->process($message);

        $inboundEmail->update([
            'status' => $result['status'] === 'rejected' ? 'rejected' : 'processed',
            'result' => json_encode($result),
            'processed_at' => now(),
        ]);

        return back()->with('success', "Reprocessed: {$result['status']}" . (isset($result['reference']) ? " ({$result['reference']})" : ''));
    }

    public function destroy(InboundEmail $inboundEmail)
    {
        $inboundEmail->delete();

        return back()->with('success', 'Email record deleted.');
    }
}
