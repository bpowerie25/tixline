<?php

namespace App\Http\Controllers;

use App\Services\InboundEmailProcessor;
use Illuminate\Http\Request;

class InboundEmailController extends Controller
{
    public function webhook(Request $request, InboundEmailProcessor $processor)
    {
        if (!$this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $validated = $request->validate([
            'from_email' => 'required|email',
            'from_name' => 'nullable|string',
            'subject' => 'required|string',
            'body' => 'required|string',
            'headers' => 'nullable|array',
        ]);

        $result = $processor->process(
            fromEmail: $validated['from_email'],
            fromName: $validated['from_name'] ?? $validated['from_email'],
            subject: $validated['subject'],
            body: $validated['body'],
            headers: $validated['headers'] ?? [],
        );

        return response()->json($result, $result['status'] === 'rejected' ? 422 : 200);
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
