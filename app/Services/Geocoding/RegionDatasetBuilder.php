<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use RuntimeException;

final class RegionDatasetBuilder
{
    public const SOURCE_URL = 'https://raw.githubusercontent.com/nvkelso/natural-earth-vector/v5.1.2/geojson/ne_10m_admin_1_states_provinces.geojson';

    public function normalize(string $sourcePath, string $outputPath, ?string $admin1Path = null): array
    {
        $admin1Path ??= dirname(__DIR__, 3).'/resources/geocoding/admin1CodesASCII.txt';
        $currentCodes = $this->currentAdminCodes($admin1Path);
        $ambiguousIds = $this->ambiguousIds($sourcePath);
        $output = @fopen($outputPath, 'wb');

        if ($output === false) {
            throw new RuntimeException('Could not create normalized region geography.');
        }

        $count = 0;
        $skipped = 0;
        $ambiguous = 0;
        $identities = [];
        $idKeys = [];

        try {
            $this->write($output, '{"type":"FeatureCollection","features":[');

            foreach (GeoJsonFeatureStream::features($sourcePath) as $feature) {
                $properties = $feature['properties'] ?? [];
                $geometry = $feature['geometry'] ?? [];
                GeoJsonPolygonValidator::validate($geometry);
                $country = $properties['country_code'] ?? $properties['iso_a2'] ?? null;
                $name = $properties['name_en'] ?? $properties['name'] ?? null;
                $nameRu = $properties['name_ru'] ?? null;
                // Natural Earth includes unnamed residual polygons and
                // disputed areas without an ISO country assignment. Their
                // country is deliberately not invented by this importer.
                if (isset($properties['adm1_code']) && (! is_string($country) || ! preg_match('/^[A-Z]{2}$/D', $country) || ! is_string($name) || mb_trim($name) === '')) {
                    $skipped++;

                    continue;
                }
                $geonamesId = $properties['geonames_id'] ?? $properties['gn_id'] ?? null;
                $geonamesId = is_numeric($geonamesId) && (int) $geonamesId > 0 ? (int) $geonamesId : null;
                $adminCode = $properties['admin1_code'] ?? null;
                $sourceKey = $properties['source_key'] ?? null;

                if (isset($properties['adm1_code']) && $sourceKey === null) {
                    // Natural Earth's older gn_a1_code values can now name
                    // a different GeoNames region. Join using a verified ID
                    // from the current administrative catalog, never the old
                    // code or a guessed name. Conflicting IDs identify no
                    // catalog region and retain their independent NE identity.
                    if ($geonamesId !== null && isset($ambiguousIds[$geonamesId])) {
                        $geonamesId = null;
                        $ambiguous++;
                    }

                    $current = $geonamesId === null ? null : ($currentCodes[$geonamesId] ?? null);
                    $adminCode = $current === null ? null : mb_substr($current, 3);
                    $sourceKey = $current === null ? 'natural-earth-admin1:'.$properties['adm1_code'] : 'geonames-admin1:'.$current;
                }

                $adminCode = is_string($adminCode) && preg_match('/^[A-Za-z0-9_-]+$/D', $adminCode) ? $adminCode : null;

                if (! is_string($country) || ! preg_match('/^[A-Z]{2}$/D', $country) || ! is_string($name) || mb_trim($name) === '') {
                    throw new RuntimeException('Region polygons require a country code and name.');
                }

                $sourceKey ??= $adminCode !== null ? 'geonames-admin1:'.$country.'.'.$adminCode : null;

                if (! is_string($sourceKey) || mb_trim($sourceKey) === '' || $sourceKey === 'natural-earth-admin1:' || ! is_string($nameRu ?? '')) {
                    throw new RuntimeException('Region polygons require stable string source identities and valid names.');
                }

                foreach ([$name, $nameRu, $sourceKey] as $label) {
                    if ($label !== null && (! mb_check_encoding($label, 'UTF-8') || mb_strlen($label) > 255 || str_contains($label, "\0"))) {
                        throw new RuntimeException('Region names and keys must be valid UTF-8 strings of at most 255 characters.');
                    }
                }

                $normalized = [
                    'type' => 'Feature',
                    'properties' => [
                        'source_key' => $sourceKey,
                        'geonames_id' => $geonamesId,
                        'country_code' => $country,
                        'admin1_code' => $adminCode,
                        'iso_code' => $properties['iso_code'] ?? $properties['iso_3166_2'] ?? null,
                        'name' => mb_trim($name),
                        'name_ru' => $nameRu === null ? null : mb_trim($nameRu),
                    ],
                    'geometry' => $geometry,
                ];
                $identity = json_encode($normalized['properties'], JSON_THROW_ON_ERROR);

                if (isset($identities[$sourceKey]) && $identities[$sourceKey] !== $identity) {
                    throw new RuntimeException("Conflicting region metadata for source identity [{$sourceKey}].");
                }

                if ($geonamesId !== null && isset($idKeys[$geonamesId]) && $idKeys[$geonamesId] !== $sourceKey) {
                    throw new RuntimeException("GeoNames region ID [{$geonamesId}] belongs to conflicting polygon identities.");
                }

                $identities[$sourceKey] = $identity;

                if ($geonamesId !== null) {
                    $idKeys[$geonamesId] = $sourceKey;
                }

                $this->write($output, ($count > 0 ? ',' : '').json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $count++;
            }

            if ($count === 0) {
                throw new RuntimeException('The region source must contain at least one polygon.');
            }

            $this->write($output, "]}\n");
        } finally {
            fclose($output);
        }

        return ['regions' => $count, 'skipped_unassigned' => $skipped, 'ambiguous_geonames_ids_cleared' => $ambiguous];
    }

    private function currentAdminCodes(string $path): array
    {
        $file = @fopen($path, 'rb');

        if ($file === false) {
            throw new RuntimeException('Could not read the current GeoNames administrative identifiers.');
        }

        $codes = [];

        try {
            while (($line = fgets($file)) !== false) {
                $columns = explode("\t", mb_rtrim($line, "\r\n"));

                if (count($columns) !== 4 || ! ctype_digit($columns[3]) || ! preg_match('/^[A-Z]{2}\.[A-Za-z0-9_-]+$/D', $columns[0])) {
                    throw new RuntimeException('Invalid current GeoNames administrative identifier row.');
                }

                $codes[(int) $columns[3]] = $columns[0];
            }
        } finally {
            fclose($file);
        }

        return $codes;
    }

    private function ambiguousIds(string $path): array
    {
        $ids = [];
        $ambiguous = [];

        foreach (GeoJsonFeatureStream::features($path) as $feature) {
            $properties = $feature['properties'] ?? [];
            $id = $properties['gn_id'] ?? null;

            if (! isset($properties['adm1_code']) || ! is_numeric($id) || (int) $id < 1) {
                continue;
            }

            $id = (int) $id;

            if (isset($ids[$id]) && $ids[$id] !== $properties['adm1_code']) {
                $ambiguous[$id] = true;
            }

            $ids[$id] = $properties['adm1_code'];
        }

        return $ambiguous;
    }

    private function write(mixed $stream, string $data): void
    {
        if (fwrite($stream, $data) !== mb_strlen($data, '8bit')) {
            throw new RuntimeException('Could not finish writing region geography.');
        }
    }
}
