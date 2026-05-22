<?php

use App\Library\HaversineDistance;

test('it calculates and formats haversine distance in meters', function () {
    $distance = new HaversineDistance();

    expect((int) round($distance->metersBetweenCoordinates(55, 37, 55.001, 37)))->toBe(111);
    expect($distance->formatMeters(111.49))->toBe('111 m');
    expect($distance->formatMeters(null))->toBeNull();
});
