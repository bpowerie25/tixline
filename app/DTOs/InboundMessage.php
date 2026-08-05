<?php

namespace App\DTOs;

class InboundMessage
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $fromEmail,
        public readonly string $fromName,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $headers = [],
        public readonly array $attachments = [],
        public readonly ?int $timestamp = null,
        public readonly ?array $authResults = null,
    ) {}

    public static function fromWebhookPayload(array $payload): self
    {
        $headers = $payload['headers'] ?? [];

        return new self(
            messageId: $payload['message_id'] ?? '',
            fromEmail: $payload['from_email'] ?? '',
            fromName: $payload['from_name'] ?? $payload['from_email'] ?? '',
            subject: $payload['subject'] ?? '',
            body: $payload['body'] ?? '',
            headers: $headers,
            attachments: $payload['attachments'] ?? [],
            timestamp: $payload['timestamp'] ?? null,
            authResults: self::extractAuthResults($payload, $headers),
        );
    }

    public static function fromRawEmail(string $raw, string $messageId = ''): self
    {
        $parts = preg_split('/\r?\n\r?\n/', $raw, 2);
        $headerBlock = $parts[0] ?? '';
        $rawBody = $parts[1] ?? '';

        $headers = self::parseHeaders($headerBlock);

        $from = $headers['from'] ?? '';
        $fromEmail = '';
        $fromName = '';
        if (preg_match('/<?([^<>\s]+@[^<>\s]+)>?/', $from, $m)) {
            $fromEmail = $m[1];
        }
        if (preg_match('/^"?([^"<]+)"?\s*</', $from, $m)) {
            $fromName = trim($m[1]);
        }

        $subject = $headers['subject'] ?? '(No Subject)';
        $subject = mb_decode_mimeheader($subject);

        $body = self::extractBody($rawBody, $headers['content-type'] ?? '');

        $resolvedId = $messageId ?: ($headers['message-id'] ?? uniqid('raw-', true));
        // Clean angle brackets from Message-ID
        $resolvedId = trim($resolvedId, '<> ');

        return new self(
            messageId: $resolvedId,
            fromEmail: $fromEmail,
            fromName: $fromName ?: $fromEmail,
            subject: $subject,
            body: $body,
            headers: $headers,
            authResults: self::extractAuthResults([], $headers),
        );
    }

    protected static function parseHeaders(string $headerBlock): array
    {
        $headers = [];
        $currentHeader = '';
        foreach (explode("\n", $headerBlock) as $line) {
            if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)/', $line, $m)) {
                $currentHeader = strtolower($m[1]);
                $headers[$currentHeader] = trim($m[2]);
            } elseif ($currentHeader && preg_match('/^\s+(.+)/', $line, $m)) {
                $headers[$currentHeader] .= ' '.trim($m[1]);
            }
        }

        return $headers;
    }

    protected static function extractBody(string $rawBody, string $contentType): string
    {
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

                return $htmlPart ?: nl2br(htmlspecialchars(trim($textPart)));
            }
        }

        if (str_contains($contentType, 'text/html')) {
            return trim($rawBody);
        }

        return nl2br(htmlspecialchars(trim($rawBody)));
    }

    /**
     * Extract email authentication verdicts from provider payload or headers.
     *
     * Providers may supply explicit fields (spf, dkim, dmarc) or an
     * Authentication-Results header. Returns normalised verdicts.
     */
    protected static function extractAuthResults(array $payload, array $headers): array
    {
        $results = [
            'spf' => null,
            'dkim' => null,
            'dmarc' => null,
        ];

        // 1. Check explicit provider fields (e.g. Mailgun, SendGrid, Postmark)
        foreach (['spf', 'dkim', 'dmarc'] as $check) {
            $value = $payload[$check] ?? $payload[strtoupper($check)] ?? null;
            if ($value !== null) {
                $results[$check] = strtolower((string) $value);
            }
        }

        // 2. Fallback: parse Authentication-Results header
        $authHeader = $headers['authentication-results'] ?? '';
        if ($authHeader && $results['spf'] === null && $results['dkim'] === null && $results['dmarc'] === null) {
            if (preg_match('/\bspf=(\w+)/i', $authHeader, $m)) {
                $results['spf'] = strtolower($m[1]);
            }
            if (preg_match('/\bdkim=(\w+)/i', $authHeader, $m)) {
                $results['dkim'] = strtolower($m[1]);
            }
            if (preg_match('/\bdmarc=(\w+)/i', $authHeader, $m)) {
                $results['dmarc'] = strtolower($m[1]);
            }
        }

        return $results;
    }

    /**
     * Whether at least one email authentication mechanism passed.
     */
    public function authPassed(): bool
    {
        if ($this->authResults === null) {
            return false;
        }

        $passing = ['pass'];

        return in_array($this->authResults['dkim'] ?? null, $passing, true)
            || in_array($this->authResults['spf'] ?? null, $passing, true)
            || in_array($this->authResults['dmarc'] ?? null, $passing, true);
    }
}
