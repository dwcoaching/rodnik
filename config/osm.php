<?php

declare(strict_types=1);

return [
    'oauth' => [
        'client_id' => env('OSM_CLIENT_ID', ''),
        'client_secret' => env('OSM_CLIENT_SECRET', ''),
        'redirect_uri' => env('OSM_REDIRECT_URI', 'http://localhost/osm/callback'),
        'auth_url' => env('OSM_AUTH_URL', 'https://www.openstreetmap.org/oauth2/authorize'),
        'token_url' => env('OSM_TOKEN_URL', 'https://www.openstreetmap.org/oauth2/token'),
        'scope' => env('OSM_SCOPE', 'read_prefs write_api write_notes'),
    ],
    'api' => [
        'base_url' => env('OSM_API_BASE_URL', 'https://api.openstreetmap.org/api/0.6'),
        'user_details_url' => env('OSM_API_BASE_URL', 'https://api.openstreetmap.org/api/0.6').'/user/details',
    ],
];
