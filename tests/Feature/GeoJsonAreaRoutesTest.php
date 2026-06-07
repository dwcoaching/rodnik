<?php

declare(strict_types=1);

use App\Models\Spring;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('area api filters springs by geojson polygon without geos', function () {
    $inside = Spring::factory()->create([
        'name' => 'Inside Armenia',
        'latitude' => 40.1872,
        'longitude' => 44.5152,
    ]);

    $outside = Spring::factory()->create([
        'name' => 'Inside Bounding Box Only',
        'latitude' => 40.0,
        'longitude' => 43.5,
    ]);

    $response = $this->getJson('/api/v1/areas/armenia');

    $response->assertOk();

    expect($response->json())->toHaveCount(1);
    expect($response->json('0.id'))->toBe($inside->id);
    expect($response->json('0.id'))->not->toBe($outside->id);
});

test('moscow stats filters springs by geojson polygon without geos', function () {
    Spring::factory()->create([
        'type' => 'Water tap',
        'latitude' => 55.7558,
        'longitude' => 37.6173,
    ]);

    Spring::factory()->create([
        'type' => 'Fountain',
        'latitude' => 56.0,
        'longitude' => 36.0,
    ]);

    $response = $this->get('/moscow-stats?area=mkad');

    $response->assertOk();
    $response->assertSee('Water tap');
    $response->assertDontSee('Fountain');
});
