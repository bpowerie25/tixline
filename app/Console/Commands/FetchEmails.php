<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\SpamFilter;
use App\Services\WorkflowEngine;
use Illuminate\Console\Command;
use PhpImap\Mailbox;

class FetchEmails extends Command
{
    protected $signature = 'support:fetch-emails';
    protected $description = 'Fetch emails via IMAP and create tickets';

    public function handle(WorkflowEngine $engine, SpamFilter $spamFilter): int
    {
        $host = config('services.imap.host');
        $port = config('services.imap.port', 993);
        $user = config('services.imap.username');
        $pass = config('services.imap.password');
        $encryption = config('services.imap.encryption', 'ssl');

        if (!$host || !$user || !$pass) {
            $this->warn('IMAP not configured. Set IMAP_HOST, IMAP_USERNAME, IMAP_PASSWORD in .env');
            return self::SUCCESS;
        }

        $imapPath = "{{$host}:{$port}/imap/{$encryption}}INBOX";

        try {
            $mailbox = new Mailbox($imapPath, $user, $pass);
            $mailIds = $mailbox->searchMailbox('UNSEEN');
        } catch (\Exception $e) {
            $this->error("IMAP connection failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->info("Found " . count($mailIds) . " new emails.");

        foreach ($mailIds as $mailId) {
            try {
                $mail = $mailbox->getMail($mailId);

                $fromEmail = $mail->fromAddress;
                $fromName = $mail->fromName ?: $fromEmail;
                $subject = $mail->subject ?: '(No Subject)';
                $body = $mail->textHtml ?: $mail->textPlain ?: '';

                // Parse headers for spam checking
                $headers = [];
                $rawHeaders = $mail->headersRaw ?? '';
                foreach (explode("\n", $rawHeaders) as $line) {
                    if (preg_match('/^(x-spam-[^:]+):\s*(.+)/i', trim($line), $m)) {
                        $headers[strtolower($m[1])] = trim($m[2]);
                    }
                }

                // Spam filter check
                $spamReason = $spamFilter->isSpam($fromEmail, $subject, $body, $headers);
                if ($spamReason) {
                    $this->line("  Skipped (spam: {$spamReason}): {$fromEmail} — {$subject}");
                    $mailbox->markMailAsRead($mailId);
                    continue;
                }

                // Skip if ticket already exists from this email thread
                $existingTicket = Ticket::where('requester_email', $fromEmail)
                    ->where('subject', $subject)
                    ->whereIn('status', ['open', 'pending'])
                    ->first();

                if ($existingTicket) {
                    $existingTicket->comments()->create([
                        'body' => $body,
                        'type' => 'reply',
                        'is_internal' => false,
                    ]);
                    $this->line("  Added reply to {$existingTicket->reference}");
                } else {
                    $ticket = Ticket::create([
                        'subject' => $subject,
                        'body' => $body,
                        'requester_name' => $fromName,
                        'requester_email' => $fromEmail,
                        'source' => 'email',
                    ]);

                    $engine->run($ticket, 'ticket_created');

                    $this->line("  Created {$ticket->reference}: {$subject}");
                }

                $mailbox->markMailAsRead($mailId);
            } catch (\Exception $e) {
                $this->error("  Failed to process email {$mailId}: {$e->getMessage()}");
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
