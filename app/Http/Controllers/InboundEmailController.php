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
        // 1. Require and validate timestamp header
        $timestamp = $request->header('X-Webhook-Timestamp');
        if (empty($timestamp) || ! ctype_digit($timestamp)) {
            return response()->json(['error' => 'Missing or invalid timestamp'], 401);
        }

        // 2. Reject replays older than 5 minutes
        if (abs(time() - (int) $timestamp) > 300) {
            return response()->json(['error' => 'Request expired'], 401);
        }

        // 3. Verify signature — HMAC covers timestamp.body
        if (! $this->verifySignature($request, $timestamp)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 4. Extract message ID for idempotency
        $messageId = $request->input('message_id', '');
        if (empty($messageId)) {
            $messageId = uniqid('webhook-', true);
        }

        // 5. Idempotency check — duplicate delivery is a no-op 200
        $existing = InboundEmail::where('message_id', $messageId)->first();
        if ($existing) {
            return response()->json(['status' => 'duplicate', 'id' => $existing->id]);
        }

        // 6. Persist raw payload with auth results
        $message = \App\DTOs\InboundMessage::fromWebhookPayload($request->all());
        try {
            $record = InboundEmail::create([
                'message_id' => $messageId,
                'payload' => $request->all(),
                'auth_results' => $message->authResults,
                'status' => 'pending',
            ]);
        } catch (QueryException $e) {
            // Race condition — another request inserted between our check and insert
            if (str_contains($e->getMessage(), 'UNIQUE constraint') || str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json(['status' => 'duplicate']);
            }
            throw $e;
        }

        // 7. Dispatch job for async processing
        ProcessInboundEmailJob::dispatch($record->id);

        return response()->json(['status' => 'queued', 'id' => $record->id]);
    }

    /**
     * Verify HMAC-SHA256 signature over "timestamp.body".
     *
     * The signed payload is the timestamp header value concatenated with
     * a dot and the raw request body: hash_hmac('sha256', "$timestamp.$body", $secret).
     * This prevents replay attacks where the timestamp is altered after signing.
     */
    protected function verifySignature(Request $request, string $timestamp): bool
    {
        $secret = config('support.inbound.webhook_secret');

        if (empty($secret)) {
            return false;
        }

        $signature = $request->header('X-Webhook-Signature');

        if (empty($signature)) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$request->getContent();
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expected, $signature);
    }
}
