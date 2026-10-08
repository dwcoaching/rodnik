<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class PageNumber
{
    // Far beyond any real list, and small enough that page × page size stays an integer SQL offset.
    public const MAX = 1_000_000;

    /** The requested page of a paginated list; anything but a page number from 1 to MAX is not found. */
    public static function from(Request $request, string $name = 'page'): int
    {
        $page = $request->query($name, '1');
        abort_unless(is_string($page) && ctype_digit($page), 404);
        $page = filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX]]);
        abort_if($page === false, 404);

        return $page;
    }
}
