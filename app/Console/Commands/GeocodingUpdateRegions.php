<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Geocoding\RegionBoundaryIndex;
use App\Services\Geocoding\RegionDatasetBuilder;
use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class GeocodingUpdateRegions extends Command
{
    protected $signature = 'geocoding:update-regions
        {--source= : Local Natural Earth or normalized region GeoJSON instead of downloading}
        {--admin1-source= : Current GeoNames administrative identifiers (defaults to configured admin1 path)}
        {--directory= : Destination directory (defaults to resources/geocoding)}';

    protected $description = 'Validate and atomically replace offline administrative region polygons';

    public function handle(RegionDatasetBuilder $builder): int
    {
        $directory = $this->option('directory') ?: resource_path('geocoding');
        $directory = str_starts_with($directory, DIRECTORY_SEPARATOR) ? $directory : base_path($directory);
        File::ensureDirectoryExists($directory);
        File::ensureDirectoryExists(storage_path('app'));
        $lock = fopen(storage_path('app/geocoding-regions-update.lock'), 'c');

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            $this->error('Another region geography update is running.');

            return self::FAILURE;
        }

        $temporary = tempnam($directory, '.regions-');
        $download = tempnam($directory, '.regions-source-');

        try {
            if ($temporary === false || $download === false) {
                throw new RuntimeException('Could not create temporary region files.');
            }

            $source = $this->option('source');

            if ($source === null) {
                $this->info('Downloading Natural Earth administrative regions…');
                Http::timeout(180)->withOptions(['sink' => $download])->get(RegionDatasetBuilder::SOURCE_URL)->throw();
                $source = $download;
            } else {
                $source = str_starts_with($source, DIRECTORY_SEPARATOR) ? $source : base_path($source);
            }

            $hash = @hash_file('sha256', $source);

            if ($hash === false) {
                throw new RuntimeException('Could not read the region source.');
            }

            $admin1 = $this->option('admin1-source') ?: config('geocoding.admin1_path') ?: resource_path('geocoding/admin1CodesASCII.txt');
            $admin1 = str_starts_with($admin1, DIRECTORY_SEPARATOR) ? $admin1 : base_path($admin1);
            $admin1Hash = @hash_file('sha256', $admin1);

            if ($admin1Hash === false) {
                throw new RuntimeException('Could not read current GeoNames administrative identifiers.');
            }

            $counts = $builder->normalize($source, $temporary, $admin1);

            if (@hash_file('sha256', $source) !== $hash || @hash_file('sha256', $admin1) !== $admin1Hash) {
                throw new RuntimeException('The geometry or administrative source changed during normalization. Retry the update.');
            }

            (new RegionBoundaryIndex(new Repository(['geocoding' => ['regions_path' => $temporary]])))->validateSource();
            $manifest = [
                'source_url' => $this->option('source') === null ? RegionDatasetBuilder::SOURCE_URL : null,
                'provider' => $this->option('source') === null ? 'Natural Earth' : 'Configured replacement',
                'version' => $this->option('source') === null ? '5.1.2' : null,
                'license' => $this->option('source') === null ? 'Public domain' : 'Provided with replacement dataset',
                'source_sha256' => $hash,
                'admin1_sha256' => $admin1Hash,
                'output_sha256' => hash_file('sha256', $temporary),
                'updated_at' => now()->toIso8601String(),
            ] + $counts;

            if (! rename($temporary, $directory.'/regions.geojson')) {
                throw new RuntimeException('Could not publish the validated region geography.');
            }

            File::put($directory.'/regions.manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
            (new RegionBoundaryIndex(new Repository(['geocoding' => [
                'regions_path' => $directory.'/regions.geojson',
                'index_cache_path' => config('geocoding.index_cache_path'),
            ]])))->validateSource();
            $this->info("Published {$counts['regions']} region polygons. Run geocoding:import-catalog and springs:geocode --queue to refresh catalog records and source links.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            foreach ([$temporary, $download] as $file) {
                if (is_string($file) && is_file($file)) {
                    unlink($file);
                }
            }

            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
