<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Extra names to redact
    |--------------------------------------------------------------------------
    |
    | Any proper names listed here will be replaced with [NAME] during corpus
    | text cleaning, in addition to agent/user display names pulled from the
    | database at export time.
    |
    */

    'names' => [
        // 'Jane Doe',
    ],

    /*
    |--------------------------------------------------------------------------
    | Domains to redact
    |--------------------------------------------------------------------------
    |
    | Domain names (without protocol) that should be replaced with [URL] when
    | they appear in ticket text, even if they are not part of a full URL.
    |
    */

    'domains' => [
        // 'example.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extra regex patterns
    |--------------------------------------------------------------------------
    |
    | Additional regex patterns to redact. Each entry should be a valid PCRE
    | pattern. Matches are replaced with the corresponding token.
    |
    | Format: ['pattern' => '/regex/', 'token' => '[TOKEN]']
    |
    */

    'extra_patterns' => [
        // ['pattern' => '/\bACME\b/i', 'token' => '[ORG]'],
    ],

];
