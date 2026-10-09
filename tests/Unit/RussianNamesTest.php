<?php

declare(strict_types=1);

use App\Services\Geography\GeographyCatalogReader;
use App\Services\Geography\GeographySlug;
use App\Services\Geography\RussianNameLookup;
use App\Services\Geography\RussianNamesBuilder;

function russianNamesFixture(array $rows): array
{
    $directory = sys_get_temp_dir().'/rodnik-russian-names-test-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $archive = new ZipArchive();
    $archive->open($directory.'/input.zip', ZipArchive::CREATE);
    $archive->addFromString('alternateNamesV2.txt', implode("\n", $rows)."\n");
    $archive->close();

    return [$directory, $directory.'/input.zip', $directory.'/russian-names.tsv'];
}

function russianNameRow(int $alternateId, int $id, string $name, string $language = 'ru', string $preferred = '', string $short = '', string $colloquial = '', string $historic = '', string $to = ''): string
{
    return implode("\t", [$alternateId, $id, $language, $name, $preferred, $short, $colloquial, $historic, '', $to]);
}

function deleteRussianNamesFixture(string $directory): void
{
    foreach (glob($directory.'/*') as $file) {
        unlink($file);
    }
    rmdir($directory);
}

test('Russian names prefer current official short labels and reject historic colloquial and ended names', function () {
    [$directory, $archive, $output] = russianNamesFixture([
        russianNameRow(1, 101, 'Old preferred', preferred: '1', historic: '1'),
        russianNameRow(2, 101, 'Slang', preferred: '1', colloquial: '1'),
        russianNameRow(3, 101, 'English', language: 'en', preferred: '1'),
        russianNameRow(4, 101, 'Старое', preferred: '1', to: '2000'),
        russianNameRow(5, 101, 'Название'),
        russianNameRow(6, 101, 'Официальное', preferred: '1'),
        russianNameRow(7, 101, 'Краткое', preferred: '1', short: '1'),
        russianNameRow(8, 101, 'Позднее', preferred: '1', short: '1'),
        russianNameRow(9, 102, 'Без перевода', language: 'uk'),
        russianNameRow(10, 103, 'Деревня'),
    ]);
    try {
        expect((new RussianNamesBuilder())->build($archive, $output))->toBe(2)
            ->and(file_get_contents($output))->toBe("101\tКраткое\n103\tДеревня\n");
        $lookup = new RussianNameLookup($output, $directory);
        expect($lookup->names([101, 102, 103, 101]))->toBe([101 => 'Краткое', 103 => 'Деревня']);
    } finally {
        deleteRussianNamesFixture($directory);
    }
});

test('Russian name lookup invalidates when a replacement file has the same size and modification time', function () {
    [$directory, $archive, $output] = russianNamesFixture([russianNameRow(1, 101, 'Москва')]);
    try {
        (new RussianNamesBuilder())->build($archive, $output);
        $mtime = filemtime($output);
        expect((new RussianNameLookup($output, $directory))->names([101]))->toBe([101 => 'Москва']);
        file_put_contents($output, "101\tКалуга\n");
        touch($output, $mtime);
        expect((new RussianNameLookup($output, $directory))->names([101]))->toBe([101 => 'Калуга']);
    } finally {
        deleteRussianNamesFixture($directory);
    }
});

test('invalid alternate names and normalized names fail instead of creating a partial lookup', function () {
    [$directory, $archive, $output] = russianNamesFixture(['not a record']);
    try {
        expect(fn () => (new RussianNamesBuilder())->build($archive, $output))->toThrow(RuntimeException::class);
        file_put_contents($output, "broken\n");
        expect(fn () => new RussianNameLookup($output, $directory))->toThrow(RuntimeException::class);
    } finally {
        deleteRussianNamesFixture($directory);
    }
});

test('catalog readers retain provider identifiers and reject invalid settlement coordinates', function () {
    $directory = sys_get_temp_dir().'/rodnik-catalog-reader-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $path = $directory.'/cities.txt';
    $columns = array_fill(0, 19, '');
    foreach ([0 => '123', 1 => 'Town', 4 => '0', 5 => '0', 6 => 'P', 7 => 'PPL', 8 => 'RU', 10 => '48', 14 => '12'] as $key => $value) {
        $columns[$key] = $value;
    }
    try {
        file_put_contents($path, implode("\t", $columns)."\n");
        $rows = iterator_to_array((new GeographyCatalogReader())->cities($path));
        expect($rows[0])->toMatchArray(['geonames_id' => 123, 'source_country_code' => 'RU', 'admin1_code' => '48', 'latitude' => 0.0, 'longitude' => 0.0]);
        $columns[4] = '91';
        file_put_contents($path, implode("\t", $columns)."\n");
        expect(fn () => iterator_to_array((new GeographyCatalogReader())->cities($path)))->toThrow(RuntimeException::class);
    } finally {
        deleteRussianNamesFixture($directory);
    }
});

test('identical names produce distinct URL slugs for different stable place identities', function () {
    expect(GeographySlug::make('Springfield', '100'))->not->toBe(GeographySlug::make('Springfield', '200'))
        ->and(GeographySlug::make('Москва', '524901'))->toBe('moskva-524901');
});
