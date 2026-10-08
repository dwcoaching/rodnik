<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Geography\GeographyBoundsImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class GeocodingImportBounds extends Command
{
    protected $signature = 'geocoding:import-bounds
        {--countries= : Normalized country GeoJSON (defaults to configured path)}
        {--regions= : Normalized region GeoJSON (defaults to configured path)}';

    protected $description = 'Store country and region map bounds from their polygons or settlements';

    /**
     * @param  array{countries:array{polygon:int,settlements:int,none:int},regions:array{polygon:int,settlements:int,none:int}}  $counts
     * @return list<string>
     */
    public static function summary(array $counts): array
    {
        return array_map(
            static fn (string $table): string => "Map bounds for {$table}: {$counts[$table]['polygon']} from polygons, {$counts[$table]['settlements']} from settlements, {$counts[$table]['none']} without.",
            ['countries', 'regions'],
        );
    }

    public function handle(GeographyBoundsImporter $importer): int
    {
        try {
            DB::connection()->disableQueryLog();
            $counts = $importer->import([
                'countries' => $this->path($this->option('countries') ?: config('geocoding.path')),
                'regions' => $this->path($this->option('regions') ?: config('geocoding.regions_path'), optional: true),
            ]);
            foreach (self::summary($counts) as $line) {
                $this->line($line);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function path(mixed $value, bool $optional = false): ?string
    {
        if (! is_string($value) || $value === '') {
            if ($optional) {
                return null;
            }
            throw new RuntimeException('A country geometry path must be configured.');
        }

        return str_starts_with($value, DIRECTORY_SEPARATOR) ? $value : base_path($value);
    }
}
