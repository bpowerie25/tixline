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
    ) {}

    public static function fromWebhookPayload(array $payload): self
    {
        return new self(
            messageId: $payload['message_id'] ?? '',
            fromEmail: $payload['from_email'] ?? '',
            fromName: $payload['from_name'] ?? $payload['from_email'] ?? '',
            subject: $payload['subject'] ?? '',
            body: $payload['body'] ?? '',
            headers: $payload['headers'] ?? [],
            attachments: $payload['attachments'] ?? [],
            timestamp: $payload['timestamp'] ?? null,
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
}
