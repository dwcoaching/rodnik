<?php

declare(strict_types=1);

return [
    'root' => env('SOURCE_RASTER_PATH', storage_path(app()->runningUnitTests()
        ? 'framework/testing/source-raster-'.getmypid()
        : 'app/source-raster')),
    'compression' => 3,
    'retention_hours' => 24,
    'max_generations' => 6,
    'retention_grace_minutes' => 10,

    // Selected raster-lab profile v3 (docs/development/source-raster-profile.json).
    // Each color is [diameter in CSS pixels, opacity]. Tiles are drawn at 2x.
    'style' => [
        'order' => ['blue', 'yellow', 'green', 'red'],
        'zooms' => [
            0 => ['blue' => [0.4, 0.005], 'yellow' => [1, 0.5], 'green' => [1, 0.5], 'red' => [1, 0.5]],
            1 => ['blue' => [0.5, 0.005], 'yellow' => [1, 0.5], 'green' => [1, 0.5], 'red' => [1, 0.5]],
            2 => ['blue' => [1, 0.01], 'yellow' => [1.5, 0.5], 'green' => [1.5, 0.5], 'red' => [1.5, 0.5]],
            3 => ['blue' => [1, 0.03], 'yellow' => [1.5, 0.5], 'green' => [1.5, 0.5], 'red' => [1.5, 0.5]],
            4 => ['blue' => [1.1, 0.1], 'yellow' => [1.5, 0.5], 'green' => [1.5, 0.5], 'red' => [1.5, 0.5]],
            5 => ['blue' => [1.3, 0.2], 'yellow' => [2, 0.5], 'green' => [2, 0.5], 'red' => [2, 0.5]],
            6 => ['blue' => [1.5, 0.3], 'yellow' => [2.5, 0.5], 'green' => [2.5, 0.5], 'red' => [2.5, 0.5]],
            7 => ['blue' => [2, 0.4], 'yellow' => [3, 0.5], 'green' => [3, 0.5], 'red' => [3, 0.5]],
            8 => ['blue' => [2.5, 0.5], 'yellow' => [4, 0.5], 'green' => [4, 0.5], 'red' => [4, 0.5]],
            9 => ['blue' => [3, 0.5], 'yellow' => [5, 0.5], 'green' => [5, 0.5], 'red' => [5, 0.5]],
            10 => ['blue' => [4, 0.5], 'yellow' => [6, 0.5], 'green' => [6, 0.5], 'red' => [6, 0.5]],
        ],
    ],
];
