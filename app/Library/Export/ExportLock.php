<?php

declare(strict_types=1);

namespace App\Library\Export;

use Closure;
use Illuminate\Support\Facades\Cache;

final class ExportLock
{
    public const NAME = 'contribution-exports';

    public const SECONDS = 3600;

    public const WAIT_SECONDS = 10;

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(Closure $callback): mixed
    {
        return Cache::lock(self::NAME, self::SECONDS)
            ->block(self::WAIT_SECONDS, $callback);
    }
}
