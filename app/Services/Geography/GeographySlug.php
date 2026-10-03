<?php

declare(strict_types=1);

namespace App\Services\Geography;

use Illuminate\Support\Str;

final class GeographySlug
{
    public static function make(string $name, string $identifier): string
    {
        $label = Str::slug($name) ?: 'place';
        $suffix = Str::slug($identifier) ?: mb_substr(hash('sha256', $identifier), 0, 16);

        return mb_substr($label, 0, 180).'-'.$suffix;
    }
}
