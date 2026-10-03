<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Geography\GeographyCatalogReader;
use App\Services\Geography\RussianNamesBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class GeocodingUpdateNames extends Command
{
    public const ALTERNATE_NAMES_URL = 'https://download.geonames.org/export/dump/alternateNamesV2.zip';

    public const COUNTRY_INFO_URL = 'https://download.geonames.org/export/dump/countryInfo.txt';

    protected $signature = 'geocoding:update-names
        {--directory= : Destination directory (defaults to resources/geocoding)}
        {--names-source= : Local alternateNamesV2.zip instead of downloading}
        {--country-info-source= : Local countryInfo.txt instead of downloading}';

    protected $description = 'Download Russian country, region, and settlement names from GeoNames';

    public function handle(RussianNamesBuilder $builder, GeographyCatalogReader $reader): int
    {
        $directory = $this->option('directory') ?: resource_path('geocoding');
        if (! str_starts_with($directory, DIRECTORY_SEPARATOR)) {
            $directory = base_path($directory);
        }
        File::ensureDirectoryExists($directory);
        File::ensureDirectoryExists(storage_path('app'));
        $lock = @fopen(storage_path('app/geography-names-'.hash('sha256', $directory).'.lock'), 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            $this->error('Another process is updating geography names, or its lock is unavailable.');

            return self::FAILURE;
        }
        $temporary = $directory.'/.names-'.Str::uuid();
        File::ensureDirectoryExists($temporary);
        try {
            $inputs = [
                'alternate_names' => ['url' => self::ALTERNATE_NAMES_URL, 'local' => $this->option('names-source'), 'file' => 'alternateNamesV2.zip'],
                'country_info' => ['url' => self::COUNTRY_INFO_URL, 'local' => $this->option('country-info-source'), 'file' => 'countryInfo.txt'],
            ];
            $hashes = [];
            foreach ($inputs as $key => $input) {
                $path = $temporary.'/'.$input['file'];
                if ($local = $input['local']) {
                    $local = str_starts_with($local, DIRECTORY_SEPARATOR) ? $local : base_path($local);
                    if (! @copy($local, $path)) {
                        throw new RuntimeException("Could not copy local {$key} input.");
                    }
                } else {
                    $this->line('Downloading '.$input['url']);
                    Http::connectTimeout(15)->timeout(600)->retry(3, 1000)->withOptions(['sink' => $path])->get($input['url'])->throw();
                }
                $hashes[$key] = ['url' => $input['url'], 'sha256' => hash_file('sha256', $path)];
            }
            $this->line('Selecting current Russian display names...');
            $count = $builder->build($temporary.'/alternateNamesV2.zip', $temporary.'/russian-names.tsv');
            $countryCount = iterator_count($reader->countries($temporary.'/countryInfo.txt'));
            if ($countryCount === 0) {
                throw new RuntimeException('The country catalog is empty.');
            }
            $normalized = [];
            foreach (['countryInfo.txt', 'russian-names.tsv'] as $file) {
                $normalized[$file] = ['sha256' => hash_file('sha256', $temporary.'/'.$file), 'bytes' => filesize($temporary.'/'.$file)];
            }
            $manifest = [
                'generated_at' => now()->utc()->toIso8601String(), 'provider' => 'GeoNames', 'license' => 'CC BY 4.0',
                'license_url' => 'https://creativecommons.org/licenses/by/4.0/', 'attribution_url' => 'https://www.geonames.org/',
                'schema_url' => 'https://download.geonames.org/export/dump/readme.txt', 'inputs' => $hashes, 'normalized_files' => $normalized,
                'counts' => ['russian_names' => $count, 'countries' => $countryCount],
                'selection' => 'Language ru; colloquial, historic, and ended-period names excluded; preferred names ranked first, then short names; lowest alternate-name identifier breaks ties.',
                'coverage' => 'Russian names are supplied where GeoNames has a current Russian alternate name. Application display falls back to the original name.',
            ];
            File::put($temporary.'/russian-names.manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            foreach (['countryInfo.txt', 'russian-names.tsv', 'russian-names.manifest.json'] as $file) {
                if (! rename($temporary.'/'.$file, $directory.'/'.$file)) {
                    throw new RuntimeException("Could not publish geography name file [{$file}].");
                }
            }
            $this->info("Installed {$count} current Russian names and {$countryCount} country identities.");
            $this->line('Run geocoding:import-catalog to update names in the database.');

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
}
