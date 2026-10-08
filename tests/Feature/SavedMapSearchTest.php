<?php

declare(strict_types=1);

use App\Models\Map;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function savedMapSearch(string $query, string $prefix = ''): TestResponse
{
    return test()->getJson($prefix.'/search/places?'.http_build_query(['q' => $query]))->assertOk();
}

/** @return list<string> */
function savedMapSearchTitles(string $query): array
{
    return array_column(savedMapSearch($query)->json('results'), 'name');
}

test('saved maps of every user are found by the words their titles start with', function () {
    $author = User::factory()->create(['name' => 'Мария']);
    $map = Map::factory()->create([
        'title' => 'Crimea in spring', 'slug' => 'Crimea-Spring', 'user_id' => $author->id, 'updated_at' => '2026-09-26 21:30:00',
        'state' => ['version' => 1, 'center' => [34.1, 44.9], 'zoom' => 9.5],
    ]);
    Map::factory()->create(['title' => 'Trip-crimea', 'user_id' => null, 'updated_at' => '2026-09-20 08:00:00']);
    Map::factory()->create(['title' => 'Old Crimean way', 'user_id' => User::factory()]);
    Map::factory()->create(['title' => 'Notcrimea']);

    expect(savedMapSearchTitles('crimea'))->toEqualCanonicalizing(['Crimea in spring', 'Trip-crimea', 'Old Crimean way'])
        ->and(savedMapSearch('crimea in')->json('results'))->toBe([[
            'type' => 'map', 'group' => 'maps', 'id' => $map->id, 'name' => 'Crimea in spring', 'alt_name' => null,
            'context' => 'Мария · 26 Sep 2026', 'sources_count' => null, 'sources_label' => null,
            'url' => url('/maps/Crimea-Spring').'/', 'center' => [34.1, 44.9], 'bounds' => null, 'zoom' => 9.5,
        ]])
        ->and(savedMapSearch('crimea in', '/ru')->json('results.0'))->toMatchArray([
            'context' => 'Мария · 26 сент. 2026', 'url' => url('/ru/maps/Crimea-Spring').'/',
        ])
        ->and(savedMapSearch('trip')->json('results.0'))->toMatchArray(['name' => 'Trip-crimea', 'context' => '20 Sep 2026'])
        ->and(savedMapSearchTitles('spring crim'))->toBe(['Crimea in spring']);
});

test('your own maps rank above more viewed ones, and only your own stars count', function () {
    $owner = User::factory()->create();
    $someone = User::factory()->create();
    Map::factory()->create(['title' => 'Crimea trip', 'user_id' => $someone->id, 'views_count' => 10]);
    Map::factory()->create(['title' => 'Crimea hike', 'user_id' => $owner->id]);

    expect(savedMapSearchTitles('crimea'))->toBe(['Crimea trip', 'Crimea hike']);

    $this->actingAs($owner);
    expect(savedMapSearchTitles('crimea'))->toBe(['Crimea hike', 'Crimea trip']);

    // Stars are private: another user's star must not reorder anyone else's results.
    $this->app['auth']->forgetGuards();
    Map::factory()->create(['title' => 'Crimea walk', 'user_id' => $someone->id, 'views_count' => 3, 'is_starred' => true]);
    expect(savedMapSearchTitles('crimea'))->toBe(['Crimea trip', 'Crimea walk', 'Crimea hike']);

    Map::factory()->create(['title' => 'Crimea camp', 'user_id' => $owner->id, 'is_starred' => true]);
    $this->actingAs($owner);
    expect(savedMapSearchTitles('crimea'))->toBe(['Crimea camp', 'Crimea hike', 'Crimea trip', 'Crimea walk']);
});

test('exact titles and your own maps are found among many more viewed maps with the same words', function () {
    $owner = User::factory()->create();
    foreach (range(1, 105) as $index) {
        Map::factory()->create(['title' => 'Crimea trip '.$index, 'views_count' => 1]);
    }
    Map::factory()->create(['title' => 'Crimea hike', 'user_id' => $owner->id]);
    Map::factory()->create(['title' => 'Crimea']);

    $this->actingAs($owner);

    expect(array_slice(savedMapSearchTitles('crimea'), 0, 2))->toBe(['Crimea', 'Crimea hike']);
});

test('titles are found by words after quotes, brackets and slashes', function () {
    Map::factory()->create(['title' => '«Крым» весной']);
    Map::factory()->create(['title' => 'Поход (Крым)']);
    Map::factory()->create(['title' => 'Алтай/Крым']);
    Map::factory()->create(['title' => 'Подкрымье']);

    expect(savedMapSearchTitles('крым'))->toEqualCanonicalizing(['«Крым» весной', 'Поход (Крым)', 'Алтай/Крым']);
});

test('exact titles come first and results are capped', function () {
    foreach (range(1, 6) as $index) {
        Map::factory()->create(['title' => 'Altai '.$index, 'views_count' => $index]);
    }
    Map::factory()->create(['title' => 'Altai']);

    expect(savedMapSearchTitles('altai'))->toBe(['Altai', 'Altai 6', 'Altai 5', 'Altai 4']);
});

test('saved map titles need three letters and match LIKE wildcards literally', function () {
    Map::factory()->create(['title' => '100% Crimea']);
    Map::factory()->create(['title' => '1000 Crimean lakes']);
    Map::factory()->create(['title' => 'Day 1/2 in Crimea']);

    // The name matcher alone would accept "crimea_" and "crim%" (punctuation folds away), so only escaped
    // wildcards keep these empty.
    expect(savedMapSearchTitles('100%'))->toBe(['100% Crimea'])
        ->and(savedMapSearchTitles('cr'))->toBe([])
        ->and(savedMapSearchTitles('crimea_'))->toBe([])
        ->and(savedMapSearchTitles('crim%'))->toBe([])
        ->and(savedMapSearchTitles('cri\\'))->toBe([])
        ->and(savedMapSearchTitles('day 1/2'))->toBe(['Day 1/2 in Crimea']);
});

test('a map without a valid saved view has no center', function () {
    Map::factory()->create(['title' => 'Broken view', 'state' => ['version' => 1, 'center' => [500, 'x'], 'zoom' => 12]]);
    Map::factory()->create(['title' => 'Bare view', 'state' => ['version' => 1, 'center' => [30, 60], 'zoom' => 99]]);

    expect(savedMapSearch('broken')->json('results.0'))->toMatchArray(['center' => null, 'zoom' => null])
        ->and(savedMapSearch('bare')->json('results.0'))->toMatchArray(['center' => [30.0, 60.0], 'zoom' => null]);
});
