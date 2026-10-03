<?php

declare(strict_types=1);

return [
    // Relative paths are resolved from the project root. Replace the file in
    // place, or configure a different path without changing application code.
    'path' => env('GEOCODING_PATH', resource_path('geocoding/countries.geojson')),
    'cities_path' => env('GEOCODING_CITIES_PATH', resource_path('geocoding/settlements.txt')),
    'admin1_path' => env('GEOCODING_ADMIN1_PATH', resource_path('geocoding/admin1CodesASCII.txt')),
    'regions_path' => env('GEOCODING_REGIONS_PATH', resource_path('geocoding/regions.geojson')),
    'index_cache_path' => storage_path('framework/cache/geocoding'),
    'reference_path' => env('GEOCODING_REFERENCE_PATH', resource_path('geocoding/rodnik-with-countries.json')),

    // Apply this separately maintained country-label policy after the base
    // geography and nearest-settlement lookup, regardless of their provider.
    'russia_border_path' => env('GEOCODING_RUSSIA_BORDER_PATH', resource_path('geocoding/russia-claimed-borders.geojson')),

    // Country comes from containment; nearest city uses great-circle distance.
    'city_scope' => env('GEOCODING_CITY_SCOPE', 'global'),
    'city_country_aliases' => [
        'FR' => ['GF', 'GP', 'MQ', 'RE'],
        'NL' => ['BQ'],
        'NO' => ['SJ'],
    ],

    // A pre-geocoded source export is an exact-coordinate cache, not a city
    // database. Enable nearby reference points only if approximate labels are
    // desired; country polygons and city points provide geographic lookups.
    'reference_max_distance_km' => (float) env('GEOCODING_REFERENCE_MAX_DISTANCE_KM', 0),

    'queue_connection' => env('GEOCODING_QUEUE_CONNECTION', 'database'),
    'queue' => env('GEOCODING_QUEUE', 'geocoding'),
    'chunk_size' => 200,
];
