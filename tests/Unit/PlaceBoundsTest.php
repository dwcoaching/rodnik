<?php

declare(strict_types=1);

use App\Services\Geography\PlaceBounds;

/** @return list<list<array{0:float,1:float}>> */
function boundsPolygon(float $west, float $south, float $east, float $north): array
{
    return [[[$west, $south], [$east, $south], [$east, $north], [$west, $north], [$west, $south]]];
}

/** @param list<array{0:float,1:float,2:float,3:float}> $boxes */
function boundsOf(array $boxes): ?array
{
    return PlaceBounds::fromGeometry([
        'type' => 'MultiPolygon',
        'coordinates' => array_map(static fn (array $box): array => boundsPolygon(...$box), $boxes),
    ]);
}

test('a nearby large island extends the mainland box while a big remote territory does not', function () {
    // Mainland France, Corsica (~1.6% of its area), and French Guiana (~15%, 60 degrees away).
    $bounds = boundsOf([[-54.6, 2.1, -51.0, 5.8], [-5.0, 42.0, 8.0, 51.0], [8.5, 41.3, 9.6, 43.0]]);

    expect($bounds)->toEqualWithDelta([-5.0, 41.3, 9.6, 51.0], 1e-9);
});

test('a chain of significant islands joins the main island while tiny islets never extend the box', function () {
    // Kauai only touches the box once Oahu has joined it, so the order is deliberately reversed.
    $bounds = boundsOf([
        [-166.29, 23.865, -166.27, 23.875], // ~0.01% islet far to the northwest
        [-160.6, 21.8, -160.58, 21.81], // ~0.01% islet within reach of Kauai
        [-159.8, 21.87, -159.3, 22.23],
        [-158.3, 21.25, -157.65, 21.71],
        [-156.7, 20.57, -155.98, 21.03],
        [-156.1, 18.9, -154.8, 20.3],
    ]);

    expect($bounds)->toEqualWithDelta([-159.8, 18.9, -154.8, 22.23], 1e-9);
});

test('two large parts within half the main part diagonal are both included', function () {
    // Borneo's diagonal is ~11.7 degrees, so parts within ~5.85 degrees join it.
    expect(boundsOf([[99.6, 1.2, 104.5, 6.7], [109.6, 0.85, 119.3, 7.4]]))->toEqualWithDelta([99.6, 0.85, 119.3, 7.4], 1e-9)
        ->and(boundsOf([[97.6, 1.2, 102.5, 6.7], [109.6, 0.85, 119.3, 7.4]]))->toEqualWithDelta([109.6, 0.85, 119.3, 7.4], 1e-9);
});

test('a remote significant island beyond the threshold is excluded', function () {
    // Mainland Chile has a threshold of ~19.5 degrees; Easter Island (~1%) is ~29 degrees away.
    expect(boundsOf([[-111.0, -28.0, -109.3, -26.3], [-75.0, -56.0, -67.0, -17.5]]))
        ->toEqualWithDelta([-75.0, -56.0, -67.0, -17.5], 1e-9);
});

test('parts split at the antimeridian form one continuous box', function () {
    // Chukotka is split into [160, 180] and [-180, -169].
    expect(boundsOf([[160.0, 62.0, 180.0, 70.0], [-180.0, 64.0, -169.0, 68.0]]))
        ->toEqualWithDelta([160.0, 62.0, 191.0, 70.0], 1e-9)
        ->and(boundsOf([[-180.0, 64.0, -169.0, 68.0], [160.0, 62.0, 180.0, 70.0]]))
        ->toEqualWithDelta([160.0, 62.0, 191.0, 70.0], 1e-9);
});

test('islands straddling the antimeridian keep west within range and east beyond 180', function () {
    // Fiji: Viti Levu, Vanua Levu, Taveuni's eastern half, and a Lau island.
    expect(boundsOf([
        [-178.9, -18.3, -178.7, -18.1],
        [177.2, -18.3, 178.7, -17.3],
        [-180.0, -16.9, -179.8, -16.6],
        [178.4, -17.0, 180.0, -16.1],
    ]))->toEqualWithDelta([177.2, -18.3, 181.3, -16.1], 1e-9);

    // The largest part sits west of the antimeridian here, so the box is shifted back into range.
    expect(boundsOf([[-180.0, -18.0, -175.0, -14.0], [178.5, -17.0, 180.0, -15.5]]))
        ->toEqualWithDelta([178.5, -18.0, 185.0, -14.0], 1e-9);
});

