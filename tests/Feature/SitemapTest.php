<?php

declare(strict_types=1);

use App\Models\Map;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('saved maps have sitemaps listing the canonical page of each map in both locales', function () {
    Map::factory()->create(['slug' => 'lycian-way']);
    Map::factory()->create(['slug' => 'NypVgCGy']);

    $this->get('/sitemap.xml')->assertOk()
        ->assertSee('<loc>'.route('sitemap.maps', ['page' => 1]).'</loc>', false)
        ->assertDontSee(route('sitemap.maps', ['page' => 2]), false);
    $sitemap = $this->get('/sitemaps/maps-1.xml')->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    foreach (['lycian-way', 'NypVgCGy'] as $slug) {
        $english = url('/maps/'.$slug).'/';
        $russian = url('/ru/maps/'.$slug).'/';
        $sitemap->assertSee('<loc>'.$english.'</loc>', false)
            ->assertSee('<loc>'.$russian.'</loc>', false)
            ->assertSee('hreflang="en" href="'.$english.'"', false)
            ->assertSee('hreflang="ru" href="'.$russian.'"', false)
            ->assertSee('hreflang="x-default" href="'.$english.'"', false);

        foreach ([$english, $russian] as $url) {
            $document = HTMLDocument::createFromString($this->get($url)->assertOk()->getContent(), LIBXML_NOERROR);
            expect($document->querySelector('link[rel="canonical"]')->getAttribute('href'))->toBe($url)
                ->and($document->querySelector('meta[name="robots"]'))->toBeNull();
        }
    }

    $this->get('/sitemaps/maps-2.xml')->assertNotFound();
    $this->get('/sitemaps/maps-0.xml')->assertNotFound();
});

test('the sitemap index omits map sitemaps until a map is saved', function () {
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('/sitemaps/maps-', false);
    $this->get('/sitemaps/maps-1.xml')->assertNotFound();
});

test('the static sitemap lists the docs overview and saved maps pages in both locales', function (string $path) {
    $this->get('/sitemaps/static.xml')->assertOk()
        ->assertSee('<loc>'.url($path).'</loc>', false)
        ->assertSee('<loc>'.url('/ru'.$path).'</loc>', false)
        ->assertSee('hreflang="ru" href="'.url('/ru'.$path).'"', false)
        ->assertSee('hreflang="x-default" href="'.url($path).'"', false);
})->with(['overview' => '/docs', 'saved maps' => '/docs/maps']);
