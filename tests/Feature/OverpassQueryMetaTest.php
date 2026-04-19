<?php

use App\Models\OverpassImport;

test('Overpass query requests meta metadata on nodes and ways', function () {
    $import = new OverpassImport();
    $import->latitude_from = 0;
    $import->latitude_to = 1;
    $import->longitude_from = 0;
    $import->longitude_to = 1;

    $query = $import->query;

    expect($query)->toContain('out meta;');
    expect($query)->toContain('out meta center;');
    expect($query)->not->toMatch('/\bout;\s/');
    expect($query)->not->toMatch('/\bout center;/');
});
