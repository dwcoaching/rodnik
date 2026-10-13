<?php

declare(strict_types=1);

use App\Models\OverpassImport;

test('Overpass query requests meta metadata on nodes and ways', function () {
    $import = new OverpassImport();
    $import->latitude_from = 0;
    $import->latitude_to = 1;
    $import->longitude_from = 0;
    $import->longitude_to = 1;

    $query = $import->query;

    expect($query)->toContain('out meta center;');
    expect($query)->not->toMatch('/\bout;\s/');
    expect($query)->not->toMatch('/\bout center;/');
});

test('Overpass query declares a modest timeout and memory size so a busy server admits it', function () {
    $import = new OverpassImport();
    $import->latitude_from = 0;
    $import->latitude_to = 1;
    $import->longitude_from = 0;
    $import->longitude_to = 1;

    expect($import->query)->toContain('[out:json][timeout:120][maxsize:134217728];');
});
