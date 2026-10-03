<?php

declare(strict_types=1);

use App\Contracts\ReverseGeocoder;
use App\Models\OverpassBatch;
use App\Models\OverpassImport;
use App\Models\Spring;
use App\Services\Geocoding\GeocodedLocation;
use Illuminate\Support\Facades\Storage;

test('legacy XML imports geocode new and moved nodes and skip unchanged coordinates', function (): void {
    Storage::fake('local');
    $geocoder = Mockery::mock(ReverseGeocoder::class);
    $geocoder->shouldReceive('reverse')->once()->with(40.18, 44.51)
        ->andReturn(new GeocodedLocation('Armenia', 'AM', 'Yerevan'));
    $geocoder->shouldReceive('reverse')->once()->with(55.75, 37.62)
        ->andReturn(new GeocodedLocation('Russia', 'RU', 'Moscow'));
    app()->instance(ReverseGeocoder::class, $geocoder);

    $batch = new OverpassBatch();
    $batch->save();
    $import = new OverpassImport();
    $import->overpass_batch_id = $batch->id;
    $import->save();
    $import->response = '<osm><node id="993412345" lat="40.18" lon="44.51"><tag k="natural" v="spring"/></node></osm>';
    expect($import->fresh()->response)->toBe($import->response);

    $this->artisan('overpass:parse', ['id' => $import->id])->assertSuccessful();
    $spring = Spring::where('osm_node_id', 993412345)->firstOrFail();
    expect((float) $spring->latitude)->toBe(40.18)
        ->and((float) $spring->longitude)->toBe(44.51)
        ->and($spring->country_code)->toBe('AM')->and($spring->city)->toBe('Yerevan')
        ->and($spring->geocoded_at)->not->toBeNull();

    $import->response = '<osm><node id="993412345" lat="55.75" lon="37.62"><tag k="natural" v="spring"/></node></osm>';
    $import->save();
    $this->artisan('overpass:parse', ['id' => $import->id])->assertSuccessful();
    $this->artisan('overpass:parse', ['id' => $import->id])->assertSuccessful();

    expect($spring->fresh()->country_code)->toBe('RU')->and($spring->fresh()->city)->toBe('Moscow')
        ->and((float) $spring->fresh()->latitude)->toBe(55.75)
        ->and((float) $spring->fresh()->longitude)->toBe(37.62)
        ->and(Spring::where('osm_node_id', 993412345)->count())->toBe(1);
});