test('a single ring wider than 180 degrees is read as crossing the antimeridian', function () {
    $ring = [[178.0, -17.0], [-178.0, -17.0], [-178.0, -16.0], [178.0, -16.0], [178.0, -17.0]];

    expect(PlaceBounds::fromGeometry(['type' => 'Polygon', 'coordinates' => [$ring]]))
        ->toEqualWithDelta([178.0, -17.0, 182.0, -16.0], 1e-9);
});

test('a ring around the whole globe covers every longitude', function () {
    $ring = [[-180.0, -90.0], [-180.0, -63.0], [-90.0, -64.0], [-0.1, -70.0], [0.1, -70.0], [90.0, -66.0], [180.0, -63.0], [180.0, -90.0], [-180.0, -90.0]];

    expect(PlaceBounds::fromGeometry(['type' => 'Polygon', 'coordinates' => [$ring]]))
        ->toEqualWithDelta([-180.0, -90.0, 180.0, -63.0], 1e-9);
});

test('holes are ignored and only outer rings define parts', function () {
    $parts = PlaceBounds::parts(['type' => 'Polygon', 'coordinates' => [
        boundsPolygon(0, 0, 4, 2)[0],
        boundsPolygon(1, 0.5, 2, 1.5)[0],
    ]]);

    expect($parts)->toHaveCount(1)
        ->and($parts[0])->toMatchArray(['west' => 0.0, 'south' => 0.0, 'east' => 4.0, 'north' => 2.0])
        ->and($parts[0]['area'])->toEqualWithDelta(8 * cos(deg2rad(1)), 1e-9);
});

test('empty and invalid geometry has no bounds', function (array $geometry) {
    expect(PlaceBounds::fromGeometry($geometry))->toBeNull();
})->with([
    'point' => [['type' => 'Point', 'coordinates' => [10, 10]]],
    'empty multipolygon' => [['type' => 'MultiPolygon', 'coordinates' => []]],
    'missing coordinates' => [['type' => 'Polygon']],
    'invalid positions' => [['type' => 'Polygon', 'coordinates' => [[['a', 'b'], [400, 10], [10, 95], null]]]],
]);

test('an archipelago whose largest island is tiny joins islands up to 5 degrees apart', function () {
    // Austral Islands: Rapa (~40 km²) is the largest; Raivavae is ~4.8 degrees away, then Tubuai, Rurutu, and Rimatara.
    expect(boundsOf([
        [-152.85, -22.66, -152.78, -22.6],
        [-151.37, -22.54, -151.32, -22.43],
        [-149.55, -23.4, -149.42, -23.33],
        [-147.72, -23.89, -147.62, -23.85],
        [-144.38, -27.64, -144.28, -27.55],
    ]))->toEqualWithDelta([-152.85, -27.64, -144.28, -22.43], 1e-9);

    // A largest island of over 1,000 km² keeps the 2 degree floor.
    expect(boundsOf([[-144.6, -27.9, -144.0, -27.3], [-147.72, -23.89, -147.62, -23.85]]))
        ->toEqualWithDelta([-144.6, -27.9, -144.0, -27.3], 1e-9);
});

test('the distance threshold grows with the box as significant parts join', function () {
    // The far island is 4.5 degrees from the box, beyond half of either island's diagonal but within half of theirs together.
    expect(boundsOf([[150.0, -10.0, 155.0, -6.0], [156.0, -10.0, 160.0, -6.5], [164.5, -11.0, 165.5, -10.0]]))
        ->toEqualWithDelta([150.0, -11.0, 165.5, -6.0], 1e-9)
        ->and(boundsOf([[150.0, -10.0, 155.0, -6.0], [164.5, -11.0, 165.5, -10.0]]))
        ->toEqualWithDelta([150.0, -10.0, 155.0, -6.0], 1e-9);
});

