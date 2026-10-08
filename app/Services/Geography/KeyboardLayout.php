<?php

declare(strict_types=1);

namespace App\Services\Geography;

final class KeyboardLayout
{
    // Keys in the same physical positions on QWERTY and ЙЦУКЕН, without and with Shift.
    private const LATIN = 'qwertyuiop[]asdfghjkl;\'zxcvbnm,.`QWERTYUIOP{}ASDFGHJKL:"ZXCVBNM<>~';

    private const CYRILLIC = 'йцукенгшщзхъфывапролджэячсмитьбюёЙЦУКЕНГШЩЗХЪФЫВАПРОЛДЖЭЯЧСМИТЬБЮЁ';

    /**
     * Retypes text entered with the other keyboard layout active, e.g. "Vjcrdf" -> "Москва".
     * Returns null when the letters do not all come from one of the two layouts.
     */
    public static function swap(string $text): ?string
    {
        $maps = ['latin' => array_combine(mb_str_split(self::LATIN), mb_str_split(self::CYRILLIC))];
        $maps['cyrillic'] = array_flip($maps['latin']);
        $layout = null;

        foreach (mb_str_split($text) as $character) {
            if (preg_match('/\p{L}/u', $character) !== 1) {
                continue;
            }

            $letterLayout = match (true) {
                isset($maps['latin'][$character]) => 'latin',
                isset($maps['cyrillic'][$character]) => 'cyrillic',
                default => null,
            };

            if ($letterLayout === null || ($layout !== null && $layout !== $letterLayout)) {
                return null;
            }

            $layout = $letterLayout;
        }

        if ($layout === null) {
            return null;
        }

        $swapped = strtr($text, $maps[$layout]);

        return $swapped === $text ? null : $swapped;
    }
}
