<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $lease_until
 * @property Carbon|null $heartbeat_at
 * @property Carbon|null $reset_applied_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
final class GeocodingRun extends Model
{
    protected $guarded = [];

    public function isActive(): bool
    {
        return $this->active_key !== null && in_array($this->status, ['queued', 'running'], true);
    }

    public function canRecover(): bool
    {
        return $this->isActive() && ($this->lease_until === null || $this->lease_until->isPast());
    }

    public function progressPercentage(): int
    {
        if ($this->status === 'completed') {
            return 100;
        }

        return $this->total > 0 ? min(100, (int) floor($this->processed / $this->total * 100)) : 0;
    }

    protected function casts(): array
    {
        return [
            'max_spring_id' => 'integer',
            'last_spring_id' => 'integer',
            'total' => 'integer',
            'processed' => 'integer',
            'matched' => 'integer',
            'unmatched' => 'integer',
            'failed_count' => 'integer',
            'lease_until' => 'datetime',
            'heartbeat_at' => 'datetime',
            'reset_applied_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
