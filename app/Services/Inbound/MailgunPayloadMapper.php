<?php

namespace App\Services\Inbound;

use Illuminate\Http\Request;

/**
 * Translates a Mailgun forwarded message into the canonical inbound payload
 * that InboundMessage::fromWebhookPayload() already understands, so the rest
 * of the pipeline stays provider-agnostic.
 */
class MailgunPayloadMapper
{
    public function map(Request $request): array
    {
        $headers = $this->headers($request);

        return [
            'message_id' => $this->messageId($request, $headers),
            'from_email' => $this->senderAddress($request),
            'from_name' => $this->senderName($request),
            'subject' => (string) $request->input('subject', ''),
            'body' => $this->body($request),
            'headers' => $headers,
            'attachments' => $this->attachments($request),
            'recipient' => (string) $request->input('recipient', ''),
            // Mailgun reports its own verdicts; InboundMessage reads these
            // top-level keys before falling back to Authentication-Results.
            'spf' => $this->verdict($headers, 'x-mailgun-spf'),
            'dkim' => $this->verdict($headers, 'x-mailgun-dkim-check-result'),
        ];
    }

    /**
     * Every recipient the message could have been addressed to, most
     * authoritative first. Mailgun's "recipient" is the address its route
     * actually matched, so it is trusted ahead of headers the sender controls.
     */
    public function recipientCandidates(Request $request): array
    {
        $headers = $this->headers($request);

        return array_values(array_filter([
            (string) $request->input('recipient', ''),
            $headers['delivered-to'] ?? '',
            $headers['x-original-to'] ?? '',
            (string) $request->input('to', ''),
            $headers['to'] ?? '',
            $headers['cc'] ?? '',
        ]));
    }

    /**
     * Mailgun sends headers as a JSON array of [name, value] pairs.
     */
    protected function headers(Request $request): array
    {
        $raw = $request->input('message-headers');

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        $headers = [];

        foreach (is_array($raw) ? $raw : [] as $pair) {
            if (is_array($pair) && count($pair) >= 2) {
                $headers[strtolower((string) $pair[0])] = is_array($pair[1])
                    ? implode(', ', $pair[1])
                    : (string) $pair[1];
            }
        }

        return $headers;
    }

    protected function messageId(Request $request, array $headers): string
    {
        $id = (string) ($request->input('Message-Id') ?: $request->input('message-id') ?: ($headers['message-id'] ?? ''));

        return trim($id, '<> ');
    }

    protected function senderAddress(Request $request): string
    {
        $sender = (string) ($request->input('sender') ?: $request->input('from', ''));

        if (preg_match('/<([^<>]+@[^<>]+)>/', $sender, $m)) {
            return strtolower(trim($m[1]));
        }

        return strtolower(trim($sender));
    }

    protected function senderName(Request $request): string
    {
        $from = (string) $request->input('from', '');

        if (preg_match('/^\s*"?([^"<]+?)"?\s*</', $from, $m)) {
            return trim($m[1]);
        }

        return $this->senderAddress($request);
    }

    protected function body(Request $request): string
    {
        foreach (['body-html', 'stripped-html', 'body-plain', 'stripped-text'] as $field) {
            $body = (string) $request->input($field, '');

            if (trim($body) !== '') {
                return $body;
            }
        }

        return '';
    }

    /**
     * Mailgun posts attachments as uploaded files named attachment-1..N.
     */
    protected function attachments(Request $request): array
    {
        $attachments = [];

        foreach ($request->allFiles() as $key => $file) {
            if (! str_starts_with((string) $key, 'attachment')) {
                continue;
            }

            foreach (is_array($file) ? $file : [$file] as $upload) {
                if (! $upload || ! $upload->isValid()) {
                    continue;
                }

                $attachments[] = [
                    'filename' => $upload->getClientOriginalName(),
                    'content' => base64_encode(file_get_contents($upload->getRealPath())),
                    'content_type' => $upload->getClientMimeType(),
                ];
            }
        }

        return $attachments;
    }

    /**
     * Mailgun writes "Pass"/"Fail"/"Neutral"; the pipeline expects lowercase
     * pass/fail wording consistent with Authentication-Results.
     */
    protected function verdict(array $headers, string $header): ?string
    {
        $value = strtolower(trim((string) ($headers[$header] ?? '')));

        if ($value === '') {
            return null;
        }

        return str_contains($value, 'pass') ? 'pass' : $value;
    }
}
