<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Geography\GeographyCatalogImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class GeocodingImportCatalog extends Command
{
    protected $signature = 'geocoding:import-catalog
        {--country-info= : GeoNames countryInfo.txt (defaults to configured path)}
        {--russian-names= : Normalized Russian names TSV (defaults to configured path)}
        {--countries= : Normalized country GeoJSON (defaults to configured path)}
        {--admin1= : GeoNames admin1CodesASCII.txt (defaults to configured path)}
        {--cities= : Normalized GeoNames settlements TSV (defaults to configured path)}
        {--regions= : Normalized region GeoJSON (defaults to configured path)}';

    protected $description = 'Import stable country, region, and settlement identities and Russian names';

    public function handle(GeographyCatalogImporter $importer): int
    {
        File::ensureDirectoryExists(storage_path('app'));
        $lock = @fopen(storage_path('app/geography-catalog-import.lock'), 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            $this->error('Another process is importing the geography catalog, or its lock is unavailable.');

            return self::FAILURE;
        }
        try {
            if (DB::table('geocoding_runs')->whereNotNull('active_key')->exists()) {
                throw new RuntimeException('Finish or stop the active reverse geocoding run before importing the catalog.');
            }

            DB::connection()->disableQueryLog();
            $paths = [
                'country_info' => $this->path($this->option('country-info') ?: config('geography.country_info_path')),
                'russian_names' => $this->path($this->option('russian-names') ?: config('geography.russian_names_path')),
                'countries' => $this->path($this->option('countries') ?: config('geocoding.path')),
                'admin1' => $this->path($this->option('admin1') ?: config('geocoding.admin1_path')),
                'cities' => $this->path($this->option('cities') ?: config('geocoding.cities_path')),
                'regions' => $this->path($this->option('regions') ?: config('geocoding.regions_path'), optional: true),
            ];
            $this->line('Importing geography catalog; existing IDs and slugs will be retained.');
            $counts = $importer->import($paths, function (string $table, int $count): void {
                if ($table !== 'cities' || $count % 10000 === 0) {
                    $this->line("{$table}: {$count}");
                }
            });
            $this->info("Imported {$counts['countries']} countries, {$counts['regions']} regions, and {$counts['cities']} settlements.");
            $this->line("Russian names: {$counts['countries_ru']} countries, {$counts['regions_ru']} regions, {$counts['cities_ru']} settlements. Original names are used where Russian names are unavailable.");
            $this->line('Run springs:geocode --queue to refresh source links and labels.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function path(mixed $value, bool $optional = false): ?string
    {
        if (! is_string($value) || $value === '') {
            if ($optional) {
                return null;
            }
            throw new RuntimeException('Required geography catalog paths must be configured.');
        }

        return str_starts_with($value, DIRECTORY_SEPARATOR) ? $value : base_path($value);
    }
}
