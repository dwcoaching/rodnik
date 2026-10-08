<?php

declare(strict_types=1);

namespace App\Services\Geography;

use App\Services\Geocoding\GeoJsonFeatureStream;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Store country and region map viewports from their polygons, or else from their settlements. */
final class GeographyBoundsImporter
{
    private const BATCH_SIZE = 500;

    /**
     * @param  array{countries:string,regions:?string}  $paths
     * @return array{countries:array{polygon:int,settlements:int,none:int},regions:array{polygon:int,settlements:int,none:int}}
     */
    public function import(array $paths): array
    {
        foreach ($paths as $path) {
            if ($path !== null && (! is_file($path) || ! is_readable($path))) {
                throw new RuntimeException("Could not read bounds input [{$path}].");
            }
        }
        $countryParts = $this->polygonParts($paths['countries'], static function (array $properties): ?string {
            $code = $properties['country_code'] ?? null;

            return is_string($code) && preg_match('/^[A-Z]{2,3}$/D', $code) === 1 ? $code : null;
        });
        // Region identities mirror the catalog import: GeoNames ID first, else provider key.
        $regionParts = $paths['regions'] === null ? [] : $this->polygonParts($paths['regions'], static function (array $properties): ?string {
            $id = $properties['geonames_id'] ?? null;
            if (is_numeric($id) && (int) $id > 0) {
                return 'geonames:'.(int) $id;
            }
            $key = $properties['source_key'] ?? null;

            return is_string($key) && $key !== '' ? 'source:'.$key : null;
        });

        return DB::transaction(fn (): array => [
            'countries' => $this->countries($countryParts, $regionParts),
            'regions' => $this->regions($regionParts),
        ]);
    }

    /**
     * @param  array<string, list<array{west:float,south:float,east:float,north:float,area:float}>>  $countryParts
     * @param  array<string, list<array{west:float,south:float,east:float,north:float,area:float}>>  $regionParts
     * @return array{polygon:int,settlements:int,none:int}
     */
    private function countries(array $countryParts, array $regionParts): array
    {
        $bounds = [];
        $missing = [];
        foreach (DB::table('countries')->orderBy('id')->get(['id', 'code']) as $country) {
            $bounds[$country->id] = PlaceBounds::mainCluster($countryParts[$country->code] ?? []);
            if ($bounds[$country->id] === null) {
                $missing[$country->id] = $country->code;
            }
        }
        // Natural Earth folds territories such as Svalbard into their sovereign
        // country, but provider regions with the territory's code keep polygons.
        $partsByCode = [];
        $regions = DB::table('regions')->where('is_active', true)->whereIn('source_country_code', array_values($missing))->orderBy('id')
            ->get(['source_country_code', 'source_key', 'geonames_id']);
        foreach ($regions as $region) {
            foreach ($this->regionPolygon($region, $regionParts) as $part) {
                $partsByCode[$region->source_country_code][] = $part;
            }
        }
        foreach ($missing as $id => $code) {
            if (($bounds[$id] = PlaceBounds::mainCluster($partsByCode[$code] ?? [])) !== null) {
                unset($missing[$id]);
            }
        }
        $counts = ['polygon' => count($bounds) - count($missing), 'settlements' => 0, 'none' => 0];
        $byCode = $this->settlementBounds(['source_country_code'], array_map(static fn (string $code): array => [$code], array_values($missing)));
        $byCountry = $this->settlementBounds(['country_id'], array_map(
            static fn (int $id): array => [$id],
            array_keys(array_filter($missing, static fn (string $code): bool => ! isset($byCode[$code]))),
        ));
        foreach ($missing as $id => $code) {
            $bounds[$id] = $byCode[$code] ?? $byCountry[(string) $id] ?? null;
            $counts[$bounds[$id] === null ? 'none' : 'settlements']++;
        }
        $this->store('countries', $bounds);

        return $counts;
    }

