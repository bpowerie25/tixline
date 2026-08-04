<?php

return [

    'base_domain' => env('TENANT_BASE_DOMAIN'),

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

    ],

];
