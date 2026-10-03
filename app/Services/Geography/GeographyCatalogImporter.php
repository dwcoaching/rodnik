<?php

declare(strict_types=1);

namespace App\Services\Geography;

use App\Services\Geocoding\ClaimedRegion;
use App\Services\Geocoding\GeoJsonFeatureStream;
use App\Services\Geocoding\RegionBoundaryIndex;
use App\Services\Geocoding\RussiaBorderPolicy;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Import provider identities without changing existing database IDs or URLs. */
final class GeographyCatalogImporter
{
    public function __construct(
        private readonly GeographyCatalogReader $reader,
        private readonly Repository $config,
        private readonly RussiaBorderPolicy $policy,
    ) {}

    /**
     * @param  array{country_info:string,russian_names:string,countries:string,admin1:string,cities:string,regions:?string}  $paths
     * @param  null|callable(string,int):void  $progress
     * @return array{countries:int,regions:int,cities:int,countries_ru:int,regions_ru:int,cities_ru:int}
     */
    public function import(array $paths, ?callable $progress = null): array
    {
        $hashes = [];
        foreach ($paths as $key => $path) {
            if ($path === null) {
                continue;
            }
            if (! is_file($path) || ! is_readable($path) || ($hashes[$key] = hash_file('sha256', $path)) === false) {
                throw new RuntimeException("Could not read catalog input [{$path}].");
            }
        }
        $borderPath = $this->config->get('geocoding.russia_border_path');
        $borderPath = is_string($borderPath) && $borderPath !== '' ? (str_starts_with($borderPath, DIRECTORY_SEPARATOR) ? $borderPath : base_path($borderPath)) : null;
        if ($borderPath !== null && (! is_file($borderPath) || ! is_readable($borderPath))) {
            throw new RuntimeException("Could not read territorial policy [{$borderPath}].");
        }
        $borderHash = $borderPath === null ? null : hash_file('sha256', $borderPath);
        $this->policy->validateBorders();
        $names = new RussianNameLookup($paths['russian_names'], storage_path('framework/cache/geocoding'));
        $generation = (string) Str::uuid();
        $timestamp = now()->toDateTimeString();
        $claims = $this->claimedRegions();
        $settings = $this->config->get('geocoding', []);
        $settings['regions_path'] = $paths['regions'];
        $boundaries = new RegionBoundaryIndex(new \Illuminate\Config\Repository(['geocoding' => $settings]), $this->policy);

        return $this->policy->withBorderSnapshot(fn (): array => $boundaries->withSnapshot(fn (): array => DB::transaction(function () use ($paths, $hashes, $names, $generation, $timestamp, $claims, $progress, $boundaries, $borderPath, $borderHash): array {
            $countries = [];
            foreach ($this->reader->countries($paths['country_info']) as $country) {
                // These dissolved codes remain in GeoNames for historical references.
                if (! in_array($country['code'], ['AN', 'CS'], true)) {
                    $countries[$country['code']] = $country;
                }
            }
            $countryRussian = [];
            foreach (GeoJsonFeatureStream::features($paths['countries']) as $feature) {
                $properties = $feature['properties'] ?? [];
                $code = $properties['country_code'] ?? null;
                if (! is_string($code) || preg_match('/^[A-Z]{2,3}$/D', $code) !== 1) {
                    continue;
                }
                $countries[$code] ??= ['code' => $code, 'name' => $properties['country'] ?? $code, 'geonames_id' => null];
                $countryRussian[$code] ??= $properties['country_ru'] ?? null;
            }
            $countries['RU'] ??= ['code' => 'RU', 'name' => 'Russia', 'geonames_id' => 2017370];
            $translations = $names->names(array_values(array_filter(array_column($countries, 'geonames_id'))));
            $countryRows = [];
            foreach ($countries as $code => $country) {
                $countryRows[] = [
                    ...$country, 'name' => $code === 'RU' ? 'Russia' : $country['name'],
                    'name_ru' => $code === 'RU' ? 'Россия' : ($translations[$country['geonames_id']] ?? $countryRussian[$code] ?? null),
                    'slug' => GeographySlug::make($country['name'], $code), 'is_active' => true,
                    'catalog_generation' => $generation, 'created_at' => $timestamp, 'updated_at' => $timestamp,
                ];
            }
            DB::table('countries')->upsert($countryRows, ['code'], ['geonames_id', 'name', 'name_ru', 'is_active', 'catalog_generation', 'updated_at']);
            $countryIds = DB::table('countries')->pluck('id', 'code')->all();
            if ($progress !== null) {
                $progress('countries', count($countryRows));
            }

            $regions = [];
            $regionGeonamesKeys = [];
            foreach ($this->reader->regions($paths['admin1']) as $region) {
                $regions[$region['source_key']] = [...$region, 'country_code' => $region['source_country_code'], 'iso_code' => null, 'name_ru' => null];
                $regionGeonamesKeys[$region['geonames_id']] = $region['source_key'];
            }
            if ($paths['regions'] !== null) {
                $geometryKeys = [];
                $geometryIds = [];
                foreach (GeoJsonFeatureStream::features($paths['regions']) as $feature) {
                    $properties = $feature['properties'] ?? [];
                    $key = $properties['source_key'] ?? null;
                    $code = $properties['country_code'] ?? null;
                    if (! is_string($key) || $key === '' || ! is_string($code) || preg_match('/^[A-Z]{2,3}$/D', $code) !== 1 || ! is_string($properties['name'] ?? null)) {
                        throw new RuntimeException('Normalized region geometry needs source_key, country_code, and name properties.');
                    }
                    $id = isset($properties['geonames_id']) && (int) $properties['geonames_id'] > 0 ? (int) $properties['geonames_id'] : null;
                    $identity = [$id, $code, $properties['name'], $properties['iso_code'] ?? null];
                    if ((isset($geometryKeys[$key]) && $geometryKeys[$key] !== $identity)
                        || ($id !== null && isset($geometryIds[$id]) && $geometryIds[$id] !== $key)) {
                        throw new RuntimeException('Region geometry contains conflicting provider identities; normalize the source before importing.');
                    }
                    $geometryKeys[$key] = $identity;
                    if ($id !== null) {
                        $geometryIds[$id] = $key;
                    }
                    if (isset($regions[$key]) && $id !== $regions[$key]['geonames_id']) {
                        throw new RuntimeException('Region geometry reuses a current administrative code for a different GeoNames identity.');
                    }
                    $key = $id !== null ? ($regionGeonamesKeys[$id] ?? $key) : $key;
                    $regions[$key] = [
                        'source_key' => $key, 'source_country_code' => $regions[$key]['source_country_code'] ?? $code,
                        'country_code' => $code,
                        'admin1_code' => $regions[$key]['admin1_code'] ?? $properties['admin1_code'] ?? null,
                        'geonames_id' => $id, 'name' => $regions[$key]['name'] ?? $properties['name'],
                        'name_ru' => $properties['name_ru'] ?? null, 'iso_code' => $properties['iso_code'] ?? null,
                    ];
                    if ($id !== null) {
                        $regionGeonamesKeys[$id] = $key;
                    }
                }
            }
            $translations = $names->names(array_values(array_filter(array_column($regions, 'geonames_id'))));
            $regionRows = [];
            foreach ($regions as $region) {
                $claim = $claims[$region['geonames_id']] ?? null;
                $code = $claim['country_code'] ?? $region['country_code'];
                unset($region['country_code']);
                if (! isset($countryIds[$code])) {
                    throw new RuntimeException("Region references an unknown country [{$code}].");
                }
                $sourceKey = $region['source_key'];
                if ($region['geonames_id'] !== null) {
                    // Administrative codes can change or be reassigned. The
                    // GeoNames entity ID takes precedence over its current code.
                    $sourceKey = 'geonames-region:'.$region['geonames_id'];
                }
                $regionRows[] = [
                    ...$region, 'source_key' => $sourceKey, 'country_id' => $countryIds[$code],
                    'name' => $claim['name'] ?? $region['name'],
                    'name_ru' => $claim['name_ru'] ?? $translations[$region['geonames_id']] ?? $region['name_ru'],
                    'slug' => GeographySlug::make($region['name'], (string) ($region['geonames_id'] ?? $region['source_key'])),
                    'is_active' => true, 'catalog_generation' => $generation, 'created_at' => $timestamp, 'updated_at' => $timestamp,
                ];
            }
            foreach (array_chunk($regionRows, 500) as $chunk) {
                foreach (['geonames_id', 'source_key'] as $identity) {
                    $rows = array_values(array_filter($chunk, static fn (array $row): bool => $identity === 'geonames_id' ? $row['geonames_id'] !== null : $row['geonames_id'] === null));
                    if ($rows !== []) {
                        DB::table('regions')->upsert($rows, [$identity], ['source_key', 'country_id', 'source_country_code', 'admin1_code', 'geonames_id', 'iso_code', 'name', 'name_ru', 'is_active', 'catalog_generation', 'updated_at']);
                    }
                }
            }
            $regionIds = [];
            $regionIdsByGeonames = [];
            $regionCountries = [];
            foreach (DB::table('regions')->select(['id', 'country_id', 'source_key', 'geonames_id'])->get() as $region) {
                $regionIds[$region->source_key] = $region->id;
                $regionCountries[$region->id] = $region->country_id;
                if ($region->geonames_id !== null) {
                    $regionIdsByGeonames[$region->geonames_id] = $region->id;
                }
            }
            if ($progress !== null) {
                $progress('regions', count($regionRows));
            }

            $count = 0;
            $citiesRussian = 0;
            $batch = [];
            $batchSize = max(100, min(2000, (int) $this->config->get('geography.import_batch_size', 1000)));
            foreach ($this->reader->cities($paths['cities']) as $city) {
                $code = $this->canonicalCityCountry($city['source_country_code']);
                $regionKey = 'geonames-admin1:'.$city['source_country_code'].'.'.$city['admin1_code'];
                $regionGeonamesId = $regions[$regionKey]['geonames_id'] ?? null;
                $regionId = $regionIdsByGeonames[$regionGeonamesId] ?? $regionIds[$regionKey] ?? null;
                if ($this->policy->contains($city['latitude'], $city['longitude'])) {
                    $code = 'RU';
                }
                // Center containment keeps settlement parents consistent with
                // the geographic regions used for source pages. Provider admin1
                // membership is used only when region geometry is disabled.
                $region = $boundaries->region($city['latitude'], $city['longitude'], $code);
                if ($paths['regions'] !== null || $region !== null) {
                    $regionId = $regionIdsByGeonames[$region?->regionGeonamesId] ?? $regionIds[$region?->regionSourceKey] ?? null;
                }
                if (! isset($countryIds[$code])) {
                    throw new RuntimeException("Settlement references an unknown country [{$code}].");
                }
                if ($regionId !== null && $regionCountries[$regionId] !== $countryIds[$code]) {
                    $regionId = null;
                }
                $batch[] = [...$city, 'country_id' => $countryIds[$code], 'region_id' => $regionId];
                if (count($batch) >= $batchSize) {
                    $citiesRussian += $this->writeCities($batch, $names, $generation, $timestamp);
                    $count += count($batch);
                    if ($progress !== null) {
                        $progress('cities', $count);
                    }
                    $batch = [];
                }
            }
            $citiesRussian += $this->writeCities($batch, $names, $generation, $timestamp);
            $count += count($batch);
            if ($count === 0 || $regionRows === []) {
                throw new RuntimeException('Catalog import requires nonempty country, region, and settlement inputs.');
            }
            foreach ($hashes as $key => $hash) {
                if (hash_file('sha256', $paths[$key]) !== $hash) {
                    throw new RuntimeException('Catalog inputs changed during import; transaction rolled back.');
                }
            }
            if ($borderPath !== null && hash_file('sha256', $borderPath) !== $borderHash) {
                throw new RuntimeException('Territorial policy changed during catalog import; transaction rolled back.');
            }
            foreach (['cities', 'regions', 'countries'] as $table) {
                DB::table($table)->where(function ($query) use ($generation): void {
                    $query->whereNull('catalog_generation')->orWhere('catalog_generation', '!=', $generation);
                })->update(['is_active' => false]);
            }

            return [
                'countries' => count($countryRows), 'regions' => count($regionRows), 'cities' => $count,
                'countries_ru' => count(array_filter($countryRows, static fn (array $row): bool => $row['name_ru'] !== null)),
                'regions_ru' => count(array_filter($regionRows, static fn (array $row): bool => $row['name_ru'] !== null)),
                'cities_ru' => $citiesRussian,
            ];
        })));
    }

