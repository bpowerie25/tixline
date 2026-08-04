<?php

namespace App\Console\Commands;

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

        $parsed = $this->parseRawEmail($raw);

        $result = $processor->process(
            fromEmail: $parsed['from_email'],
            fromName: $parsed['from_name'],
            subject: $parsed['subject'],
            body: $parsed['body'],
            headers: $parsed['headers'],
        );

        $this->line(json_encode($result));

        return self::SUCCESS;
    }

    protected function parseRawEmail(string $raw): array
    {
        // Split headers and body
        $parts = preg_split('/\r?\n\r?\n/', $raw, 2);
        $headerBlock = $parts[0] ?? '';
        $rawBody = $parts[1] ?? '';

        // Parse headers
        $headers = [];
        $currentHeader = '';
        foreach (explode("\n", $headerBlock) as $line) {
            if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)/', $line, $m)) {
                $currentHeader = strtolower($m[1]);
                $headers[$currentHeader] = trim($m[2]);
            } elseif ($currentHeader && preg_match('/^\s+(.+)/', $line, $m)) {
                $headers[$currentHeader] .= ' ' . trim($m[1]);
            }
        }

        // Extract from
        $from = $headers['from'] ?? '';
        $fromEmail = '';
        $fromName = '';
        if (preg_match('/<?([^<>\s]+@[^<>\s]+)>?/', $from, $m)) {
            $fromEmail = $m[1];
        }
        if (preg_match('/^"?([^"<]+)"?\s*</', $from, $m)) {
            $fromName = trim($m[1]);
        }

        // Extract subject
        $subject = $headers['subject'] ?? '(No Subject)';
        // Decode MIME encoded words
        $subject = mb_decode_mimeheader($subject);

        // Handle body — prefer text/plain, strip signatures
        $body = $this->extractBody($rawBody, $headers['content-type'] ?? '');

        return [
            'from_email' => $fromEmail,
            'from_name' => $fromName ?: $fromEmail,
            'subject' => $subject,
            'body' => $body,
            'headers' => $headers,
        ];
    }

    protected function extractBody(string $rawBody, string $contentType): string
    {
        // Handle multipart
        if (str_contains($contentType, 'multipart/')) {
            preg_match('/boundary="?([^";\s]+)"?/', $contentType, $m);
            $boundary = $m[1] ?? '';

            if ($boundary) {
                $parts = explode("--{$boundary}", $rawBody);
                $textPart = '';
                $htmlPart = '';

                foreach ($parts as $part) {
                    if (str_contains($part, 'Content-Type: text/plain')) {
                        $textPart = preg_split('/\r?\n\r?\n/', $part, 2)[1] ?? '';
                    } elseif (str_contains($part, 'Content-Type: text/html')) {
                        $htmlPart = preg_split('/\r?\n\r?\n/', $part, 2)[1] ?? '';
                    }
                }

                // Prefer HTML for richer display, fall back to plain text
                return $htmlPart ?: nl2br(htmlspecialchars(trim($textPart)));
            }
        }

        // Handle transfer encoding
        if (str_contains($contentType, 'text/html')) {
            return trim($rawBody);
        }

        // Plain text
        return nl2br(htmlspecialchars(trim($rawBody)));
    }
}
