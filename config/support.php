<?php

return [

    'base_domain' => env('TENANT_BASE_DOMAIN'),

    'inbound' => [
        'webhook_secret' => env('INBOUND_WEBHOOK_SECRET'),
    ],

    'attachments' => [
        'disk' => env('ATTACHMENT_DISK', 'local'),
        'max_size_bytes' => (int) env('ATTACHMENT_MAX_SIZE', 10 * 1024 * 1024), // 10MB
        'allowed_mimes' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'text/plain', 'text/csv',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'message/rfc822',
        ],
    ],

    'spam' => [

        /*
        |--------------------------------------------------------------------------
        | Domain / Email Allowlist
        |--------------------------------------------------------------------------
        |
        | If set, ONLY emails from these domains or addresses will be accepted.
        | Leave empty to accept from anyone (subject to blocklist).
        |
        | Example: ['acme.com', 'vip@gmail.com']
        |
        */
        'allowlist' => array_filter(explode(',', env('SPAM_ALLOWLIST', ''))),

        /*
        |--------------------------------------------------------------------------
        | Domain / Email Blocklist
        |--------------------------------------------------------------------------
        |
        | Emails from these domains or addresses will always be rejected.
        |
        | Example: ['spamdomain.com', 'spammer@example.com']
        |
        */
        'blocklist' => array_filter(explode(',', env('SPAM_BLOCKLIST', ''))),

        /*
        |--------------------------------------------------------------------------
        | SpamAssassin Score Threshold
        |--------------------------------------------------------------------------
        |
        | Emails with an X-Spam-Score at or above this value are rejected.
        |
        */
        'score_threshold' => (float) env('SPAM_SCORE_THRESHOLD', 5.0),

        /*
        |--------------------------------------------------------------------------
        | Rate Limit
        |--------------------------------------------------------------------------
        |
        | Maximum number of new tickets a single email address can create per hour.
        |
        */
        'max_tickets_per_hour' => (int) env('SPAM_MAX_PER_HOUR', 10),

        'log_only' => (bool) env('SPAM_LOG_ONLY', false),

    ],

];
