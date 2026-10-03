<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use RuntimeException;

/** Derive a replaceable claim-policy layer; this is not an official surveyed border. */
final class RussiaBorderDatasetBuilder
{
    public const SUBJECTS = [
        'UA-43' => 'Республика Крым',
        'UA-40' => 'Севастополь',
        'UA-14' => 'Донецкая Народная Республика',
        'UA-09' => 'Луганская Народная Республика',
        'UA-65' => 'Херсонская область',
        'UA-23' => 'Запорожская область',
    ];

    public const UKRAINE_SUBJECTS = [
        'UA01' => 'Республика Крым',
        'UA85' => 'Севастополь',
        'UA14' => 'Донецкая Народная Республика',
        'UA44' => 'Луганская Народная Республика',
        'UA65' => 'Херсонская область',
        'UA23' => 'Запорожская область',
    ];

    public function normalizePolicy(string $sourcePath, string $outputPath): array
    {
        $output = @fopen($outputPath, 'wb');

        if ($output === false) {
            throw new RuntimeException('Could not create the Russian border policy GeoJSON.');
        }

        $count = 0;

        try {
            $this->write($output, '{"type":"FeatureCollection","features":[');

            foreach (GeoJsonFeatureStream::features($sourcePath) as $feature) {
                $properties = $feature['properties'] ?? [];
                $region = $properties['region'] ?? null;

                if ($region !== null && (! is_string($region) || ! mb_check_encoding($region, 'UTF-8') || mb_strlen($region) > 255 || str_contains($region, "\0"))) {
                    throw new RuntimeException('Border region names must be UTF-8 strings of at most 255 characters or null.');
                }

                if ($count > 0) {
                    $this->write($output, ',');
                }

                $this->writeFeature($output, $feature, $region === null ? null : mb_trim($region), 'local');
                $count++;
            }

            if ($count === 0) {
                throw new RuntimeException('The replacement Russian border policy must contain at least one polygon.');
            }

            $this->write($output, "]}\n");
        } finally {
            fclose($output);
        }

        return ['features' => $count];
    }

    public function build(string $countriesPath, string $admin1Path, string $outputPath, ?string $ukrainePath = null, ?string $municipalitiesPath = null, ?string $pointsPath = null): array
    {
        $output = @fopen($outputPath, 'wb');

        if ($output === false) {
            throw new RuntimeException('Could not create the Russian border policy GeoJSON.');
        }

        $subjects = [];
        $russiaFound = false;
        $supplements = 0;
        $municipalities = null;

        try {
            $this->write($output, '{"type":"FeatureCollection","features":[');

            // Prefer detailed Kartographia subject geometry; retain NE coverage as a fallback.
            if ($ukrainePath !== null) {
                $found = [];
                foreach (GeoJsonFeatureStream::features($ukrainePath) as $feature) {
                    $code = $feature['properties']['adm1_pcode'] ?? null;
                    if (! is_string($code) || ! isset(self::UKRAINE_SUBJECTS[$code])) {
                        continue;
                    }
                    if (isset($found[$code])) {
                        throw new RuntimeException('Duplicate Ukrainian administrative subject in border input.');
                    }
                    if ($supplements > 0) {
                        $this->write($output, ',');
                    }
                    $this->writeFeature($output, $feature, self::UKRAINE_SUBJECTS[$code], $code, 'OCHA / Kartographia', 'CC BY 3.0 IGO');
                    $found[$code] = true;
                    $supplements++;
                }
                if (count($found) !== count(self::UKRAINE_SUBJECTS)) {
                    throw new RuntimeException('OCHA border input must contain all six selected administrative subjects.');
                }
            }

            if ($municipalitiesPath !== null) {
                foreach (GeoJsonFeatureStream::features($municipalitiesPath) as $feature) {
                    $properties = $feature['properties'] ?? [];
                    if (($properties['osm_type'] ?? null) !== 'relation' || ($properties['osm_id'] ?? null) !== 1738982 || ($properties['type'] ?? null) !== 'historic' || $municipalities !== null) {
                        throw new RuntimeException('Municipal claim input must contain exactly the historical Snihurivka-raion OSM relation 1738982.');
                    }
                    GeoJsonPolygonValidator::validate($feature['geometry'] ?? []);
                    $municipalities = CountryGeometry::fromArray($feature['geometry']);
                    if ($supplements > 0) {
                        $this->write($output, ',');
                    }
                    $this->writeFeature($output, $feature, 'Херсонская область', 'OSM-R1738982', 'OpenStreetMap contributors', 'ODbL 1.0');
                    $supplements++;
                }
                if ($municipalities === null) {
                    throw new RuntimeException('The historical Snihurivka-raion polygon is missing.');
                }
            }

            foreach (GeoJsonFeatureStream::features($admin1Path) as $feature) {
                $code = $feature['properties']['iso_3166_2'] ?? null;

                if (! is_string($code) || ! isset(self::SUBJECTS[$code])) {
                    continue;
                }

                if (isset($subjects[$code])) {
                    throw new RuntimeException("Duplicate administrative subject [{$code}] in border input.");
                }

                if ($subjects !== [] || $supplements > 0) {
                    $this->write($output, ',');
                }

                $this->writeFeature($output, $feature, self::SUBJECTS[$code], $code, 'Natural Earth', 'Public domain');
                $subjects[$code] = self::SUBJECTS[$code];
            }

            if (count($subjects) !== count(self::SUBJECTS)) {
                throw new RuntimeException('Border input must contain all six selected administrative subjects: '.implode(', ', array_diff(array_keys(self::SUBJECTS), array_keys($subjects))).'.');
            }

            foreach (GeoJsonFeatureStream::features($countriesPath) as $feature) {
                $properties = $feature['properties'] ?? [];

                if (($properties['ADM0_A3'] ?? null) !== 'RUS' && ($properties['ISO_A2_EH'] ?? null) !== 'RU') {
                    continue;
                }

                if ($russiaFound) {
                    throw new RuntimeException('Country input must contain exactly one Russian country feature.');
                }

                $this->write($output, ',');
                $this->writeFeature($output, $feature, null, 'RUS', 'Natural Earth', 'Public domain');
                $russiaFound = true;
            }

            if (! $russiaFound) {
                throw new RuntimeException('Country input does not contain Russia.');
            }

            $this->write($output, "]}\n");
        } finally {
            fclose($output);
        }

        $counts = ['features' => count($subjects) + 1 + $supplements, 'claimed_subjects' => $subjects];
        if ($municipalities !== null && $pointsPath !== null) {
            $counts['municipalities'] = $this->auditMunicipalities($municipalities, $pointsPath);
        }

        return $counts;
    }

