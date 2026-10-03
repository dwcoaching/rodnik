<?php

declare(strict_types=1);

namespace App\Contracts;

interface SnapshotReverseGeocoder extends ReverseGeocoder
{
    /**
     * Use one validated dataset for a bounded batch; reload before the next batch.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withSnapshot(callable $callback): mixed;
}
