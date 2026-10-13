<?php

declare(strict_types=1);

namespace App\Library;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps outgoing Overpass traffic inside the quota the API advertises for our IP.
 *
 * overpass-api.de grants every client a couple of slots and answers with HTTP 429
 * (`rate_limited`) or HTTP 504 (`request_read_and_idx::timeout`) once they are used
 * up. Both are congestion signals, so the only useful reaction is to wait for a slot
 * instead of sending more requests.
 *
 * maps.mail.ru has no quota at all, and its 504s are a coin toss: over two full imports the
 * share of refusals stayed at about half whether the previous query had just returned or we
 * had waited minutes, so there the gate keeps out of the way unless refusals pile up. How
 * patient to be is configured per endpoint under `services.overpass`.
 */
final class OverpassGate
{
    /**
     * Upper bound for a single wait so an unreachable status endpoint cannot stall a batch.
     */
    public const MAXIMUM_WAIT_SECONDS = 180;

    private const NEXT_REQUEST_CACHE_KEY = 'overpass:next-request-at';

    /**
     * Exponential backoff, capped so a batch keeps making progress.
     *
     * @return int Seconds to wait before the next attempt.
     */
    public static function backoffSeconds(int $consecutiveFailures): int
    {
        $wait = 5 * (2 ** max(0, $consecutiveFailures - 1));

        return (int) min($wait, self::MAXIMUM_WAIT_SECONDS);
    }

    /**
     * Read the free slot count and the shortest wait out of an /api/status body.
     *
     * @return array{slots: int, wait: int}
     */
    public static function parseStatus(string $body): array
    {
        $slots = 0;
        $waits = [];

        if (preg_match('/(\d+)\s+slots?\s+available\s+now/i', $body, $matches)) {
            $slots = (int) $matches[1];
        }

        if (preg_match_all('/Slot available after:.*?in\s+(-?\d+)\s+seconds/i', $body, $matches)) {
            $waits = array_map(static fn ($seconds): int => max(0, (int) $seconds), $matches[1]);
        }

        if ($slots > 0 || $waits === []) {
            return ['slots' => $slots, 'wait' => 0];
        }

        return ['slots' => 0, 'wait' => (int) min(min($waits) + 1, self::MAXIMUM_WAIT_SECONDS)];
    }

    /**
     * Shortest allowed interval between the *starts* of two queries.
     *
     * Measured from the start rather than from the previous response on purpose. overpass-api.de
     * keeps a slot marked busy for a while after a query returns — /api/status advertised waits
     * of up to 44 seconds during a full import — so pacing from the response punishes nothing
     * when a query is slow and paces not at all when it is fast. Cheap ocean areas came back in
     * seconds and were refused on 98% of first tries, while 150-second European areas, which
     * space themselves out for free, were never refused once.
     */
    public static function minimumCycleSeconds(): int
    {
        return max(0, (int) config('services.overpass.minimum_cycle_seconds'));
    }

    /**
     * How long to wait after a run of congestion responses.
     *
     * The first `tolerated_congestion` refusals in a row are treated as noise and retried after
     * the shortest wait; only a longer run, which a coin toss rarely produces, starts the
     * exponential backoff that protects an API that is really down.
     */
    public static function congestionWaitSeconds(int $consecutiveFailures): int
    {
        $tolerated = max(0, (int) config('services.overpass.tolerated_congestion'));

        return self::backoffSeconds($consecutiveFailures - $tolerated);
    }

    /**
     * Wait until the API is ready for another query, then claim the next slot.
     *
     * The claim is made here, before the query runs, so that the reservation covers the query
     * itself: an area that takes longer than the cycle waits no extra time, while a fast one is
     * held back until the cycle is up. Deliberately does not call /api/status on the happy path,
     * since asking would double the number of requests we make.
     */
    public function awaitSlot(): void
    {
        $cycle = self::minimumCycleSeconds();

        if ($cycle <= 0) {
            return;
        }

        $this->sleep($this->secondsUntilNextRequest());

        $this->reserveNextRequestAt($cycle);
    }

    /**
     * Back off after a congestion response, then hold the whole batch back for that long.
     */
    public function backOff(int $consecutiveFailures): void
    {
        $wait = self::congestionWaitSeconds($consecutiveFailures);

        if (config('services.overpass.consult_status')) {
            $wait = max($this->waitAdvertisedByStatus(), $wait);
        }

        echo "Overpass is congested ({$consecutiveFailures} in a row), backing off {$wait}s\n";

        $this->reserveNextRequestAt($wait);
        $this->sleep($wait);
    }

    /**
     * Ask the API how long we have to wait. Treats an unreachable status endpoint as "no wait",
     * because the caller always applies its own spacing on top.
     */
    private function waitAdvertisedByStatus(): int
    {
        try {
            $response = (new Client)->request('GET', config('services.overpass.status_url'), [
                'headers' => [
                    'User-Agent' => 'Rodnik.today/1.0 (+https://rodnik.today; kolpavko@hey.com)',
                    'Accept' => '*/*',
                ],
                'timeout' => 15,
                'http_errors' => false,
            ]);

            return self::parseStatus((string) $response->getBody())['wait'];
        } catch (TransferException) {
            return 0;
        }
    }

    private function secondsUntilNextRequest(): int
    {
        $nextRequestAt = Cache::get(self::NEXT_REQUEST_CACHE_KEY);

        if (! $nextRequestAt) {
            return 0;
        }

        return (int) max(0, min($nextRequestAt - now()->getTimestamp(), self::MAXIMUM_WAIT_SECONDS));
    }

    private function reserveNextRequestAt(int $seconds): void
    {
        Cache::put(
            self::NEXT_REQUEST_CACHE_KEY,
            now()->getTimestamp() + $seconds,
            now()->addSeconds($seconds + 60),
        );
    }

    private function sleep(int $seconds): void
    {
        if ($seconds > 0 && ! app()->runningUnitTests()) {
            sleep($seconds);
        }
    }
}
