<?php

declare(strict_types=1);

use App\Library\OverpassGate;
use App\Models\OverpassBatch;
use App\Models\OverpassImport;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Answer every Overpass request with a queue of canned Guzzle results.
 *
 * @param  array<int, mixed>  $results
 * @param  array<int, array<string, mixed>>|null  $history
 */
function fakeOverpassResponses(array $results, ?array &$history = null): void
{
    $handler = HandlerStack::create(new MockHandler($results));

    if ($history !== null) {
        $handler->push(Middleware::history($history));
    }

    app()->bind(Client::class, fn (): Client => new Client([
        'handler' => $handler,
    ]));
}

function overpassImport(OverpassBatch $batch, array $attributes = []): OverpassImport
{
    $import = new OverpassImport();
    $import->overpass_batch_id = $batch->id;
    $import->latitude_from = -90;
    $import->latitude_to = 90;
    $import->longitude_from = 10;
    $import->longitude_to = 11;
    $import->ground_up = false;
    $import->attempts = 1;
    $import->started_at = now();
    $import->fetched_at = now();

    $response = $attributes['response'] ?? null;
    unset($attributes['response']);

    foreach ($attributes as $key => $value) {
        $import->{$key} = $value;
    }

    $import->save();

    // The response body lives on disk under the import's id, so it can only be written once
    // the record exists.
    if ($response !== null) {
        $import->response = $response;
    }

    return $import;
}

function overpassErrorPage(string $error): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><html><body>'
        .'<p><strong style="color:#FF0000">Error</strong>: runtime error: open64: 0 Success '
        ."/osm3s_osm_base Dispatcher_Client::request_read_and_idx::{$error}. </p>"
        .'</body></html>';
}

test('a rate limited response is treated as congestion, not as an area that is too big', function () {
    $batch = OverpassBatch::create([]);

    $import = overpassImport($batch, [
        'response_code' => 429,
        'response' => overpassErrorPage('rate_limited'),
        'has_remarks' => true,
    ]);

    expect($import->isCongested())->toBeTrue()
        ->and($import->needsSmallerArea())->toBeFalse()
        ->and($import->succeeded())->toBeFalse();
});

test('a busy dispatcher timeout is treated as congestion, not as an area that is too big', function () {
    $batch = OverpassBatch::create([]);

    $import = overpassImport($batch, [
        'response_code' => 504,
        'response' => overpassErrorPage('timeout'),
        'has_remarks' => true,
    ]);

    expect($import->isCongested())->toBeTrue()
        ->and($import->needsSmallerArea())->toBeFalse();
});

test('an unrecognised server error is retried rather than ground up', function () {
    $batch = OverpassBatch::create([]);

    $import = overpassImport($batch, [
        'response_code' => 500,
        'response' => 'Internal Server Error',
        'has_remarks' => true,
    ]);

    expect($import->isCongested())->toBeTrue()
        ->and($import->needsSmallerArea())->toBeFalse();

    $batch->grindUpFailedImports();

    $import->refresh();

    expect($import->fetched_at)->toBeNull()
        ->and(OverpassImport::where('parent_id', $import->id)->count())->toBe(0);
});

test('a truncated body is retried rather than ground up', function () {
    $batch = OverpassBatch::create([]);

    $import = overpassImport($batch, [
        'response_code' => 200,
        'response' => '{"elements":[{"type":"node","id":1,',
        'has_remarks' => true,
    ]);

    expect($import->succeeded())->toBeFalse()
        ->and($import->needsSmallerArea())->toBeFalse()
        ->and($import->isCongested())->toBeTrue();

    $batch->grindUpFailedImports();

    expect(OverpassImport::where('parent_id', $import->id)->count())->toBe(0);
});

test('only a genuine Overpass remark asks for a smaller area', function () {
    $batch = OverpassBatch::create([]);

    $tooBig = overpassImport($batch, [
        'response_code' => 200,
        'response' => json_encode([
            'remark' => 'runtime error: Query timed out in "recurse" at line 5 after 180 seconds.',
            'elements' => [],
        ]),
        'has_remarks' => true,
    ]);

    expect($tooBig->needsSmallerArea())->toBeTrue()
        ->and($tooBig->isCongested())->toBeFalse();
});

test('an element tagged remark does not count as an Overpass remark', function () {
    $batch = OverpassBatch::create([]);

    $import = overpassImport($batch, [
        'response_code' => 200,
        'response' => json_encode([
            'elements' => [
                ['type' => 'node', 'id' => 1, 'tags' => ['remark' => 'Forage']],
            ],
        ]),
        'has_remarks' => false,
    ]);

    expect($import->responseRemark())->toBeNull()
        ->and($import->needsSmallerArea())->toBeFalse()
        ->and($import->succeeded())->toBeTrue();
});

test('a congested import is retried over the same area instead of being ground up', function () {
    $batch = OverpassBatch::create([]);

    $import = overpassImport($batch, [
        'response_code' => 429,
        'response' => overpassErrorPage('rate_limited'),
        'has_remarks' => true,
    ]);

    expect($batch->grindUpFailedImports())->toBeTrue();

    $import->refresh();

    expect($import->fetched_at)->toBeNull()
        ->and($import->has_remarks)->toBeNull()
        ->and((bool) $import->ground_up)->toBeFalse()
        ->and(OverpassImport::where('parent_id', $import->id)->count())->toBe(0);
});

