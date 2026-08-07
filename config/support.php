<?php

return [

    'multi_tenant' => (bool) env('MULTI_TENANT', false),

    'base_domain' => env('TENANT_BASE_DOMAIN'),

    'inbound' => [
        'webhook_secret' => env('INBOUND_WEBHOOK_SECRET'),
        'payload_retention_days' => (int) env('INBOUND_PAYLOAD_RETENTION_DAYS', 30),

        /*
        |----------------------------------------------------------------------
        | Require email authentication (DKIM/SPF/DMARC) for reference matching
        |----------------------------------------------------------------------
        |
        | When true, a [TKT-n] reference match is only honoured when the
        | inbound message has passing DKIM, SPF, or DMARC results. Messages
        | with failing or absent auth results fall through to create a new
        | ticket. This prevents From-header spoofing attacks.
        |
        | The pipe path (Postfix → artisan) has no provider auth results.
        | When this is true, pipe-delivered messages with a [TKT-n] reference
        | are only matched if the sender is the original requester — the
        | agent-address branch is never granted without authentication.
        |
        | Set to false only if your MTA performs its own authentication
        | and you trust the pipe path unconditionally.
        |
        */
        'require_auth_results' => (bool) env('INBOUND_REQUIRE_AUTH_RESULTS', true),
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

        'learned_threshold' => (float) env('SPAM_LEARNED_THRESHOLD', 8.0),

        'log_only' => (bool) env('SPAM_LOG_ONLY', false),

    ],

];