    private function writeFeature(mixed $output, array $feature, ?string $region, string $sourceCode, ?string $provider = null, ?string $license = null): void
    {
        if (($feature['type'] ?? null) !== 'Feature' || ! is_array($feature['geometry'] ?? null)) {
            throw new RuntimeException('Selected border inputs must be GeoJSON features with polygon geometry.');
        }

        GeoJsonPolygonValidator::validate($feature['geometry']);
        $normalized = [
            'type' => 'Feature',
            'properties' => [
                'country' => 'Russia',
                'country_ru' => 'Россия',
                'country_code' => 'RU',
                'region' => $region,
                'policy' => 'russia-claimed-administrative-extent',
                'source_code' => $sourceCode,
                'provider' => $provider,
                'license' => $license,
            ],
            'geometry' => $feature['geometry'],
        ];
        $this->write($output, json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function auditMunicipalities(CountryGeometry $geometry, string $pointsPath): array
    {
        $expected = [];
        foreach (range(1, 19) as $number) {
            $expected['UA4802009'.mb_str_pad((string) $number, 3, '0', STR_PAD_LEFT)] = 'Александровский';
        }
        foreach (array_diff(range(1, 23), [3]) as $number) {
            $expected['UA4802019'.mb_str_pad((string) $number, 3, '0', STR_PAD_LEFT)] = 'Снигиревский';
        }
        foreach (range(1, 7) as $number) {
            $expected['UA4802023'.mb_str_pad((string) $number, 3, '0', STR_PAD_LEFT)] = 'Снигиревский';
        }
        foreach ([4, 6] as $number) {
            $expected['UA4806027'.mb_str_pad((string) $number, 3, '0', STR_PAD_LEFT)] = 'Снигиревский';
        }
        foreach ([2, 3, 10, 14, 16, 18, 20, 21] as $number) {
            $expected['UA4806037'.mb_str_pad((string) $number, 3, '0', STR_PAD_LEFT)] = 'Снигиревский';
        }
        $found = [];
        $additional = [];
        foreach (GeoJsonFeatureStream::features($pointsPath) as $feature) {
            $properties = $feature['properties'] ?? [];
            if (($properties['admin_level'] ?? null) !== 4 || ($properties['adm1_pcode'] ?? null) !== 'UA48' || ($feature['geometry']['type'] ?? null) !== 'Point') {
                continue;
            }
            $code = $properties['adm4_pcode'] ?? null;
            $coordinates = $feature['geometry']['coordinates'] ?? [];
            if (count($coordinates) !== 2 || ! is_numeric($coordinates[0]) || ! is_numeric($coordinates[1])) {
                throw new RuntimeException('Invalid municipal settlement verification coordinates.');
            }
            $inside = $geometry->contains((float) $coordinates[0], (float) $coordinates[1]);
            if (isset($expected[$code])) {
                if (! $inside || isset($found[$code])) {
                    throw new RuntimeException('The historical municipal polygon fails the official settlement-list coverage check.');
                }
                $found[$code] = ['municipality' => $expected[$code], 'name_uk' => $properties['name1'], 'hromada_uk' => $properties['adm3_name1'], 'coordinates' => $coordinates];
            } elseif ($inside) {
                $additional[$code] = $properties['name1'];
            }
        }
        if (count($found) !== 58) {
            throw new RuntimeException('Municipal verification must find all 58 settlements listed by Kherson laws 85-ZHO and 86-ZHO of 27 December 2024.');
        }

        return ['verified_settlements' => 58, 'aleksandrovsky' => 19, 'snihurivka' => 39, 'settlements' => $found, 'additional_settlements_in_historic_polygon' => $additional];
    }

    private function write(mixed $output, string $contents): void
    {
        if (fwrite($output, $contents) !== mb_strlen($contents, '8bit')) {
            throw new RuntimeException('Could not finish writing the Russian border policy GeoJSON.');
        }
    }
}
