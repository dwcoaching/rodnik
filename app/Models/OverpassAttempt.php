<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single request sent to Overpass, kept whether it succeeded or not.
 *
 * This is the record used to re-tune {@see \App\Library\OverpassSeed}: every row carries the
 * area it covered alongside what that area actually cost, so the seed's cost model can be
 * refitted from real timings rather than guessed.
 */
final class OverpassAttempt extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<OverpassImport, $this>
     */
    public function overpassImport(): BelongsTo
    {
        return $this->belongsTo(OverpassImport::class);
    }

    /**
     * @return BelongsTo<OverpassBatch, $this>
     */
    public function overpassBatch(): BelongsTo
    {
        return $this->belongsTo(OverpassBatch::class);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'congested' => 'boolean',
        ];
    }
}
