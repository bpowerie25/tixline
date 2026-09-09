<?php

namespace App\Support;

class TextScrubber
{
    /** @var list<string> */
    protected array $names = [];

    /** @var list<string> */
    protected array $domains = [];

    /** @var list<array{pattern: string, token: string}> */
    protected array $extraPatterns = [];

    /**
     * @param  list<string>  $names
     */
    public function setNames(array $names): static
    {
        $this->names = $names;

        return $this;
    }

    /**
     * @param  list<string>  $domains
     */
    public function setDomains(array $domains): static
    {
        $this->domains = $domains;

        return $this;
    }

    /**
     * @param  list<array{pattern: string, token: string}>  $extraPatterns
     */
    public function setExtraPatterns(array $extraPatterns): static
    {
        $this->extraPatterns = $extraPatterns;

        return $this;
    }

    public function cleanBody(string $text): string
    {
        $text = $this->htmlToPlainText($text);
        $text = $this->stripQuotedReplies($text);
        $text = $this->stripSignatures($text);
        $text = $this->redactPii($text);
        $text = $this->redactNames($text);
        $text = $this->applyExtraPatterns($text);

        return trim($text);
    }

    public function cleanSubject(string $subject): string
    {
        $subject = $this->htmlToPlainText($subject);
        $subject = $this->stripSubjectPrefixes($subject);
        $subject = $this->redactPii($subject);
        $subject = $this->redactNames($subject);
        $subject = $this->applyExtraPatterns($subject);

        return trim($subject);
    }

    // -------------------------------------------------------------------------
    // Step 1: HTML → plain text
    // -------------------------------------------------------------------------

    public function htmlToPlainText(string $text): string
    {
        // Replace <br> and block-level closing tags with newlines
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/(p|div|tr|li|h[1-6])>/i', "\n", $text);

        // Strip remaining tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalise whitespace: collapse runs of spaces/tabs on each line, trim trailing
        $lines = explode("\n", $text);
        $lines = array_map(fn ($line) => preg_replace('/[ \t]+/', ' ', trim($line)), $lines);
        $text = implode("\n", $lines);

        // Collapse 3+ consecutive newlines to 2
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    // -------------------------------------------------------------------------
    // Step 2: Strip quoted replies
    // -------------------------------------------------------------------------

    public function stripQuotedReplies(string $text): string
    {
        $lines = explode("\n", $text);
        $output = [];

        foreach ($lines as $line) {
            // "On ... wrote:" pattern (Gmail-style)
            if (preg_match('/^On .+ wrote:\s*$/i', $line)) {
                break;
            }

            // "-----Original Message-----" (Outlook)
            if (preg_match('/^-{3,}\s*Original Message\s*-{3,}/i', $line)) {
                break;
            }

            // "From: ... Sent: ..." (Outlook variant)
            if (preg_match('/^From:\s.+Sent:\s/i', $line)) {
                break;
            }

            // "Le ... a écrit" (French Gmail)
            if (preg_match('/^Le .+ a écrit\s*:?\s*$/i', $line)) {
                break;
            }

            // Skip lines starting with ">"
            if (str_starts_with(ltrim($line), '>')) {
                continue;
            }

            $output[] = $line;
        }

        return implode("\n", $output);
    }

    // -------------------------------------------------------------------------
    // Step 3: Strip signatures
    // -------------------------------------------------------------------------

    public function stripSignatures(string $text): string
    {
        $lines = explode("\n", $text);
        $output = [];

        foreach ($lines as $i => $line) {
            // Standard sig delimiter: "-- " (with trailing space) or "--" alone on a line
            if (preg_match('/^--\s*$/', $line)) {
                break;
            }

            // "Sent from my ..."
            if (preg_match('/^Sent from my /i', trim($line))) {
                break;
            }

            // Common sign-offs followed by a short trailing block (≤ 5 lines remaining)
            $remaining = count($lines) - $i;
            if ($remaining <= 6) {
                $trimmed = trim($line);
                if (preg_match('/^(Regards|Kind regards|Best regards|Thanks|Thank you|Cheers|Best|Many thanks|Warm regards),?\s*$/i', $trimmed)) {
                    break;
                }
            }

            $output[] = $line;
        }

        return implode("\n", $output);
    }

    // -------------------------------------------------------------------------
    // Step 4: Redact PII tokens
    // -------------------------------------------------------------------------

    public function redactPii(string $text): string
    {
        // Emails
        $text = preg_replace('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', '[EMAIL]', $text);

        // URLs (http/https/ftp, or www.)
        $text = preg_replace('#(?:https?|ftp)://[^\s<>\"\')\]]+#i', '[URL]', $text);
        $text = preg_replace('#\bwww\.[^\s<>\"\')\]]+#i', '[URL]', $text);

        // Configured domains
        foreach ($this->domains as $domain) {
            $text = preg_replace('/\b' . preg_quote($domain, '/') . '\b/i', '[URL]', $text);
        }

        // IBANs: 2 letter country code + 2 check digits + up to 30 alphanumeric
        $text = preg_replace('/\b[A-Z]{2}\d{2}[\s]?[\dA-Z]{4}[\s]?(?:[\dA-Z]{4}[\s]?){1,7}[\dA-Z]{1,4}\b/', '[IBAN]', $text);

        // Card-like: 13-19 digit sequences (possibly separated by spaces or dashes)
        $text = preg_replace('/\b(?:\d[\s\-]?){13,19}\b/', '[CARD]', $text);

        // Phone numbers — Irish formats first, then international/general
        // Irish mobile: 08x xxx xxxx or +353 8x xxx xxxx
        $text = preg_replace('/\+353[\s\-]?\d{1,2}[\s\-]?\d{3}[\s\-]?\d{4}\b/', '[PHONE]', $text);
        $text = preg_replace('/\b0[1-9]\d[\s\-]?\d{3}[\s\-]?\d{4}\b/', '[PHONE]', $text);

        // International: +<country code> then 7-14 digits (with optional spaces/dashes)
        $text = preg_replace('/\+\d{1,3}[\s\-]?(?:\(?\d{1,4}\)?[\s\-]?){1,4}\d{2,4}\b/', '[PHONE]', $text);

        // General: sequences of 7+ digits with optional separators that look like phone numbers
        $text = preg_replace('/\b(?:\d[\s\-]?){7,15}\b/', '[PHONE]', $text);

        return $text;
    }

    // -------------------------------------------------------------------------
    // Step 5: Redact known names
    // -------------------------------------------------------------------------

    public function redactNames(string $text): string
    {
        if (empty($this->names)) {
            return $text;
        }

        // Sort by length descending so longer names are matched first
        $sorted = $this->names;
        usort($sorted, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($sorted as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $escaped = preg_quote($name, '/');
            $text = preg_replace('/\b' . $escaped . '\b/i', '[NAME]', $text);
        }

        return $text;
    }

    // -------------------------------------------------------------------------
    // Step 6: Strip subject prefixes
    // -------------------------------------------------------------------------

    public function stripSubjectPrefixes(string $subject): string
    {
        return preg_replace('/^(?:(?:Re|Fwd|FW)\s*:\s*)+/i', '', $subject);
    }

    // -------------------------------------------------------------------------
    // Extra patterns from config
    // -------------------------------------------------------------------------

    protected function applyExtraPatterns(string $text): string
    {
        foreach ($this->extraPatterns as $entry) {
            $text = preg_replace($entry['pattern'], $entry['token'], $text);
        }

        return $text;
    }
}
