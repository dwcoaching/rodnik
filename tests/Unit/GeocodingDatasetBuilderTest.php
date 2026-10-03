<?php

declare(strict_types=1);

use App\Services\Geocoding\GeocodingDatasetBuilder;

function geocodingBuilderFixture(array $cities, string $member = 'cities1000.txt'): array
{
    $directory = sys_get_temp_dir().'/rodnik-dataset-builder-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $countries = $directory.'/input.geojson';
    file_put_contents($countries, json_encode([
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'properties' => ['NAME_EN' => 'Example', 'NAME_RU' => 'Пример', 'ISO_A2' => '-99', 'ISO_A2_EH' => 'AA'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [5, 0], [5, 5], [0, 5], [0, 0]]]],
        ]],
    ], JSON_THROW_ON_ERROR));
    $archivePath = $directory.'/input.zip';
    $archive = new ZipArchive();
    $archive->open($archivePath, ZipArchive::CREATE);
    $archive->addFromString($member, implode("\n", $cities)."\n");
    $archive->close();
    $admin1 = $directory.'/input-admin1.txt';
    file_put_contents($admin1, "AA.01\tExample Province\tExample Province\t123\n");

    return [$directory, $countries, $archivePath, $admin1];
}

function geocodingBuilderCity(string $featureCode = 'PPL', string $latitude = '1', string $id = '123', string $country = 'AA', string $population = '2000', string $name = 'Город', string $featureClass = 'P'): string
{
    $columns = array_fill(0, 19, '');
    foreach ([0 => $id, 1 => $name, 2 => 'ASCII name', 3 => 'alternate,other', 4 => $latitude, 5 => '2', 6 => $featureClass, 7 => $featureCode, 8 => $country, 10 => '01', 14 => $population, 18 => '2026-10-02'] as $key => $value) {
        $columns[$key] = $value;
    }

    return implode("\t", $columns);
}

function deleteGeocodingBuilderFixture(string $directory): void
{
    foreach (glob($directory.'/*') as $file) {
        unlink($file);
    }
    rmdir($directory);
}

test('offline dataset normalization preserves useful GeoNames columns and excludes inactive settlements and districts', function () {
    [$directory, $countries, $archive, $admin1] = geocodingBuilderFixture([
        geocodingBuilderCity('PPLC'),
        geocodingBuilderCity('PPLX'),
        geocodingBuilderCity('PPLQ'),
        geocodingBuilderCity('PPLH'),
        geocodingBuilderCity('PPLCH'),
        geocodingBuilderCity('PPLW'),
    ]);
    try {
        $counts = (new GeocodingDatasetBuilder())->build($countries, $archive, $admin1, $directory);
        $normalized = json_decode(file_get_contents($directory.'/countries.geojson'), true, flags: JSON_THROW_ON_ERROR);
        $city = explode("\t", mb_rtrim(file_get_contents($directory.'/settlements.txt'), "\r\n"));

        expect($counts)->toBe(['countries' => 1, 'cities' => 1, 'admin1_regions' => 1])
            ->and($normalized['features'][0]['properties'])->toBe(['country' => 'Example', 'country_ru' => 'Пример', 'country_code' => 'AA'])
            ->and($city)->toHaveCount(19)
            ->and($city[0])->toBe('123')
            ->and($city[1])->toBe('Город')
            ->and($city[2])->toBe('')
            ->and($city[3])->toBe('')
            ->and($city[4])->toBe('1')
            ->and($city[5])->toBe('2')
            ->and($city[7])->toBe('PPLC')
            ->and($city[8])->toBe('AA')
            ->and($city[10])->toBe('01')
            ->and($city[14])->toBe('2000')
            ->and(file_get_contents($directory.'/admin1CodesASCII.txt'))->toBe("AA.01\tExample Province\tExample Province\t123\n");
    } finally {
        deleteGeocodingBuilderFixture($directory);
    }
});

test('different settlement catalogs retain current populated places with zero or unknown population', function (string $catalog, string $member) {
    [$directory, $countries, $archive, $admin1] = geocodingBuilderFixture([
        geocodingBuilderCity(id: '1', population: '0'),
        geocodingBuilderCity(id: '2', population: ''),
        geocodingBuilderCity(id: '3', featureCode: 'STM', featureClass: 'H'),
    ], $member);
    try {
        $counts = (new GeocodingDatasetBuilder())->build($countries, $archive, $admin1, $directory, $catalog);
        expect($counts['cities'])->toBe(2)
            ->and(count(file($directory.'/settlements.txt')))->toBe(2);
    } finally {
        deleteGeocodingBuilderFixture($directory);
    }
})->with([
    ['cities500', 'cities500.txt'],
    ['cities1000', 'cities1000.txt'],
    ['all', 'allCountries.txt'],
]);

