<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'overpass' => [
        'url' => env('OVERPASS_API_URL', 'https://maps.mail.ru/osm/tools/overpass/api/interpreter'),
        'status_url' => env('OVERPASS_STATUS_URL', 'https://maps.mail.ru/osm/tools/overpass/api/status'),

        // Pacing, tuned for maps.mail.ru: it has no per-IP quota (`Rate limit: 0`) and turns away
        // about half of all queries at random however long we wait in between, so waiting only
        // costs time. For overpass-api.de use 30 / 0 / true.
        'minimum_cycle_seconds' => (int) env('OVERPASS_MINIMUM_CYCLE_SECONDS', 0),
        'tolerated_congestion' => (int) env('OVERPASS_TOLERATED_CONGESTION', 4),
        'consult_status' => (bool) env('OVERPASS_CONSULT_STATUS', false),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'telegram-bot-api' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'channel_id' => env('TELEGRAM_CHANNEL_ID'),
    ],

];
