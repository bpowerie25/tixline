<?php

namespace App\Console\Commands;

use App\DTOs\InboundMessage;
use App\Models\InboundEmail;
use App\Services\InboundEmailProcessor;
use Illuminate\Console\Command;

class ProcessInboundEmail extends Command
{
    protected $signature = 'support:process-email';

    protected $description = 'Process a raw email from stdin (for Postfix pipe transport)';

    public function handle(InboundEmailProcessor $processor): int
    {
        $raw = file_get_contents('php://stdin');

        if (empty($raw)) {
            $this->error('No email data received on stdin.');

            return self::FAILURE;
        }

        $message = InboundMessage::fromRawEmail($raw);

        // Idempotency — check if we've already processed this Message-ID
        if (InboundEmail::where('message_id', $message->messageId)->exists()) {
            $this->line('Duplicate message, skipping: '.$message->messageId);

            return self::SUCCESS;
        }

        // Persist for audit trail
        $record = InboundEmail::create([
            'message_id' => $message->messageId,
            'payload' => [
                'from_email' => $message->fromEmail,
                'from_name' => $message->fromName,
                'subject' => $message->subject,
                'body' => $message->body,
                'headers' => $message->headers,
            ],
            'auth_results' => $message->authResults,
            'status' => 'pending',
        ]);

        $result = $processor->process($message);

        $record->update([
            'status' => $result['status'] === 'rejected' ? 'rejected' : 'processed',
            'result' => json_encode($result),
            'processed_at' => now(),
        ]);

        $this->line(json_encode($result));

        return self::SUCCESS;
    }
}