test('an import that really is too big is still ground up into smaller areas', function () {
    $batch = OverpassBatch::create([]);

    $import = overpassImport($batch, [
        'response_code' => 200,
        'response' => json_encode(['remark' => 'runtime error: Query run out of memory.']),
        'has_remarks' => true,
    ]);

    $batch->grindUpFailedImports();

    $import->refresh();
    $children = OverpassImport::where('parent_id', $import->id)->get();

    expect((bool) $import->ground_up)->toBeTrue()
        ->and($children)->toHaveCount(3)
        ->and($children->pluck('longitude_from')->map(fn ($value) => (float) $value)->unique()->all())
        ->toBe([10.0]);
});

test('a congested import stops being retried once it runs out of attempts', function () {
    $batch = OverpassBatch::create([]);

    $import = overpassImport($batch, [
        'response_code' => 429,
        'response' => overpassErrorPage('rate_limited'),
        'has_remarks' => true,
        'attempts' => OverpassImport::MAXIMUM_ATTEMPTS,
    ]);

    $batch->grindUpFailedImports();

    $import->refresh();

    expect($import->hasAttemptsLeft())->toBeFalse()
        ->and((bool) $import->ground_up)->toBeTrue()
        ->and($import->fetched_at)->not->toBeNull();
});

test('grinding up a 1x1 area gives up instead of cloning itself forever', function () {
    $batch = OverpassBatch::create([]);

    $import = overpassImport($batch, [
        'latitude_from' => 40,
        'latitude_to' => 41,
        'response_code' => 400,
        'response' => 'Bad Request',
        'has_remarks' => true,
    ]);

    $batch->grindUpFailedImports();

    $import->refresh();

    expect((bool) $import->ground_up)->toBeTrue()
        ->and(OverpassImport::where('parent_id', $import->id)->count())->toBe(0);
});

test('a connection failure is recorded instead of aborting the batch', function () {
    $batch = OverpassBatch::create([]);

    fakeOverpassResponses([
        new ConnectException(
            'cURL error 7: Failed to connect to overpass-api.de port 443',
            new Request('POST', OverpassImport::ENDPOINT),
        ),
    ]);

    $import = new OverpassImport();
    $import->overpass_batch_id = $batch->id;
    $import->latitude_from = -90;
    $import->latitude_to = 90;
    $import->longitude_from = 10;
    $import->longitude_to = 11;
    $import->save();

    $import->fetch();

    expect((int) $import->response_code)->toBe(OverpassImport::TRANSPORT_ERROR)
        ->and($import->response_phrase)->toContain('Failed to connect')
        ->and($import->fetched_at)->not->toBeNull()
        ->and($import->attempts)->toBe(1)
        ->and($import->isCongested())->toBeTrue()
        ->and($import->needsSmallerArea())->toBeFalse();
});

test('a successful fetch counts an attempt and keeps the payload', function () {
    $batch = OverpassBatch::create([]);
    $history = [];

    fakeOverpassResponses([
        new Response(200, [], json_encode(['elements' => []])),
    ], $history);

    $import = new OverpassImport();
    $import->overpass_batch_id = $batch->id;
    $import->latitude_from = -90;
    $import->latitude_to = 90;
    $import->longitude_from = 12;
    $import->longitude_to = 13;
    $import->attempts = 2;
    $import->save();

    $import->fetch();

    expect((int) $import->response_code)->toBe(200)
        ->and($import->attempts)->toBe(3)
        ->and($import->succeeded())->toBeTrue()
        ->and($import->isCongested())->toBeFalse()
        ->and($history)->toHaveCount(1)
        ->and($history[0]['options']['connect_timeout'])->toBe(15)
        ->and($history[0]['options']['timeout'])->toBe(210);
});

test('the gate reads free slots and waiting times out of an /api/status body', function () {
    $free = OverpassGate::parseStatus(<<<'TXT'
        Connected as: 1407062504
        Current time: 2026-08-04T15:16:45Z
        Rate limit: 2
        2 slots available now.
        TXT);

    expect($free)->toBe(['slots' => 2, 'wait' => 0]);

    $busy = OverpassGate::parseStatus(<<<'TXT'
        Rate limit: 2
        Slot available after: 2026-08-04T15:20:00Z, in 12 seconds.
        Slot available after: 2026-08-04T15:20:10Z, in 22 seconds.
        TXT);

    expect($busy)->toBe(['slots' => 0, 'wait' => 13]);
});

test('the gate never waits on an unparseable or already elapsed status', function () {
    expect(OverpassGate::parseStatus('nonsense')['wait'])->toBe(0);

    expect(OverpassGate::parseStatus('Slot available after: 2026-08-04T15:20:00Z, in -3 seconds.')['wait'])
        ->toBe(1);
});

test('the gate backs off exponentially and stays capped', function () {
    expect(OverpassGate::backoffSeconds(1))->toBe(5)
        ->and(OverpassGate::backoffSeconds(2))->toBe(10)
        ->and(OverpassGate::backoffSeconds(3))->toBe(20)
        ->and(OverpassGate::backoffSeconds(20))->toBe(OverpassGate::MAXIMUM_WAIT_SECONDS);
});