    /**
     * @param  array<string, list<array{west:float,south:float,east:float,north:float,area:float}>>  $regionParts
     * @return array{polygon:int,settlements:int,none:int}
     */
    private function regions(array $regionParts): array
    {
        $bounds = [];
        $missing = [];
        foreach (DB::table('regions')->orderBy('id')->get(['id', 'source_key', 'geonames_id', 'source_country_code', 'admin1_code']) as $region) {
            $bounds[$region->id] = PlaceBounds::mainCluster($this->regionPolygon($region, $regionParts));
            if ($bounds[$region->id] === null) {
                $missing[$region->id] = $region;
            }
        }
        $counts = ['polygon' => count($bounds) - count($missing), 'settlements' => 0, 'none' => 0];
        $byRegion = $this->settlementBounds(['region_id'], array_map(static fn (int $id): array => [$id], array_keys($missing)));
        // Provider regions without polygons or linked settlements, such as
        // GeoNames England, still own settlements through their admin1 code.
        $byAdmin1 = $this->settlementBounds(['source_country_code', 'admin1_code'], array_values(array_map(
            static fn (object $region): array => [$region->source_country_code, $region->admin1_code],
            array_filter($missing, static fn (object $region): bool => ! isset($byRegion[(string) $region->id]) && (string) $region->admin1_code !== ''),
        )));
        foreach ($missing as $id => $region) {
            $bounds[$id] = $byRegion[(string) $id]
                ?? ((string) $region->admin1_code !== '' ? $byAdmin1[$region->source_country_code."\t".$region->admin1_code] ?? null : null);
            $counts[$bounds[$id] === null ? 'none' : 'settlements']++;
        }
        $this->store('regions', $bounds);

        return $counts;
    }

    /**
     * Polygon parts of each key; several features may share a key.
     *
     * @param  callable(array<string,mixed>):?string  $key
     * @return array<string, list<array{west:float,south:float,east:float,north:float,area:float}>>
     */
    private function polygonParts(string $path, callable $key): array
    {
        $parts = [];
        foreach (GeoJsonFeatureStream::features($path) as $feature) {
            $properties = $feature['properties'] ?? null;
            $geometry = $feature['geometry'] ?? null;
            if (! is_array($properties) || ! is_array($geometry) || ($identity = $key($properties)) === null) {
                continue;
            }
            foreach (PlaceBounds::parts($geometry) as $part) {
                $parts[$identity][] = $part;
            }
        }

        return $parts;
    }

    /**
     * @param  array<string, list<array{west:float,south:float,east:float,north:float,area:float}>>  $regionParts
     * @return list<array{west:float,south:float,east:float,north:float,area:float}>
     */
    private function regionPolygon(object $region, array $regionParts): array
    {
        return ((int) $region->geonames_id > 0 ? $regionParts['geonames:'.$region->geonames_id] ?? null : null)
            ?? $regionParts['source:'.$region->source_key] ?? [];
    }

    /**
     * Main-cluster bounds of the active settlements in each wanted group of
     * column values, keyed by those values joined with tabs.
     *
     * @param  list<string>  $columns
     * @param  list<list<int|string>>  $groups
     * @return array<string, array{0:float,1:float,2:float,3:float}>
     */
    private function settlementBounds(array $columns, array $groups): array
    {
        $settlements = [];
        foreach (array_chunk($groups, 1000) as $chunk) {
            $wanted = array_flip(array_map(static fn (array $values): string => implode("\t", $values), $chunk));
            $query = DB::table('cities')->where('is_active', true)->orderBy('id')->select([...$columns, 'latitude', 'longitude', 'population']);
            // Each column narrows the scan; the exact groups are matched below.
            foreach ($columns as $index => $column) {
                $query->whereIn($column, array_values(array_unique(array_column($chunk, $index))));
            }
            foreach ($query->cursor() as $row) {
                $key = implode("\t", array_map(static fn (string $column): string => (string) $row->{$column}, $columns));
                if (isset($wanted[$key])) {
                    $settlements[$key][] = [(float) $row->latitude, (float) $row->longitude, (int) $row->population];
                }
            }
        }

        return array_filter(array_map(PlaceBounds::fromSettlements(...), $settlements));
    }

    /** @param array<int, null|array{0:float,1:float,2:float,3:float}> $bounds */
    private function store(string $table, array $bounds): void
    {
        foreach (array_chunk($bounds, self::BATCH_SIZE, true) as $chunk) {
            $assignments = [];
            $bindings = [];
            foreach (['bbox_west', 'bbox_south', 'bbox_east', 'bbox_north'] as $index => $column) {
                $assignments[] = "{$column} = CASE id".str_repeat(' WHEN ? THEN ?', count($chunk)).' END';
                foreach ($chunk as $id => $box) {
                    array_push($bindings, $id, $box[$index] ?? null);
                }
            }
            $placeholders = implode(', ', array_fill(0, count($chunk), '?'));
            DB::update("UPDATE {$table} SET ".implode(', ', $assignments)." WHERE id IN ({$placeholders})", [...$bindings, ...array_keys($chunk)]);
        }
    }
}
