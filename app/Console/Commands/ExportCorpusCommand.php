<?php

namespace App\Console\Commands;

use App\Models\Label;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TextScrubber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportCorpusCommand extends Command
{
    protected $signature = 'tickets:export-corpus
        {--out=storage/app/exports/corpus.jsonl : Output file path}
        {--since= : Only tickets created on or after this date (Y-m-d)}
        {--until= : Only tickets created on or before this date (Y-m-d)}
        {--include-replies : Include all customer messages, not just the first}
        {--salt= : HMAC salt for requester hashing}';

    protected $description = 'Export a pseudonymised ticket corpus (JSONL) for external topic analysis';

    public function handle(): int
    {
        $salt = $this->resolveSalt();
        $scrubber = $this->buildScrubber();
        $includeReplies = $this->option('include-replies');

        $outPath = $this->option('out');
        File::ensureDirectoryExists(dirname($outPath));

        $query = Ticket::query()
            ->withoutGlobalScopes()
            ->select(['id', 'subject', 'body', 'requester_email', 'status', 'source', 'created_at'])
            ->with(['labels:id,name']);

        if ($this->option('since')) {
            $query->where('created_at', '>=', $this->option('since'));
        }
        if ($this->option('until')) {
            $query->where('created_at', '<=', $this->option('until').' 23:59:59');
        }

        $handle = fopen($outPath, 'w');
        if (! $handle) {
            $this->error("Cannot open {$outPath} for writing.");

            return self::FAILURE;
        }

        $rowCount = 0;
        $minDate = null;
        $maxDate = null;
        $statuses = [];
        $channels = [];

        $query->chunkById(500, function ($tickets) use (
            $handle, $scrubber, $salt, $includeReplies,
            &$rowCount, &$minDate, &$maxDate, &$statuses, &$channels
        ) {
            foreach ($tickets as $ticket) {
                $hash = $this->hashRequester($ticket->requester_email, $salt);
                $tags = $ticket->labels->pluck('name')->values()->all();
                $created = $ticket->created_at->toIso8601String();

                $statuses[$ticket->status] = true;
                $channels[$ticket->source] = true;
                $minDate = $minDate === null ? $created : min($minDate, $created);
                $maxDate = $maxDate === null ? $created : max($maxDate, $created);

                if (! $includeReplies) {
                    $row = [
                        'ticket_id' => $ticket->id,
                        'created_at' => $created,
                        'status' => $ticket->status,
                        'channel' => $ticket->source,
                        'subject' => $scrubber->cleanSubject($ticket->subject ?? ''),
                        'body' => $scrubber->cleanBody($ticket->body ?? ''),
                        'requester_hash' => $hash,
                        'tags' => $tags,
                    ];
                    fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE)."\n");
                    $rowCount++;
                } else {
                    // First message is the ticket body itself
                    $messageIndex = 0;
                    $row = [
                        'ticket_id' => $ticket->id,
                        'created_at' => $created,
                        'status' => $ticket->status,
                        'channel' => $ticket->source,
                        'subject' => $scrubber->cleanSubject($ticket->subject ?? ''),
                        'body' => $scrubber->cleanBody($ticket->body ?? ''),
                        'requester_hash' => $hash,
                        'tags' => $tags,
                        'message_index' => $messageIndex,
                    ];
                    fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE)."\n");
                    $rowCount++;

                    // Customer replies: comments with no user_id (customer) and type=reply
                    $comments = $ticket->comments()
                        ->whereNull('user_id')
                        ->where('type', 'reply')
                        ->where('is_internal', false)
                        ->orderBy('created_at')
                        ->get(['body', 'created_at']);

                    foreach ($comments as $comment) {
                        $messageIndex++;
                        $row = [
                            'ticket_id' => $ticket->id,
                            'created_at' => $comment->created_at->toIso8601String(),
                            'status' => $ticket->status,
                            'channel' => $ticket->source,
                            'subject' => $scrubber->cleanSubject($ticket->subject ?? ''),
                            'body' => $scrubber->cleanBody($comment->body ?? ''),
                            'requester_hash' => $hash,
                            'tags' => $tags,
                            'message_index' => $messageIndex,
                        ];
                        fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE)."\n");
                        $rowCount++;
                    }
                }
            }
        });

        fclose($handle);

        $this->writeReadme($outPath, $rowCount, $statuses, $channels, $minDate, $maxDate);

        $this->info("Exported {$rowCount} rows to {$outPath}");

        return self::SUCCESS;
    }

    protected function resolveSalt(): string
    {
        $salt = $this->option('salt') ?: env('CORPUS_SALT');

        if (! $salt) {
            $salt = bin2hex(random_bytes(16));
            $this->warn("No salt provided (--salt or CORPUS_SALT env). Generated random salt for this run — requester hashes will not be reproducible.");
        }

        return $salt;
    }

    protected function buildScrubber(): TextScrubber
    {
        $scrubber = new TextScrubber;

        // Collect agent/user display names
        $userNames = User::withoutGlobalScopes()
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $configNames = config('corpus.names', []);
        $scrubber->setNames(array_merge($userNames, $configNames));
        $scrubber->setDomains(config('corpus.domains', []));
        $scrubber->setExtraPatterns(config('corpus.extra_patterns', []));

        return $scrubber;
    }

    protected function hashRequester(?string $email, string $salt): string
    {
        $email = mb_strtolower(trim($email ?? ''));

        return substr(hash_hmac('sha256', $email, $salt), 0, 16);
    }

    protected function writeReadme(
        string $outPath,
        int $rowCount,
        array $statuses,
        array $channels,
        ?string $minDate,
        ?string $maxDate
    ): void {
        $dir = dirname($outPath);
        $statusList = implode(', ', array_keys($statuses));
        $channelList = implode(', ', array_keys($channels));

        $readme = <<<MD
        # Corpus Export — Data Dictionary

        **Generated:** {$this->nowIso()}
        **Row count:** {$rowCount}
        **Date range:** {$minDate} → {$maxDate}

        ## Fields

        | Field | Type | Description |
        |-------|------|-------------|
        | ticket_id | integer | Internal ticket primary key |
        | created_at | string | ISO 8601 timestamp of the ticket (or message) creation |
        | status | string | Ticket status at export time. Values: {$statusList} |
        | channel | string | Ticket source channel. Values: {$channelList} |
        | subject | string | Cleaned, redacted ticket subject |
        | body | string | Cleaned, redacted message body (plain text) |
        | requester_hash | string | 16-char hex HMAC-SHA256 of the requester email (see below) |
        | tags | array | Label names attached to the ticket |
        | message_index | integer | *(only with --include-replies)* 0-based index of the customer message within the ticket |

        ## Redaction tokens

        | Token | What was redacted |
        |-------|-------------------|
        | [EMAIL] | Email addresses |
        | [PHONE] | Phone numbers (Irish, international, general) |
        | [IBAN] | IBAN bank account numbers |
        | [URL] | URLs and configured domains |
        | [CARD] | Credit/debit card-like digit sequences (13-19 digits) |
        | [NAME] | Known agent/user names and configured proper names |

        ## Requester hash

        `requester_hash` is the first 16 hex characters of `HMAC-SHA256(lowercase_trimmed_email, salt)`.
        The salt is provided at export time and **not** stored in the export file.
        This means:
        - The hash is **not reversible** — you cannot recover the email address.
        - The hash is **consistent within a single export** — the same email always produces the same hash.
        - The hash is **not joinable across exports** unless the same salt is reused.

        ## Text cleaning pipeline

        1. HTML → plain text (strip tags, decode entities, normalise whitespace)
        2. Strip quoted replies (Gmail "On … wrote:", Outlook "Original Message", French "a écrit", ">" prefixed lines)
        3. Strip signatures ("-- " delimiter, "Sent from my …", common sign-offs)
        4. Redact PII with tokens (see table above)
        5. Redact known names from the users table and config/corpus.php

        ## Notes

        - Only customer messages are included; agent replies, internal notes, and system messages are excluded.
        - Attachments and raw webhook payloads are never included.
        - No user accounts, authentication data, or personally identifiable information is included.
        MD;

        File::put("{$dir}/README.md", $readme);
    }

    protected function nowIso(): string
    {
        return now()->toIso8601String();
    }
}