    private function canonicalCityCountry(string $sourceCode): string
    {
        $aliases = $this->config->get('geocoding.city_country_aliases', []);
        if (! is_array($aliases)) {
            throw new RuntimeException('Settlement country aliases must be a country-to-code-list mapping.');
        }
        foreach ($aliases as $country => $codes) {
            if (! is_string($country) || ! is_array($codes)) {
                throw new RuntimeException('Settlement country aliases must be a country-to-code-list mapping.');
            }
            if (in_array($sourceCode, $codes, true)) {
                return $country;
            }
        }

        return $sourceCode;
    }

    /** @param list<array<string,mixed>> $batch */
    private function writeCities(array $batch, RussianNameLookup $names, string $generation, string $timestamp): int
    {
        if ($batch === []) {
            return 0;
        }
        $translations = $names->names(array_column($batch, 'geonames_id'));
        $rows = [];
        $russian = 0;
        foreach ($batch as $city) {
            $ru = $translations[$city['geonames_id']] ?? null;
            $russian += $ru !== null ? 1 : 0;
            $rows[] = [
                ...$city, 'name_ru' => $ru,
                'slug' => GeographySlug::make($city['name'], (string) $city['geonames_id']),
                'is_active' => true, 'catalog_generation' => $generation, 'created_at' => $timestamp, 'updated_at' => $timestamp,
            ];
        }
        DB::table('cities')->upsert($rows, ['source_key'], ['country_id', 'region_id', 'geonames_id', 'source_country_code', 'admin1_code', 'name', 'name_ru', 'latitude', 'longitude', 'population', 'is_active', 'catalog_generation', 'updated_at']);

        return $russian;
    }

    /** @return array<int, array<string,mixed>> */
    private function claimedRegions(): array
    {
        $path = $this->config->get('geocoding.russia_border_path');
        if (! is_string($path) || $path === '') {
            return [];
        }
        $path = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
        $claims = [];
        foreach (GeoJsonFeatureStream::features($path) as $feature) {
            $properties = $feature['properties'] ?? [];
            $claim = ClaimedRegion::fromCode($properties['source_code'] ?? null) ?? ClaimedRegion::fromName($properties['region'] ?? null);
            if ($claim !== null) {
                $claims[$claim['geonames_id']] = $claim;
            }
        }

        return $claims;
    }
}
