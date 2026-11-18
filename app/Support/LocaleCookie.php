<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\Cookie;

final class LocaleCookie
{
    public static function make(string $locale): Cookie
    {
        return cookie(
            name: config('localization.cookie'),
            value: $locale,
            minutes: config('localization.cookie_minutes'),
            path: '/',
            secure: null,
            httpOnly: true,
            sameSite: 'lax',
        );
    }
}
