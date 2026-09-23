<?php

namespace App\Console\Commands;

use App\DTOs\InboundMessage;
use App\Models\InboundEmail;
use App\Models\InboundMailbox;
use App\Services\InboundEmailProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;

class PollImapMailbox extends Command
{
    protected $signature = 'support:poll-imap {--mailbox= : Poll a specific mailbox by ID}';

    protected $description = 'Poll configured IMAP mailboxes for new emails and create tickets';

    public function handle(InboundEmailProcessor $processor): int
    {
        $mailboxId = $this->option('mailbox');

        if ($mailboxId) {
            $mailboxes = InboundMailbox::withoutGlobalScopes()->where('id', $mailboxId)->get();
        } else {
            $mailboxes = InboundMailbox::withoutGlobalScopes()->where('is_active', true)->get();
        }

        if ($mailboxes->isEmpty()) {
            $this->info('No active IMAP mailboxes configured.');

            return self::SUCCESS;
        }

        foreach ($mailboxes as $mailbox) {
            try {
                $this->pollMailbox($mailbox, $processor);
            } catch (\Throwable $e) {
                $this->error("Mailbox '{$mailbox->name}' failed: {$e->getMessage()}");
                Log::error('IMAP mailbox polling failed', [
                    'mailbox_id' => $mailbox->id,
                    'mailbox_name' => $mailbox->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }

    private function pollMailbox(InboundMailbox $mailbox, InboundEmailProcessor $processor): void
    {
        $this->info("Polling mailbox: {$mailbox->name} ({$mailbox->imap_username})");

        // Bind tenant context
        if ($mailbox->tenant_id && $mailbox->tenant) {
            app()->instance('tenant', $mailbox->tenant);
            app()->getProvider(\App\Providers\MailConfigServiceProvider::class)?->applyMailConfig();
        }

        if (! $mailbox->imap_host || ! $mailbox->imap_username || ! $mailbox->imap_password) {
            $this->error("Mailbox '{$mailbox->name}': IMAP credentials are incomplete.");

            return;
        }

        $this->line("  Host: {$mailbox->imap_host}:{$mailbox->imap_port} ({$mailbox->imap_encryption})");
        $this->line("  User: {$mailbox->imap_username}");
        $this->line("  Pass length: ".strlen($mailbox->imap_password));

        $client = Client::make([
            'host' => $mailbox->imap_host,
            'port' => $mailbox->imap_port ?: 993,
            'encryption' => $mailbox->imap_encryption ?: 'ssl',
            'validate_cert' => true,
            'username' => $mailbox->imap_username,
            'password' => $mailbox->imap_password,
            'protocol' => 'imap',
        ]);

        $client->connect();

        $folder = $client->getFolder($mailbox->imap_folder ?: 'INBOX');

        if (! $folder) {
            $this->error("Mailbox '{$mailbox->name}': Folder '{$mailbox->imap_folder}' not found.");
            $client->disconnect();

            return;
        }

        $messages = $folder->query()->unseen()->get();

        $processed = 0;
        $skipped = 0;

        foreach ($messages as $message) {
            [$wasProcessed, $wasSkipped] = $this->processMessage($message, $processor, $mailbox);
            $processed += $wasProcessed;
            $skipped += $wasSkipped;
        }

        $client->disconnect();

        $this->info("  Done. Processed: {$processed}, Skipped: {$skipped}");
    }

    private function processMessage($message, InboundEmailProcessor $processor, InboundMailbox $mailbox): array
    {
        $rawMessageId = $message->getMessageId();
        $messageId = is_object($rawMessageId) ? (string) $rawMessageId : ($rawMessageId ?: '');
        if (empty($messageId)) {
            $messageId = 'imap-'.md5((string) $message->getSubject().(string) $message->getDate());
        }

        // Idempotency check
        if (InboundEmail::where('message_id', $messageId)->exists()) {
            $message->setFlag('Seen');

            return [0, 1];
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
            if ($from) {
                $fromValues = method_exists($from, 'toArray') ? $from->toArray() : (is_array($from) ? $from : [$from]);
                if (! empty($fromValues)) {
                    $firstFrom = reset($fromValues);
                    $fromEmail = is_object($firstFrom) ? ($firstFrom->mail ?? (string) $firstFrom) : (string) $firstFrom;
                    $fromName = is_object($firstFrom) ? mb_decode_mimeheader($firstFrom->personal ?? '') : '';
                }
            }

            $inboundMessage = new InboundMessage(
                messageId: $messageId,
                fromEmail: $fromEmail,
                fromName: $fromName,
                subject: mb_decode_mimeheader((string) $message->getSubject()) ?: '(No Subject)',
                body: ((string) ($message->getHTMLBody() ?: '')) ?: ((string) ($message->getTextBody() ?: '')),
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

            $result = $processor->process($inboundMessage, $mailbox->team_id);

            $inboundEmail->update([
                'status' => $result['status'] === 'rejected' ? 'rejected' : 'processed',
                'result' => json_encode($result),
                'processed_at' => now(),
            ]);

            // Mark as seen (or delete)
            $message->setFlag('Seen');

            if ($mailbox->delete_after_process) {
                $message->delete();
            }

            $this->line("  Processed: {$inboundMessage->subject} ({$result['status']})");

            return [1, 0];

        } catch (\Throwable $e) {
            Log::error('IMAP message processing failed', [
                'message_id' => $messageId,
                'mailbox_id' => $mailbox->id,
                'error' => $e->getMessage(),
            ]);
            $this->error("  Failed: {$messageId} - {$e->getMessage()}");

            // Still mark as seen to avoid reprocessing failures
            $message->setFlag('Seen');

            return [0, 0];
        }
    }
}
