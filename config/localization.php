<?php

declare(strict_types=1);

return [
    'default' => 'en',

    'supported' => [
        'en' => [
            'name' => 'English',
            'short_name' => 'EN',
        ],
        'ru' => [
            'name' => 'Русский',
            'short_name' => 'RU',
        ],
    ],

    'cookie' => 'rodnik_locale',
    'cookie_minutes' => 60 * 24 * 365 * 5,
];
