<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessInboundEmailJob;
use App\Models\InboundEmail;
use App\Models\Scopes\TenantScope;
use App\Services\Inbound\InboundTenantResolver;
use App\Services\Inbound\MailgunPayloadMapper;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MailgunInboundController extends Controller
{
    public function __construct(
        protected MailgunPayloadMapper $mapper,
        protected InboundTenantResolver $resolver,
    ) {}

    public function __invoke(Request $request)
    {
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $tenant = $this->resolver->resolve($this->mapper->recipientCandidates($request));

        if (! $tenant) {
            // Never guess. An unroutable message is dropped with a log entry
            // rather than filed into an arbitrary tenant's helpdesk.
            Log::warning('Inbound message could not be routed to a tenant', [
                'recipient' => $request->input('recipient'),
                'sender' => $request->input('sender'),
            ]);

            return response()->json(['status' => 'unrouted'], 202);
        }

        $payload = $this->mapper->map($request);
        $messageId = $payload['message_id'] ?: uniqid('mailgun-', true);

        return TenantContext::run($tenant, function () use ($payload, $messageId, $tenant) {
            $existing = InboundEmail::where('message_id', $messageId)->first();

            if ($existing) {
                return response()->json(['status' => 'duplicate', 'id' => $existing->id]);
            }

            try {
                $record = InboundEmail::create([
                    'tenant_id' => $tenant->id,
                    'message_id' => $messageId,
                    'payload' => $payload,
                    'auth_results' => \App\DTOs\InboundMessage::fromWebhookPayload($payload)->authResults,
                    'status' => 'pending',
                ]);
            } catch (QueryException $e) {
                if ($this->isDuplicate($e)) {
                    return response()->json(['status' => 'duplicate']);
                }

                throw $e;
            }

            ProcessInboundEmailJob::dispatch($record->id);

            return response()->json(['status' => 'queued', 'id' => $record->id]);
        });
    }

    /**
     * Mailgun signs with HMAC-SHA256 over timestamp+token using the HTTP
     * webhook signing key. The timestamp window bounds replay; a duplicate
     * delivery that slips inside it is caught by the Message-ID check.
     */
    protected function verifySignature(Request $request): bool
    {
        $signingKey = config('support.inbound.mailgun.signing_key');

        if (empty($signingKey)) {
            // Fail closed: an unsigned endpoint would let anyone inject tickets.
            Log::error('MAILGUN_WEBHOOK_SIGNING_KEY is not set; rejecting inbound message.');

            return false;
        }

        $timestamp = (string) $request->input('timestamp', '');
        $token = (string) $request->input('token', '');
        $signature = (string) $request->input('signature', '');

        if ($timestamp === '' || $token === '' || $signature === '') {
            return false;
        }

        $tolerance = (int) config('support.inbound.mailgun.tolerance_seconds', 300);

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $timestamp.$token, $signingKey),
            $signature,
        );
    }

    protected function isDuplicate(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'UNIQUE constraint')
            || str_contains($message, 'Duplicate entry');
    }
}
