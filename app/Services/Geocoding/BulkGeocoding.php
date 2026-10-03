<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use App\Jobs\GeocodeSprings;
use App\Models\GeocodingRun;
use App\Models\Spring;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class BulkGeocoding
{
    public const MODES = ['all', 'missing', 'reset'];

    private const LEASE_SECONDS = 75;

    public function __construct(private GeocodeSpring $geocoder) {}

    public function start(string $mode): GeocodingRun
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new RuntimeException('Unknown geocoding mode.');
        }

        File::ensureDirectoryExists(storage_path('app'));
        $lock = @fopen(storage_path('app/geography-catalog-import.lock'), 'c');

        if ($lock === false || ! flock($lock, LOCK_SH | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            throw new RuntimeException('A geography catalog import is running. Finish it before starting reverse geocoding.');
        }

        try {
            // Hold the shared lock until the active run exists. An importer
            // either finishes first or sees that run under its exclusive lock.
            return $this->reserve($mode);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function enqueue(GeocodingRun $run): void
    {
        if (! $run->isActive()) {
            return;
        }

        try {
            GeocodeSprings::dispatch($run->id)
                ->onConnection(config('geocoding.queue_connection', 'database'))
                ->onQueue(config('geocoding.queue', 'geocoding'));
        } catch (Throwable $exception) {
            $this->fail($run, 'Unable to queue reverse geocoding: '.$exception->getMessage());
            throw $exception;
        }
    }

    /** Returns false when another worker owns the run or it has stopped. */
    public function processChunk(GeocodingRun $run): bool
    {
        $token = (string) Str::uuid();
        $claimed = GeocodingRun::query()
            ->whereKey($run->id)
            ->whereNotNull('active_key')
            ->whereIn('status', ['queued', 'running'])
            ->where(fn (Builder $query) => $query->whereNull('lease_until')->orWhere('lease_until', '<=', now()))
            ->update([
                'processing_token' => $token,
                'lease_until' => now()->addSeconds(self::LEASE_SECONDS),
                'heartbeat_at' => now(),
                'status' => 'running',
            ]);

        if (! $claimed) {
            return false;
        }

        $run->refresh();

        try {
            $processed = $this->geocoder->withSnapshot(function () use ($run, $token): bool {
                $this->geocoder->validateSource();
                $this->applyReset($run);
                if ($run->started_at === null) {
                    $this->owned($run, $token)->update(['started_at' => now()]);
                }

                $springs = $this->selection($run->mode, $run->max_spring_id)
                    ->where('id', '>', $run->last_spring_id)
                    ->orderBy('id')
                    ->limit(max(1, min(1000, (int) config('geocoding.chunk_size', 200))))
                    ->get();

                $started = microtime(true);
                foreach ($springs as $spring) {
                    $column = 'matched';
                    $error = null;
                    try {
                        if (! $this->geocoder->geocode($spring, fn (): bool => $this->owned($run, $token)->lockForUpdate()->first() !== null)) {
                            $column = 'unmatched';
                        }
                    } catch (Throwable $exception) {
                        $column = 'failed_count';
                        $error = 'Water source #'.$spring->id.': '.$exception->getMessage();
                        report($exception);
                    }

                    $values = [
                        'last_spring_id' => $spring->id,
                        'processed' => DB::raw('processed + 1'),
                        $column => DB::raw($column.' + 1'),
                        'heartbeat_at' => now(),
                        'lease_until' => now()->addSeconds(self::LEASE_SECONDS),
                    ];
                    if ($error !== null) {
                        $values['error'] = $error;
                    }
                    if (! $this->owned($run, $token)->update($values)) {
                        return false;
                    }

                    // A bounded job finishes comfortably before the database queue's retry_after.
                    if (microtime(true) - $started >= 30) {
                        break;
                    }
                }

                $run->refresh();
                $hasMore = $this->selection($run->mode, $run->max_spring_id)
                    ->where('id', '>', $run->last_spring_id)->exists();
                $values = ['processing_token' => null, 'lease_until' => null, 'heartbeat_at' => now()];
                if (! $hasMore) {
                    $values += ['status' => 'completed', 'active_key' => null, 'finished_at' => now()];
                } else {
                    $values['status'] = 'queued';
                }
                $this->owned($run, $token)->update($values);

                return true;
            });
        } catch (Throwable $exception) {
            $this->owned($run, $token)->update($this->failedValues($exception->getMessage()));
            throw $exception;
        }

        $run->refresh();

        return $processed;
    }

    public function stop(GeocodingRun $run): void
    {
        $stopped = GeocodingRun::query()
            ->whereKey($run->id)->whereNotNull('active_key')
            ->where(fn (Builder $query) => $query->whereNull('lease_until')->orWhere('lease_until', '<=', now()))
            ->update($this->failedValues('Stopped by an administrator.'));

        if (! $stopped) {
            throw new RuntimeException('This run is currently processing. Wait until its worker stops before releasing it.');
        }
    }

    public function fail(GeocodingRun $run, string $message): void
    {
        GeocodingRun::query()->whereKey($run->id)->whereNotNull('active_key')
            ->update($this->failedValues($message));
    }

    private function reserve(string $mode): GeocodingRun
    {

        // Validate before reserving a run or removing any existing locations.
        $this->geocoder->validateSource();

        $maxId = (int) Spring::query()->max('id');
        $total = $this->selection($mode, $maxId)->count();

        try {
            $run = GeocodingRun::create([
                'active_key' => 'springs',
                'mode' => $mode,
                'status' => 'queued',
                'max_spring_id' => $maxId,
                'total' => $total,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new RuntimeException('A reverse geocoding run is already active. Resume or stop it before starting another.');
        }

        try {
            $this->applyReset($run);

            if ($total === 0) {
                $run->update(['status' => 'completed', 'active_key' => null, 'finished_at' => now()]);
            }
        } catch (Throwable $exception) {
            $this->fail($run, $exception->getMessage());
            throw $exception;
        }

        return $run->refresh();
    }

    private function failedValues(string $message): array
    {
        return [
            'status' => 'failed', 'active_key' => null, 'error' => $message,
            'processing_token' => null, 'lease_until' => null, 'finished_at' => now(),
        ];
    }

    /** @return Builder<GeocodingRun> */
    private function owned(GeocodingRun $run, string $token): Builder
    {
        return GeocodingRun::query()->whereKey($run->id)
            ->where('processing_token', $token)->whereNotNull('active_key');
    }

    /** @return Builder<Spring> */
    private function selection(string $mode, int $maxId): Builder
    {
        $query = Spring::query()->where('id', '<=', $maxId);

        if ($mode === 'missing') {
            $query->where(fn (Builder $query) => $query
                ->whereNull('country')->orWhere('country', '')
                ->orWhereNull('city')->orWhere('city', ''));
        }

        return $query;
    }

    private function applyReset(GeocodingRun $run): void
    {
        if ($run->mode !== 'reset' || $run->reset_applied_at !== null) {
            return;
        }

        $this->geocoder->validateSource();
        DB::transaction(function () use ($run): void {
            $locked = GeocodingRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isActive() || $locked->reset_applied_at !== null) {
                return;
            }

            Spring::query()->where('id', '<=', $locked->max_spring_id)->toBase()
                ->update(array_fill_keys(GeocodeSpring::FIELDS, null));
            $locked->update(['reset_applied_at' => now()]);
        });
        $run->refresh();
    }
}
