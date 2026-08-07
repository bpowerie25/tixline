<?php

namespace App\Console\Commands;

use App\DTOs\InboundMessage;
use App\Models\InboundEmail;
use App\Models\MailConfiguration;
use App\Services\InboundEmailProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;

class PollImapMailbox extends Command
{
    protected $signature = 'support:poll-imap';
    protected $description = 'Poll configured IMAP mailbox for new emails and create tickets';

    public function handle(InboundEmailProcessor $processor): int
    {
        $config = MailConfiguration::first();

        if (! $config || $config->inbound_method !== 'imap') {
            $this->info('IMAP polling is not configured or not enabled.');

            return self::SUCCESS;
        }

        if (! $config->imap_host || ! $config->imap_username || ! $config->imap_password) {
            $this->error('IMAP credentials are incomplete.');

            return self::FAILURE;
        }

        try {
            $client = Client::make([
                'host' => $config->imap_host,
                'port' => $config->imap_port ?: 993,
                'encryption' => $config->imap_encryption ?: 'ssl',
                'validate_cert' => true,
                'username' => $config->imap_username,
                'password' => $config->imap_password,
                'protocol' => 'imap',
            ]);

            $client->connect();
        } catch (\Throwable $e) {
            $this->error('Failed to connect to IMAP: ' . $e->getMessage());
            Log::error('IMAP connection failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $folder = $client->getFolder($config->imap_folder ?: 'INBOX');

        if (! $folder) {
            $this->error("Folder '{$config->imap_folder}' not found.");
            $client->disconnect();

            return self::FAILURE;
        }

        $messages = $folder->query()->unseen()->get();

        $processed = 0;
        $skipped = 0;

        foreach ($messages as $message) {
            $messageId = $message->getMessageId()?->toString() ?: 'imap-' . md5($message->getSubject() . $message->getDate());

            // Idempotency check
            if (InboundEmail::where('message_id', $messageId)->exists()) {
                $message->setFlag('Seen');
                $skipped++;

                continue;
            }

            try {
                // Build headers array
                $headers = [];
                $headerObj = $message->getHeader();
                $rawHeaders = $headerObj->raw ?? '';
                if (is_string($rawHeaders)) {
                    foreach (explode("\n", $rawHeaders) as $line) {
                        $line = trim($line);
                        if (str_contains($line, ':')) {
                            [$key, $value] = explode(':', $line, 2);
                            $headers[strtolower(trim($key))] = trim($value);
                        }
                    }
                } elseif (is_array($rawHeaders) || is_object($rawHeaders)) {
                    foreach ($rawHeaders as $key => $value) {
                        $headers[strtolower($key)] = is_array($value) ? implode(', ', $value) : (string) $value;
                    }
                }

                // Build attachments
                $attachments = [];
                foreach ($message->getAttachments() as $attachment) {
                    $attachments[] = [
                        'filename' => $attachment->getName(),
                        'content' => base64_encode($attachment->getContent()),
                        'content_type' => $attachment->getMimeType(),
                    ];
                }

                // Extract sender info safely
                $from = $message->getFrom();
                $fromEmail = '';
                $fromName = '';
                if ($from && count($from) > 0) {
                    $firstFrom = $from[0];
                    $fromEmail = is_object($firstFrom) ? ($firstFrom->mail ?? (string) $firstFrom) : (string) $firstFrom;
                    $fromName = is_object($firstFrom) ? ($firstFrom->personal ?? '') : '';
                }

                $inboundMessage = new InboundMessage(
                    messageId: $messageId,
                    fromEmail: $fromEmail,
                    fromName: $fromName,
                    subject: (string) ($message->getSubject() ?? '(No Subject)'),
                    body: $message->getHTMLBody() ?: ($message->getTextBody() ?: ''),
                    headers: $headers,
                    attachments: $attachments,
                );

                // Persist for audit
                $inboundEmail = InboundEmail::create([
                    'message_id' => $messageId,
                    'payload' => [
                        'from_email' => $inboundMessage->fromEmail,
                        'from_name' => $inboundMessage->fromName,
                        'subject' => $inboundMessage->subject,
                        'body' => $inboundMessage->body,
                        'headers' => $inboundMessage->headers,
                    ],
                    'status' => 'pending',
                ]);

                $result = $processor->process($inboundMessage);

                $inboundEmail->update([
                    'status' => $result['status'] === 'rejected' ? 'rejected' : 'processed',
                    'result' => json_encode($result),
                    'processed_at' => now(),
                ]);

                // Mark as seen (or delete)
                $message->setFlag('Seen');

                if ($config->imap_delete_after_process) {
                    $message->delete();
                }

                $processed++;
                $this->line("Processed: {$inboundMessage->subject} ({$result['status']})");

            } catch (\Throwable $e) {
                Log::error('IMAP message processing failed', [
                    'message_id' => $messageId,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed: {$messageId} - {$e->getMessage()}");

                // Still mark as seen to avoid reprocessing failures
                $message->setFlag('Seen');
            }
        }

        $client->disconnect();

        $this->info("Done. Processed: {$processed}, Skipped: {$skipped}");

        return self::SUCCESS;
    }
}