test('small islands next to the largest part extend the box', function () {
    // Florida: the Keys and Key West are each far below 0.5% of the mainland but within a tenth of its diagonal.
    expect(boundsOf([
        [-87.63, 25.12, -80.03, 31.0],
        [-80.5, 24.95, -80.3, 25.2],
        [-81.81, 24.54, -81.73, 24.6],
        [-82.0, 23.0, -81.9, 23.05], // ~2 degrees south of the mainland, beyond its reach
    ]))->toEqualWithDelta([-87.63, 24.54, -80.03, 31.0], 1e-9);
});

test('a chain of tiny islands does not lead the box across the antimeridian', function () {
    // Alaska's mainland reaches ~2.4 degrees, so only the first three islets join. The chain continues
    // one islet per degree past the antimeridian to Attu, which islets extending the reach would pull in.
    $chain = array_map(static function (int $step): array {
        $west = -168.5 - $step;
        $west += $west < -180 ? 360 : 0;

        return [$west, 52.5, $west + 0.3, 52.8];
    }, range(0, 18));
    $chain[] = [172.5, 52.7, 173.3, 53.0];

    expect(boundsOf([[-168.1, 54.6, -130.0, 71.4], ...$chain]))
        ->toEqualWithDelta([-170.5, 52.5, -130.0, 71.4], 1e-9);
});

test('settlements cluster around the most populous one without remote outposts', function () {
    // Svalbard and Jan Mayen: the Jan Mayen station is ~10 degrees from Longyearbyen.
    expect(PlaceBounds::fromSettlements([[70.9221, -8.7187, 14], [78.2233, 15.6469, 2060]]))
        ->toEqualWithDelta([15.6469, 78.2233, 15.6469, 78.2233], 1e-9);

    // Da Nang: the city and its towns, while the unpopulated Paracels outpost lies 4.4 degrees offshore.
    expect(PlaceBounds::fromSettlements([
        [16.0678, 108.2208, 752493],
        [15.98, 108.25, 20000],
        [16.12, 107.99, 0],
        [16.2, 108.13, 10000],
        [16.5, 112.725, 0],
    ]))->toEqualWithDelta([107.99, 15.98, 108.25, 16.2], 1e-9);
});

test('villages of any size extend the box through their neighbours', function () {
    // England: Cornish villages are each under 0.01% of London, but a chain of them reaches Land's End.
    $villages = array_map(static fn (int $step): array => [51.4 - 0.25 * $step, -1.0 - 0.75 * $step, $step % 2 === 0 ? 0 : 400], range(0, 6));

    expect(PlaceBounds::fromSettlements([[51.5074, -0.1278, 8961989], ...$villages]))
        ->toEqualWithDelta([-5.5, 49.9, -0.1278, 51.5074], 1e-9);
});

test('a misfiled city far from the other settlements does not take over the box', function () {
    // Zanzan: Sinfra is filed under the district but lies ~3 degrees away and outweighs every single town in it.
    expect(PlaceBounds::fromSettlements([
        [6.621, -5.911, 245226],
        [8.04, -2.8, 141568],
        [9.81, -3.35, 48225],
        [7.8, -3.17, 35854],
        [9.27, -3.0, 24312],
        [7.49, -3.25, 7114],
    ]))->toEqualWithDelta([-3.35, 7.49, -2.8, 9.81], 1e-9);
});

test('settlements across the antimeridian form one continuous box', function () {
    expect(PlaceBounds::fromSettlements([[-17.5, 179.5, 5000], [-16.5, -179.0, 3000], [64.7, 177.5, 0]]))
        ->toEqualWithDelta([179.5, -17.5, 181.0, -16.5], 1e-9)
        ->and(PlaceBounds::fromSettlements([[-16.5, -179.0, 5000], [-17.5, 179.5, 3000]]))
        ->toEqualWithDelta([179.5, -17.5, 181.0, -16.5], 1e-9)
        ->and(PlaceBounds::fromSettlements([[10.0, 20.0, 0]]))->toEqualWithDelta([20.0, 10.0, 20.0, 10.0], 1e-9)
        ->and(PlaceBounds::fromSettlements([]))->toBeNull();
});
