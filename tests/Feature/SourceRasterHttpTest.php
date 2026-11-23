<?php

declare(strict_types=1);

use App\Actions\HideReportAction;
use App\Actions\Springs\PatchSpringsLocationAction;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringTile;
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
    $this->pyramid->shouldReceive('manifest')->andReturn($manifest);
    $this->pyramid->shouldReceive('enabled')->twice()->andReturn(true, false);
    $this->pyramid->shouldNotReceive('tile');

    $this->getJson('/source-tiles.json')
        ->assertOk()->assertExactJson($manifest)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeaderMissing('Set-Cookie');

    // Switched off in the admin: maps get the same retryable answer as before a build.
    $this->getJson('/source-tiles.json')->assertStatus(503)->assertHeader('Retry-After', '60')
        ->assertJsonMissing(['revision' => $this->rasterRevision]);
});

test('an unbuilt overview returns a retryable response without starting a web request build', function () {
    $this->pyramid->shouldReceive('manifest')->once()->andReturnNull();
    $this->pyramid->shouldNotReceive('tile', 'refresh');

    $this->getJson('/source-tiles.json')->assertStatus(503)
        ->assertHeader('Retry-After', '60')
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('a published tile is a public PNG that browsers revalidate after a minute', function () {
    $this->pyramid->shouldReceive('tile')->once()
        ->with($this->rasterRevision, 8, 120, 80)
        ->andReturn($this->rasterPath);

    $response = $this->get('/source-tiles/'.$this->rasterRevision.'/8/120/80.png');

    $response->assertOk()->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderMissing('Set-Cookie');
    expect($response->headers->get('Cache-Control'))->toContain('public', 'max-age=60')->not->toContain('immutable')
        ->and($response->baseResponse->getFile()->getPathname())->toBe($this->rasterPath);

    $this->pyramid->shouldReceive('tile')->once()->andReturn($this->rasterPath);
    $this->get('/source-tiles/'.$this->rasterRevision.'/8/120/80.png', ['If-Modified-Since' => $response->headers->get('Last-Modified')])
        ->assertStatus(304);
});

test('the stable tile URL redirects to the latest enabled revision without caching', function () {
    $nextRevision = str_repeat('b', 24);
    $this->pyramid->shouldReceive('manifest')->twice()->andReturn(
        ['revision' => $this->rasterRevision],
        ['revision' => $nextRevision],
    );
    $this->pyramid->shouldReceive('enabled')->twice()->andReturn(true);
    $this->pyramid->shouldNotReceive('tile', 'png');

    $url = '/source-tiles/10/609/369.png?types=1&reports=1';
    $first = $this->get($url);
    $first->assertStatus(302)
        ->assertRedirect('/source-tiles/'.$this->rasterRevision.'/10/609/369.png')
        ->assertHeaderMissing('Set-Cookie');
    expect($first->headers->get('Cache-Control'))->toContain('no-store');

    $this->get($url)->assertRedirect('/source-tiles/'.$nextRevision.'/10/609/369.png');
});

test('the stable tile URL is unavailable until there is an enabled raster', function () {
    $this->pyramid->shouldReceive('manifest')->twice()->andReturn(null, ['revision' => $this->rasterRevision]);
    $this->pyramid->shouldReceive('enabled')->once()->andReturn(false);
    $this->pyramid->shouldNotReceive('tile');

    $this->get('/source-tiles/0/0/0.png')->assertStatus(503)->assertHeader('Retry-After', '60');
    $this->get('/source-tiles/0/0/0.png')->assertStatus(503)->assertHeader('Retry-After', '60');
});

test('invalid tile coordinates never invoke the renderer', function (string $coordinates) {
    $this->pyramid->shouldNotReceive('tile');

    $this->get('/source-tiles/'.$this->rasterRevision.'/'.$coordinates.'.png')->assertNotFound();
    $this->get('/source-tiles/'.$coordinates.'.png')->assertNotFound();
})->with(['11/0/0', '8/256/0', '8/0/256', '0/1/0', '0/0/1', '1/-1/0', '1/0/-1']);

test('legacy filter parameters still return the combined raster tile', function () {
    $this->pyramid->shouldReceive('tile')->once()->with($this->rasterRevision, 0, 0, 0)
        ->andReturn($this->rasterPath);
    $this->pyramid->shouldNotReceive('png');

    $this->get('/source-tiles/'.$this->rasterRevision.'/0/0/0.png?types=1&reports=1')
        ->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeaderMissing('Set-Cookie');
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

test('only admins can read the style, preview a draft or queue a redraw', function () {
    $this->pyramid->shouldNotReceive('style', 'png', 'queueStyle');

    $this->getJson('/admin/source-raster/style')->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    $this->getJson('/admin/source-raster/style')->assertForbidden();
    $this->get('/admin/source-raster/preview/0/0/0.png?s=1,1,1,1,1,1,1,1')->assertForbidden();
    $this->postJson('/admin/source-raster/style', ['style' => config('source_raster.style')])->assertForbidden();
});

test('an admin previews a draft style for one zoom without publishing anything', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $this->pyramid->shouldReceive('style')->andReturn(config('source_raster.style'));
    $this->pyramid->shouldReceive('png')->once()->withArgs(function ($z, $x, $y, $style) {
        return [$z, $x, $y] === [7, 3, 2]
            && $style['order'] === ['red', 'green', 'yellow', 'blue']
            && $style['zooms'][7] === ['blue' => [2.0, 0.1], 'yellow' => [3.0, 0.2], 'green' => [4.0, 0.3], 'red' => [5.0, 0.4]]
            && $style['zooms'][6]['blue'] === config('source_raster.style.zooms.6.blue');
    })->andReturn('preview');

    $response = $this->get('/admin/source-raster/preview/7/3/2.png?s=2,0.1,3,0.2,4,0.3,5,0.4&o=3210');
    $response->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($response->getContent())->toBe('preview');

    $this->get('/admin/source-raster/preview/7/3/2.png?s=99,0.1,3,0.2,4,0.3,5,0.4')->assertUnprocessable();
    $this->get('/admin/source-raster/preview/7/3/2.png?s=1,2,3')->assertUnprocessable();
    $this->get('/admin/source-raster/preview/7/3/2.png?s=2,0.1,3,0.2,4,0.3,5,0.4&o=0012')->assertUnprocessable();
});

test('an admin queues a complete style for the next refresh', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $status = ['revision' => $this->rasterRevision, 'generated_at' => null, 'enabled' => false, 'pending' => true, 'busy' => false];
    $this->pyramid->shouldReceive('manifest')->andReturn(['revision' => $this->rasterRevision, 'max_zoom' => 10, 'url' => '/source-tiles/x/{z}/{x}/{y}.png']);
    $this->pyramid->shouldReceive('status')->andReturn($status);
    $this->pyramid->shouldReceive('queueStyle')->once()->with(config('source_raster.style'));
    $this->pyramid->shouldReceive('queueStyle')->once()->andThrow(new InvalidArgumentException('Zoom 3 red needs a size of 0-14 and an opacity of 0-1.'));
    $this->pyramid->shouldReceive('style')->andReturn(config('source_raster.style'));
    $this->pyramid->shouldReceive('pendingStyle')->andReturnNull();

    $this->postJson('/admin/source-raster/style', ['style' => config('source_raster.style')])
        ->assertStatus(202)->assertJson(['pending' => true]);
    $this->postJson('/admin/source-raster/style', ['style' => ['order' => []]])
        ->assertUnprocessable()->assertJson(['message' => 'Zoom 3 red needs a size of 0-14 and an opacity of 0-1.']);
    $this->getJson('/admin/source-raster/style')->assertOk()
        ->assertJsonPath('style.order', config('source_raster.style.order'))
        ->assertJsonPath('max_diameter', 14)->assertJsonPath('pending', true)
        ->assertJsonPath('pending_style', null)
        ->assertJsonPath('url', '/source-tiles/x/{z}/{x}/{y}.png')->assertJsonPath('max_zoom', 10)
        ->assertJsonPath('enabled', false);
});

test('the raster lab page is only available to admins', function () {
    $this->withoutVite();

    $this->get('/admin/raster-lab')->assertRedirect();
    $this->actingAs(User::factory()->create());
    $this->get('/admin/raster-lab')->assertForbidden();
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $this->get('/admin/raster-lab')->assertOk()->assertSee('raster-lab-map')->assertSee('Generate tiles');
    $this->get('/')->assertDontSee('rasterLab');
});

test('a superadmin switches visitors between the vector and raster overview once tiles exist', function () {
    $this->pyramid->shouldReceive('status')->andReturn(['revision' => null, 'generated_at' => null, 'enabled' => false, 'pending' => false, 'busy' => false]);
    $this->pyramid->shouldReceive('manifest')->times(2)->andReturn(null, ['revision' => $this->rasterRevision, 'max_zoom' => 10]);
    $this->pyramid->shouldReceive('setEnabled')->once()->with(true);
    $this->pyramid->shouldReceive('setEnabled')->once()->with(false);

    $this->actingAs(User::factory()->create(['is_admin' => true]));
    expect(App\Filament\Widgets\SourceOverviewWidget::canView())->toBeFalse();
    Livewire\Livewire::test(App\Filament\Widgets\SourceOverviewWidget::class)->call('setEnabled', true)->assertForbidden();

    $this->actingAs(User::factory()->create(['is_superadmin' => true]));
    Livewire\Livewire::test(App\Filament\Widgets\SourceOverviewWidget::class)
        ->assertSee('have not been built')
        ->call('setEnabled', true)   // Refused: nothing is built yet.
        ->call('setEnabled', true)
        ->call('setEnabled', false);
});
