<?php

declare(strict_types=1);

use App\Library\Laundry;
use App\Models\OSMTag;
use App\Models\Spring;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cleanup removes springs with false positive tags', function () {
    // Create a spring with false positive tags (amenity=toilets & drinking_water=no)
    $falsePositiveSpring = Spring::factory()->create([
        'latitude' => 10.111111,
        'longitude' => 20.222222,
    ]);
    OSMTag::factory()->create([
        'spring_id' => $falsePositiveSpring->id,
        'key' => 'amenity',
        'value' => 'toilets',
    ]);
    OSMTag::factory()->create([
        'spring_id' => $falsePositiveSpring->id,
        'key' => 'drinking_water',
        'value' => 'no',
    ]);

    // Create a normal spring without false positive tags
    $normalSpring = Spring::factory()->create([
        'latitude' => 10.333333,
        'longitude' => 20.444444,
    ]);
    OSMTag::factory()->create([
        'spring_id' => $normalSpring->id,
        'key' => 'natural',
        'value' => 'spring',
    ]);
    OSMTag::factory()->create([
        'spring_id' => $normalSpring->id,
        'key' => 'drinking_water',
        'value' => 'yes',
    ]);

    // Run the cleanup
    $laundry = new Laundry();
    $laundry->cleanup();

    // Refresh models from database
    $falsePositiveSpring->refresh();
    $normalSpring->refresh();

    // Check that the false positive spring is hidden
    expect($falsePositiveSpring->hidden_at)->not->toBeNull();

    // Check that the normal spring is still visible
    expect($normalSpring->hidden_at)->toBeNull();
});
