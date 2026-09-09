<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AuditCorpusCommand extends Command
{
    protected $signature = 'tickets:audit-corpus {file : Path to the JSONL corpus file}';

    protected $description = 'Scan an exported corpus JSONL for residual PII and report statistics';

    /** @var array<string, list<string>> */
    protected array $hits = [];

    protected int $rowCount = 0;

    protected int $emptyBodyCount = 0;

    protected ?string $minDate = null;

    protected ?string $maxDate = null;

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $knownNames = $this->loadKnownNames();

        $handle = fopen($file, 'r');

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true);
            if (! is_array($row)) {
                continue;
            }

            $this->rowCount++;

            $body = $row['body'] ?? '';
            $subject = $row['subject'] ?? '';
            $text = $subject.' '.$body;

            if (trim($body) === '') {
                $this->emptyBodyCount++;
            }

            $created = $row['created_at'] ?? null;
            if ($created) {
                $this->minDate = $this->minDate === null ? $created : min($this->minDate, $created);
                $this->maxDate = $this->maxDate === null ? $created : max($this->maxDate, $created);
            }

            // Check for residual emails
            if (preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
                foreach ($matches[0] as $match) {
                    $this->recordHit('email', $match);
                }
            }

            // Check for 7+ digit runs (potential phone/card/ID numbers)
            if (preg_match_all('/\b\d{7,}\b/', $text, $matches)) {
                foreach ($matches[0] as $match) {
                    $this->recordHit('digit_sequence', $match);
                }
            }

            // Check for IBAN patterns
            if (preg_match_all('/\b[A-Z]{2}\d{2}[\s]?[\dA-Z]{4}[\s]?(?:[\dA-Z]{4}[\s]?){1,7}[\dA-Z]{1,4}\b/', $text, $matches)) {
                foreach ($matches[0] as $match) {
                    $this->recordHit('iban', $match);
                }
            }

            // Check for known names
            foreach ($knownNames as $name) {
                if ($name && stripos($text, $name) !== false) {
                    $this->recordHit('known_name', $name);
                }
            }
        }

        fclose($handle);

        $this->printReport();

        $totalHits = array_sum(array_map('count', $this->hits));

        return $totalHits > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function recordHit(string $category, string $sample): void
    {
        $this->hits[$category] ??= [];
        $this->hits[$category][] = $sample;
    }

    protected function loadKnownNames(): array
    {
        $names = config('corpus.names', []);

        // Try to load user names if database is available
        try {
            $userNames = \App\Models\User::withoutGlobalScopes()
                ->pluck('name')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $names = array_merge($names, $userNames);
        } catch (\Throwable) {
            // Database may not be available when auditing standalone
        }

        return array_filter(array_unique($names));
    }

    protected function printReport(): void
    {
        $this->newLine();
        $this->info('=== Corpus Audit Report ===');
        $this->newLine();
        $this->line("Rows:         {$this->rowCount}");
        $this->line("Empty bodies: {$this->emptyBodyCount}");
        $this->line("Date range:   {$this->minDate} → {$this->maxDate}");
        $this->newLine();

        if (empty($this->hits)) {
            $this->info('No residual PII detected.');

            return;
        }

        $this->warn('Residual PII detected:');
        $this->newLine();

        foreach ($this->hits as $category => $samples) {
            $count = count($samples);
            $this->line("  {$category}: {$count} hit(s)");

            $shown = array_unique(array_slice($samples, 0, 5));
            foreach ($shown as $sample) {
                $this->line("    - {$sample}");
            }
        }

        $this->newLine();
    }
}
