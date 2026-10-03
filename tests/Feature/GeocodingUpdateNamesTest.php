<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->nameDirectory = sys_get_temp_dir().'/rodnik-name-command-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->nameDirectory);
    $this->nameArchive = $this->nameDirectory.'/input.zip';
    $this->countryInfo = $this->nameDirectory.'/countryInfo.txt';
    $archive = new ZipArchive();
    $archive->open($this->nameArchive, ZipArchive::CREATE);
    $archive->addFromString('alternateNamesV2.txt', "1\t123\tru\tМосква\t1\t1\t\t\t\t\n");
    $archive->close();
    $country = array_fill(0, 19, '');
    $country[0] = 'RU';
    $country[4] = 'Russia';
    $country[16] = '2017370';
    file_put_contents($this->countryInfo, implode("\t", $country)."\n");
});

afterEach(function () {
    File::deleteDirectory($this->nameDirectory);
});

test('the name updater publishes language-tagged labels and reproducible license metadata', function () {
    $this->artisan('geocoding:update-names', [
        '--directory' => $this->nameDirectory.'/output', '--names-source' => $this->nameArchive, '--country-info-source' => $this->countryInfo,
    ])->assertSuccessful();
    $manifest = json_decode(file_get_contents($this->nameDirectory.'/output/russian-names.manifest.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(file_get_contents($this->nameDirectory.'/output/russian-names.tsv'))->toBe("123\tМосква\n")
        ->and($manifest['license'])->toBe('CC BY 4.0')
        ->and($manifest['counts'])->toBe(['russian_names' => 1, 'countries' => 1])
        ->and($manifest['inputs']['alternate_names']['sha256'])->toBe(hash_file('sha256', $this->nameArchive));
});

test('invalid country identity data prevents replacing existing name files', function () {
    File::ensureDirectoryExists($this->nameDirectory.'/output');
    file_put_contents($this->nameDirectory.'/output/russian-names.tsv', 'existing names');
    file_put_contents($this->nameDirectory.'/output/countryInfo.txt', 'existing countries');
    file_put_contents($this->countryInfo, 'invalid country record');

    $this->artisan('geocoding:update-names', [
        '--directory' => $this->nameDirectory.'/output', '--names-source' => $this->nameArchive, '--country-info-source' => $this->countryInfo,
    ])->assertFailed();

    expect(file_get_contents($this->nameDirectory.'/output/russian-names.tsv'))->toBe('existing names')
        ->and(file_get_contents($this->nameDirectory.'/output/countryInfo.txt'))->toBe('existing countries')
        ->and(is_file($this->nameDirectory.'/output/russian-names.manifest.json'))->toBeFalse();
});
