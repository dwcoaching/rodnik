<?php

declare(strict_types=1);

use App\Actions\HideReportAction;
use App\Actions\Springs\PatchSpringsLocationAction;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringTile;
use App\Models\TrackPolygon;
use App\Models\User;
use App\Models\WateredSpringTile;
use App\Services\SourceRaster\RasterPyramid;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->pyramid = Mockery::mock(RasterPyramid::class);
    app()->instance(RasterPyramid::class, $this->pyramid);
    $this->rasterRevision = str_repeat('a', 24);
    $this->rasterPath = tempnam(sys_get_temp_dir(), 'rodnik-raster-http-');
    file_put_contents($this->rasterPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLbtAAAAABJRU5ErkJggg=='));
    Storage::fake(SpringTile::DISK);
    Storage::fake(WateredSpringTile::DISK);
    Queue::fake();
});

afterEach(function () {
    @unlink($this->rasterPath);
});

test('the overview manifest exposes the current immutable tile generation without caching discovery', function () {
    $manifest = [
        'revision' => $this->rasterRevision,
        'max_zoom' => 10,
        'tile_size' => 512,
        'tile_pixel_ratio' => 2,
        'url' => '/source-tiles/'.$this->rasterRevision.'/{z}/{x}/{y}.png',
    ];
    $this->pyramid->shouldReceive('manifest')->once()->andReturn($manifest);
    $this->pyramid->shouldNotReceive('tile');

    $this->getJson('/source-tiles.json')
        ->assertOk()->assertExactJson($manifest)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeaderMissing('Set-Cookie');
});

