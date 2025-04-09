<?php

use App\Library\Overpass;
use App\Models\SpringRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('spring revision is created', function () {
    $json = file_get_contents(base_path('tests/stubs/overpass.json'));

    Overpass::parse(json_decode($json));

    $springRevision = SpringRevision::where('spring_id', 1)
        ->orderBy('id', 'desc')->first();

    expect($springRevision)->not->toBeNull();
    expect(55.655136)->toEqual($springRevision->old_latitude);
    expect(36.709845)->toEqual($springRevision->old_longitude);
    expect(55.655135)->toEqual($springRevision->new_latitude);
    expect(36.709844)->toEqual($springRevision->new_longitude);
    expect('Родник святого Дионисия')->toEqual($springRevision->new_name);
});