test('full-country supplements add small villages and deduplicate GeoNames IDs against the base catalog', function () {
    [$directory, $countries, $archive, $admin1] = geocodingBuilderFixture([
        geocodingBuilderCity(id: '1', country: 'RU', name: 'Base city'),
        geocodingBuilderCity(id: '2', country: 'AA', name: 'Other country'),
    ], 'cities500.txt');
    $supplement = new ZipArchive();
    $supplement->open($directory.'/RU.zip', ZipArchive::CREATE);
    $supplement->addFromString('RU.txt', implode("\n", [
        geocodingBuilderCity(id: '1', country: 'RU', name: 'Duplicate city'),
        geocodingBuilderCity(id: '3', country: 'RU', population: '0', name: 'Small village'),
        geocodingBuilderCity(id: '4', country: 'RU', population: '', name: 'Unknown population'),
        geocodingBuilderCity(id: '5', country: 'RU', featureCode: 'PPLQ'),
    ])."\n");
    $supplement->close();
    try {
        $counts = (new GeocodingDatasetBuilder())->build($countries, $archive, $admin1, $directory, 'cities500', ['RU' => $directory.'/RU.zip']);
        $rows = array_map(fn (string $line): array => explode("\t", mb_rtrim($line, "\r\n")), file($directory.'/settlements.txt'));
        expect($counts['cities'])->toBe(4)
            ->and(array_column($rows, 0))->toBe(['1', '2', '3', '4'])
            ->and(array_column($rows, 1))->toBe(['Base city', 'Other country', 'Small village', 'Unknown population']);
    } finally {
        deleteGeocodingBuilderFixture($directory);
    }
});

test('supplement archives cannot silently add settlements from a different country', function () {
    [$directory, $countries, $archive, $admin1] = geocodingBuilderFixture([geocodingBuilderCity()], 'cities500.txt');
    $supplement = new ZipArchive();
    $supplement->open($directory.'/RU.zip', ZipArchive::CREATE);
    $supplement->addFromString('RU.txt', geocodingBuilderCity(id: '2', country: 'UA')."\n");
    $supplement->close();
    try {
        expect(fn () => (new GeocodingDatasetBuilder())->build($countries, $archive, $admin1, $directory, 'cities500', ['RU' => $directory.'/RU.zip']))
            ->toThrow(RuntimeException::class, 'another country');
    } finally {
        deleteGeocodingBuilderFixture($directory);
    }
});

test('all-country catalog refuses redundant supplements and unsupported catalog choices', function () {
    $builder = new GeocodingDatasetBuilder();
    expect(fn () => $builder->build('', '', '', '', 'unknown'))->toThrow(RuntimeException::class, 'Settlement catalog')
        ->and(fn () => $builder->build('', '', '', '', 'all', ['RU' => 'RU.zip']))->toThrow(RuntimeException::class, 'already includes');
});

test('offline dataset normalization rejects malformed city coordinates before publishing a dataset', function () {
    [$directory, $countries, $archive, $admin1] = geocodingBuilderFixture([geocodingBuilderCity(latitude: 'not a coordinate')]);
    try {
        expect(fn () => (new GeocodingDatasetBuilder())->build($countries, $archive, $admin1, $directory))
            ->toThrow(RuntimeException::class, 'Invalid coordinates');
    } finally {
        deleteGeocodingBuilderFixture($directory);
    }
});

test('offline dataset normalization rejects archives without the documented city member', function () {
    [$directory, $countries, $archivePath, $admin1] = geocodingBuilderFixture([geocodingBuilderCity()]);
    $archive = new ZipArchive();
    $archive->open($archivePath);
    $archive->renameName('cities1000.txt', 'unrelated.txt');
    $archive->close();
    try {
        expect(fn () => (new GeocodingDatasetBuilder())->build($countries, $archivePath, $admin1, $directory))
            ->toThrow(RuntimeException::class, 'must contain cities1000.txt');
    } finally {
        deleteGeocodingBuilderFixture($directory);
    }
});
