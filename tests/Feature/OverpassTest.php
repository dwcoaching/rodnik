<?php

use App\Library\Overpass;
use App\Models\Spring;
use App\Models\SpringRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('spring revision is created', function () {
    $json = file_get_contents(base_path('tests/stubs/overpass.json'));

    $result = Overpass::parse(json_decode($json));

    // Find the spring with the OSM node ID from our test data
    $spring = Spring::where('osm_node_id', 7600556407)->first();
    
    // Get the revision for that spring
    $springRevision = SpringRevision::where('spring_id', $spring->id)
        ->orderBy('id', 'desc')->first();

    expect($springRevision)->not->toBeNull();
    expect(55.655136)->toEqual($springRevision->old_latitude);
    expect(36.709845)->toEqual($springRevision->old_longitude);
    expect(55.655135)->toEqual($springRevision->new_latitude);
    expect(36.709844)->toEqual($springRevision->new_longitude);
    expect('Родник святого Дионисия')->toEqual($springRevision->new_name);
});
