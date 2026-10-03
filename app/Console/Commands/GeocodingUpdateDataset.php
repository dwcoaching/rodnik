<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Geocoding\GeocodingDatasetBuilder;
use App\Services\Geocoding\GeoJsonReverseGeocoder;
use App\Services\Geocoding\RussiaBorderPolicy;
use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class GeocodingUpdateDataset extends Command
{
    public const COUNTRIES_URL = 'https://raw.githubusercontent.com/nvkelso/natural-earth-vector/v5.1.2/geojson/ne_10m_admin_0_countries.geojson';

    public const CITIES_URL = 'https://download.geonames.org/export/dump/cities500.zip';

    public const SETTLEMENT_URLS = [
        'cities500' => self::CITIES_URL,
        'cities1000' => 'https://download.geonames.org/export/dump/cities1000.zip',
        'all' => 'https://download.geonames.org/export/dump/allCountries.zip',
    ];

    public const ADMIN1_URL = 'https://download.geonames.org/export/dump/admin1CodesASCII.txt';

    protected $signature = 'geocoding:update-dataset
        {--directory= : Destination directory (defaults to resources/geocoding)}
        {--settlements=cities500 : GeoNames catalog: cities500, cities1000, or all (all requires substantially more memory)}
        {--supplement-countries= : Full-country catalogs to merge (default RU,UA; pass an empty value to disable)}
        {--supplement-source=* : Local country ZIP instead of downloading, e.g. RU=/path/RU.zip}
        {--countries-source= : Local Natural Earth GeoJSON input instead of downloading}
        {--cities-source= : Local GeoNames ZIP for the selected settlement catalog instead of downloading}
        {--admin1-source= : Local GeoNames admin1CodesASCII.txt input instead of downloading}';

    protected $description = 'Download and normalize offline country boundaries and GeoNames settlements';

    public function handle(GeocodingDatasetBuilder $builder): int
    {
        $settlements = $this->option('settlements');
        $supplements = array_values(array_unique(array_filter(array_map(
            static fn (string $code): string => mb_strtoupper(mb_trim($code)),
            explode(',', $this->option('supplement-countries') ?? ($settlements === 'all' ? '' : 'RU,UA')),
        ))));
        $localSupplements = [];
        if (! isset(self::SETTLEMENT_URLS[$settlements])
            || array_filter($supplements, static fn (string $code): bool => preg_match('/^[A-Z]{2}$/D', $code) !== 1) !== []
            || ($settlements === 'all' && $supplements !== [])) {
            $this->error('Select cities500, cities1000, or all; supplements must be two-letter country codes and cannot be combined with all.');

            return self::INVALID;
        }
        foreach ($this->option('supplement-source') as $source) {
            if (preg_match('/^([A-Z]{2})=(.+)$/D', $source, $matches) !== 1 || ! in_array($matches[1], $supplements, true)) {
                $this->error('Local supplement sources must use CODE=/path/archive.zip and their country must be selected in --supplement-countries.');

                return self::INVALID;
            }
            $localSupplements[$matches[1]] = $matches[2];
        }
        if ($settlements === 'all') {
            $this->warn('The all-country populated-place catalog is not benchmarked by this application. Use PHP CLI memory_limit=1G for generation and validate HTTP/worker limits before configuring it. The bundled cities500 + RU,UA catalog is tested at 128M.');
        }

        $directory = $this->option('directory') ?: resource_path('geocoding');
        if (! str_starts_with($directory, DIRECTORY_SEPARATOR)) {
            $directory = base_path($directory);
        }
        File::ensureDirectoryExists($directory);
        $lockDirectory = storage_path('app');
        File::ensureDirectoryExists($lockDirectory);
        $lock = @fopen($lockDirectory.'/geocoding-update-'.hash('sha256', $directory).'.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            $this->error('Another command is updating this geocoding dataset, or its lock is unavailable.');

            return self::FAILURE;
        }
        $temporary = $directory.'/.download-'.Str::uuid();
        File::ensureDirectoryExists($temporary);

        try {
            $inputs = [
                'countries' => ['url' => self::COUNTRIES_URL, 'local' => $this->option('countries-source'), 'file' => 'countries-source.geojson'],
                'cities' => ['url' => self::SETTLEMENT_URLS[$settlements], 'local' => $this->option('cities-source'), 'file' => 'cities-source.zip'],
                'admin1' => ['url' => self::ADMIN1_URL, 'local' => $this->option('admin1-source'), 'file' => 'admin1-source.txt'],
            ];
            foreach ($supplements as $country) {
                $inputs['supplement_'.$country] = [
                    'url' => 'https://download.geonames.org/export/dump/'.$country.'.zip',
                    'local' => $localSupplements[$country] ?? null,
                    'file' => $country.'.zip',
                ];
            }
            $hashes = [];
            foreach ($inputs as $key => $input) {
                $path = $temporary.'/'.$input['file'];
                if ($local = $input['local']) {
                    if (! str_starts_with($local, DIRECTORY_SEPARATOR)) {
                        $local = base_path($local);
                    }
                    if (! @copy($local, $path)) {
                        throw new RuntimeException("Could not copy local {$key} input.");
                    }
                } else {
                    $this->line('Downloading '.$input['url']);
                    Http::connectTimeout(15)->timeout($settlements === 'all' ? 600 : 180)->retry(3, 1000)
                        ->withOptions(['sink' => $path])->get($input['url'])->throw();
                }
                $hashes[$key] = ['url' => $input['url'], 'sha256' => hash_file('sha256', $path)];
            }

            $supplementArchives = [];
            foreach ($supplements as $country) {
                $supplementArchives[$country] = $temporary.'/'.$country.'.zip';
            }
            $counts = $builder->build($temporary.'/countries-source.geojson', $temporary.'/cities-source.zip', $temporary.'/admin1-source.txt', $temporary, $settlements, $supplementArchives);
            $files = ['countries.geojson', 'settlements.txt', 'admin1CodesASCII.txt'];
            $normalized = [];
            foreach ($files as $file) {
                $normalized[$file] = ['sha256' => hash_file('sha256', $temporary.'/'.$file), 'bytes' => filesize($temporary.'/'.$file)];
            }
            $manifest = [
                'generated_at' => now()->utc()->toIso8601String(),
                'countries' => ['provider' => 'Natural Earth', 'version' => '5.1.2', 'scale' => '1:10m', 'license' => 'Public domain', 'license_url' => 'https://www.naturalearthdata.com/about/terms-of-use/'],
                'cities' => ['provider' => 'GeoNames', 'dataset' => $settlements, 'supplement_countries' => $supplements, 'scope' => match ($settlements) {
                    'cities500' => 'Population over 500 or administrative seats down to PPLA4, as supplied by GeoNames',
                    'cities1000' => 'Population over 1000 or administrative seats down to PPLA3, as supplied by GeoNames',
                    'all' => 'All populated places with no population threshold, as supplied by GeoNames',
                }, 'supplement_scope' => 'All current populated places in the selected country files, including villages with small or unknown populations', 'excluded_feature_codes' => GeocodingDatasetBuilder::EXCLUDED_SETTLEMENT_CODES, 'license' => 'CC BY 4.0', 'license_url' => 'https://creativecommons.org/licenses/by/4.0/', 'attribution_url' => 'https://www.geonames.org/', 'schema_url' => 'https://download.geonames.org/export/dump/readme.txt'],
                'inputs' => $hashes,
                'normalized_files' => $normalized,
                'counts' => $counts,
                'transformations' => ['Country labels and ISO alpha-2 codes normalized from Natural Earth; GeoNames country codes used for Somaliland and Northern Cyprus.', 'GeoNames TSV retains the standard 19 column positions; unused ASCII/alternate names and extraneous fields are emptied.', 'Historical, abandoned, destroyed settlements and sections of populated places are excluded; coordinates and display names are preserved.'],
            ];
            $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
            if (file_put_contents($temporary.'/manifest.json', $manifestJson) !== mb_strlen($manifestJson, '8bit')) {
                throw new RuntimeException('Could not write the dataset attribution manifest.');
            }

            $this->line('Validating staged geography, settlements, and the configured border policy...');
            $this->validateDataset($temporary, $temporary.'/index');

            // Complete engine validation happens before the configured files change.
            foreach ([...$files, 'manifest.json'] as $file) {
                if (! rename($temporary.'/'.$file, $directory.'/'.$file)) {
                    throw new RuntimeException("Could not publish normalized file [{$file}].");
                }
            }
            $this->line('Validating offline geography and warming its lookup index...');
            $this->validateDataset($directory, storage_path('framework/cache/geocoding'));
            $this->info('Installed '.$counts['countries'].' country areas, '.$counts['cities'].' settlements, and '.$counts['admin1_regions'].' administrative names.');
            $this->line('Dataset directory: '.$directory);
            $this->line('Run springs:geocode to update saved country and city labels.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($temporary);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function validateDataset(string $directory, string $cacheDirectory): void
    {
        $settings = config('geocoding', []);
        $settings['path'] = $directory.'/countries.geojson';
        $settings['cities_path'] = $directory.'/settlements.txt';
        $settings['admin1_path'] = $directory.'/admin1CodesASCII.txt';
        $settings['index_cache_path'] = $cacheDirectory;
        $settings['properties'] = [];
        $config = new Repository(['geocoding' => $settings]);
        (new RussiaBorderPolicy(new GeoJsonReverseGeocoder($config), $config))->validateSource();
    }
}
