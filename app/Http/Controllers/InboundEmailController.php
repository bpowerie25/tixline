<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessInboundEmailJob;
use App\Models\InboundEmail;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class InboundEmailController extends Controller
{
    public function webhook(Request $request)
    {
        // 1. Verify signature
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 2. Check timestamp — reject replays older than 5 minutes
        $timestamp = $request->input('timestamp');
        if ($timestamp && abs(time() - (int) $timestamp) > 300) {
            return response()->json(['error' => 'Request expired'], 401);
        }

        // 3. Extract message ID for idempotency
        $messageId = $request->input('message_id', '');
        if (empty($messageId)) {
            $messageId = uniqid('webhook-', true);
        }

        // 4. Idempotency check — duplicate delivery is a no-op 200
        $existing = InboundEmail::where('message_id', $messageId)->first();
        if ($existing) {
            return response()->json(['status' => 'duplicate', 'id' => $existing->id]);
        }

        // 5. Persist raw payload
        try {
            $record = InboundEmail::create([
                'message_id' => $messageId,
                'payload' => $request->all(),
                'status' => 'pending',
            ]);
        } catch (QueryException $e) {
            // Race condition — another request inserted between our check and insert
            if (str_contains($e->getMessage(), 'UNIQUE constraint') || str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json(['status' => 'duplicate']);
            }
            throw $e;
        }

        // 6. Dispatch job for async processing
        ProcessInboundEmailJob::dispatch($record->id);

        return response()->json(['status' => 'queued', 'id' => $record->id]);
    }

    protected function verifySignature(Request $request): bool
    {
        $secret = config('support.inbound.webhook_secret');

        if (empty($secret)) {
            return false;
        }

        $signature = $request->header('X-Webhook-Signature');

        if (empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