test('an unbuilt overview returns a retryable response without starting a web request build', function () {
    $this->pyramid->shouldReceive('manifest')->once()->andReturnNull();
    $this->pyramid->shouldNotReceive('tile', 'refresh');

    $this->getJson('/source-tiles.json')->assertStatus(503)
        ->assertHeader('Retry-After', '60')
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('a published tile is an immutable public PNG with the default source filters', function () {
    $this->pyramid->shouldReceive('tile')->once()
        ->with($this->rasterRevision, 8, 120, 80, 63, false, null)
        ->andReturn($this->rasterPath);

    $response = $this->get('/source-tiles/'.$this->rasterRevision.'/8/120/80.png');

    $response->assertOk()->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderMissing('Set-Cookie');
    expect($response->headers->get('Cache-Control'))->toContain('public', 'immutable', 'max-age=31536000')
        ->and($response->baseResponse->getFile()->getPathname())->toBe($this->rasterPath);
});

test('type and report filters reach the tile renderer without changing source data', function () {
    $this->pyramid->shouldReceive('tile')->once()
        ->with($this->rasterRevision, 10, 1023, 1023, 5, true, null)
        ->andReturn($this->rasterPath);

    $this->get('/source-tiles/'.$this->rasterRevision.'/10/1023/1023.png?types=5&reports=1')->assertOk();
});

test('the empty type selection is a valid transparent overview request', function () {
    $this->pyramid->shouldReceive('tile')->once()
        ->with($this->rasterRevision, 0, 0, 0, 0, false, null)
        ->andReturn($this->rasterPath);

    $this->get('/source-tiles/'.$this->rasterRevision.'/0/0/0.png?types=0')->assertOk();
});

test('a public route polygon filters individual sources using its private stored geometry', function () {
    $polygon = TrackPolygon::factory()->create();
    $geometry = $polygon->polygon;
    $this->pyramid->shouldReceive('tile')->once()
        ->with($this->rasterRevision, 0, 0, 0, 63, false, $geometry)
        ->andReturn($this->rasterPath);

    $this->get('/source-tiles/'.$this->rasterRevision.'/0/0/0.png?polygon='.$polygon->hash)
        ->assertOk()->assertHeader('Content-Type', 'image/png');
});

test('unknown polygon hashes do not silently render unfiltered sources', function () {
    $this->pyramid->shouldNotReceive('tile');

    $this->get('/source-tiles/'.$this->rasterRevision.'/0/0/0.png?polygon='.str_repeat('f', 64))->assertNotFound();
});

test('missing polygon geometry does not silently render unfiltered sources', function () {
    $polygon = TrackPolygon::factory()->create();
    Storage::disk(TrackPolygon::GEOMETRY_DISK)->delete($polygon->geometryPath());
    $this->pyramid->shouldNotReceive('tile');

    $this->get('/source-tiles/'.$this->rasterRevision.'/0/0/0.png?polygon='.$polygon->hash)->assertNotFound();
});

test('malformed overview filters are rejected before invoking the renderer', function (string $query, string $field) {
    $this->pyramid->shouldNotReceive('tile');

    $this->getJson('/source-tiles/'.$this->rasterRevision.'/0/0/0.png?'.$query)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['types=64', 'types'],
    ['types=-1', 'types'],
    ['types=1.5', 'types'],
    ['types[]=1', 'types'],
    ['reports=maybe', 'reports'],
    ['polygon=abc', 'polygon'],
    ['polygon='.str_repeat('A', 64), 'polygon'],
]);

test('invalid tile coordinates never invoke the renderer', function (string $coordinates) {
    $this->pyramid->shouldNotReceive('tile');

    $this->get('/source-tiles/'.$this->rasterRevision.'/'.$coordinates.'.png')->assertNotFound();
})->with(['11/0/0', '8/256/0', '8/0/256', '0/1/0', '0/0/1', '1/-1/0', '1/0/-1']);

test('invalid filters return an error without requiring a browser session', function () {
    $this->pyramid->shouldNotReceive('tile');

    $this->get('/source-tiles/'.$this->rasterRevision.'/0/0/0.png?types=64')
        ->assertUnprocessable()->assertJsonValidationErrors('types')
        ->assertHeaderMissing('Set-Cookie');
});

test('malformed revisions are rejected by the route', function () {
    $this->pyramid->shouldNotReceive('tile');

    $this->get('/source-tiles/../../0/0/0.png')->assertNotFound();
    $this->get('/source-tiles/'.str_repeat('A', 24).'/0/0/0.png')->assertNotFound();
});

test('an unavailable generation returns not found rather than rendering current data under an old URL', function () {
    $this->pyramid->shouldReceive('tile')->once()->andThrow(new InvalidArgumentException('Unknown revision.'));

    $this->get('/source-tiles/'.$this->rasterRevision.'/0/0/0.png')->assertNotFound();
});

test('source tile invalidation enqueues a raster update once despite the separate reported tile cache', function () {
    $this->pyramid->shouldReceive('invalidate')->once()->with(20.25, 10.5);

    SpringTile::invalidate(20.25, 10.5);
    WateredSpringTile::invalidate(20.25, 10.5);
});

test('moving a source invalidates the old and new raster positions', function () {
    $spring = Spring::factory()->create(['longitude' => 20.25, 'latitude' => 10.5]);
    $this->pyramid->shouldReceive('invalidate')->once()->with(20.25, 10.5);
    $this->pyramid->shouldReceive('invalidate')->once()->with(50.75, 40.5);

    app(PatchSpringsLocationAction::class)->execute($spring, ['longitude' => 50.75, 'latitude' => 40.5]);
});

test('hiding and restoring a source invalidates its raster position', function () {
    $spring = Spring::factory()->create(['longitude' => 20.25, 'latitude' => 10.5]);
    $this->pyramid->shouldReceive('invalidate')->twice()->with(20.25, 10.5);

    $spring->hide();
    $spring->unhide();
});

test('hiding a visible report invalidates the source condition in the raster layer', function () {
    $spring = Spring::factory()->create(['longitude' => 20.25, 'latitude' => 10.5]);
    $user = User::factory()->create();
    $report = Report::factory()->for($spring)->for($user)->create(['hidden_at' => null, 'from_osm' => null]);
    $this->pyramid->shouldReceive('invalidate')->once()->with(20.25, 10.5);

    app(HideReportAction::class)->execute($user, $report);
});

test('the refresh command delegates a coalesced update to the pyramid', function () {
    $this->pyramid->shouldReceive('refresh')->once()->andReturn(['changed_sources' => 2, 'tiles' => 12]);

    $this->artisan('sources:raster-refresh')->assertSuccessful();
});

test('the refresh command reports a failed refresh without claiming success', function () {
    $this->pyramid->shouldReceive('refresh')->once()->andThrow(new RuntimeException('Could not publish generation.'));

    $this->artisan('sources:raster-refresh')->expectsOutput('Could not publish generation.')->assertFailed();
});
