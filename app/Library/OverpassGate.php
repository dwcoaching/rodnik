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
 */
final class OverpassGate
{
    public const STATUS_URL = 'https://overpass-api.de/api/status';

    /**
     * Shortest allowed interval between the *starts* of two queries.
     *
     * Measured from the start rather than from the previous response on purpose. Overpass keeps
     * a slot marked busy for a while after a query returns — /api/status advertised waits of up
     * to 44 seconds during the last full import — so pacing from the response punishes nothing
     * when a query is slow and paces not at all when it is fast. Cheap ocean areas came back in
     * seconds and were refused on 98% of first tries, while 150-second European areas, which
     * space themselves out for free, were never refused once.
     */
    public const MINIMUM_CYCLE_SECONDS = 30;

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
     * Wait until the API is ready for another query, then claim the next slot.
     *
     * The claim is made here, before the query runs, so that the reservation covers the query
     * itself: an area that takes longer than the cycle waits no extra time, while a fast one is
     * held back until the cycle is up. Deliberately does not call /api/status on the happy path,
     * since asking would double the number of requests we make.
     */
    public function awaitSlot(): void
    {
        $this->sleep($this->secondsUntilNextRequest());

        $this->reserveNextRequestAt(self::MINIMUM_CYCLE_SECONDS);
    }

    /**
     * Back off after a congestion response, then hold the whole batch back for that long.
     */
    public function backOff(int $consecutiveFailures): void
    {
        $wait = max($this->waitAdvertisedByStatus(), self::backoffSeconds($consecutiveFailures));

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
            $response = (new Client)->request('GET', self::STATUS_URL, [
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
