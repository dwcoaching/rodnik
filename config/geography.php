<?php

declare(strict_types=1);

return [
    'country_info_path' => env('GEOGRAPHY_COUNTRY_INFO_PATH', resource_path('geocoding/countryInfo.txt')),
    'russian_names_path' => env('GEOGRAPHY_RUSSIAN_NAMES_PATH', resource_path('geocoding/russian-names.tsv')),
    'names_manifest_path' => resource_path('geocoding/russian-names.manifest.json'),
    'import_batch_size' => 1000,

    // Location pages list shorter lists in full and paginate longer ones.
    'list_limit' => 5000,
    'page_size' => 1000,
    'cache_seconds' => 600,
];
