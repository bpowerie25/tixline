<?php

namespace App\Services;

use Mews\Purifier\Facades\Purifier;

class HtmlSanitizer
{
    /**
     * Sanitize HTML for safe rendering in email body/comment views.
     *
     * Uses HTMLPurifier with a conservative allowlist:
     * no script, no style, no iframe, no object/embed, no event handlers,
     * no javascript:/data: URIs. Keeps the original body intact in the DB —
     * this is called on render, not on storage.
     */
    public static function sanitize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return Purifier::clean($html, 'email_body');
    }
}
