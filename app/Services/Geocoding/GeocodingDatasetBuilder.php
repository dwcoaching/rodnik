<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use RuntimeException;
use ZipArchive;

/** Normalize public datasets while keeping GeoNames' standard 19 TSV columns. */
final class GeocodingDatasetBuilder
{
    public const EXCLUDED_SETTLEMENT_CODES = ['PPLX', 'PPLQ', 'PPLH', 'PPLCH', 'PPLW'];

    public const SETTLEMENT_MEMBERS = ['cities500' => 'cities500.txt', 'cities1000' => 'cities1000.txt', 'all' => 'allCountries.txt'];

    /** @param array<string, string> $supplementArchives Country code => full GeoNames country ZIP. */
    public function build(string $countriesSource, string $citiesArchive, string $admin1Source, string $directory, string $settlements = 'cities1000', array $supplementArchives = []): array
    {
        if (! isset(self::SETTLEMENT_MEMBERS[$settlements])) {
            throw new RuntimeException('Settlement catalog must be cities500, cities1000, or all.');
        }

        foreach ($supplementArchives as $country => $archive) {
            if (! preg_match('/^[A-Z]{2}$/D', $country)) {
                throw new RuntimeException('Supplemental settlement archives require uppercase two-letter country codes and paths.');
            }
        }

        if ($settlements === 'all' && $supplementArchives !== []) {
            throw new RuntimeException('The all-country settlement catalog already includes country supplements.');
        }

        return [
            'countries' => $this->countries($countriesSource, $directory.'/countries.geojson'),
            'cities' => $this->cities($citiesArchive, $directory.'/settlements.txt', self::SETTLEMENT_MEMBERS[$settlements], $supplementArchives),
            'admin1_regions' => $this->regions($admin1Source, $directory.'/admin1CodesASCII.txt'),
        ];
    }

    private function countries(string $source, string $destination): int
    {
        $output = $this->openOutput($destination);
        $count = 0;
        try {
            $this->writeStream($output, '{"type":"FeatureCollection","features":[');
            foreach (GeoJsonFeatureStream::features($source) as $feature) {
                $properties = $feature['properties'] ?? null;
                $geometry = $feature['geometry'] ?? null;
                if (! is_array($properties) || ! is_array($geometry) || ! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                    throw new RuntimeException('Every Natural Earth country must have polygon geometry and properties.');
                }

                $this->validateGeometry($geometry);
                $country = $this->label($properties['NAME_EN'] ?? $properties['ADMIN'] ?? null, 'country');
                $countryRu = $this->label($properties['NAME_RU'] ?? null, 'Russian country', optional: true);
                $code = null;
                foreach (['ISO_A2_EH', 'ISO_A2'] as $key) {
                    if (is_string($properties[$key] ?? null) && preg_match('/^[A-Z]{2}$/D', $properties[$key]) === 1) {
                        $code = $properties[$key];
                        break;
                    }
                }
                // Natural Earth treats these areas separately; GeoNames uses these codes.
                $code ??= match ($properties['ADM0_A3'] ?? null) {
                    'SOL' => 'SO',
                    'CYN' => 'CY',
                    default => null,
                };

                $normalized = [
                    'type' => 'Feature',
                    'properties' => ['country' => $country, 'country_ru' => $countryRu, 'country_code' => $code],
                    'geometry' => $geometry,
                ];
                $this->writeStream($output, ($count > 0 ? ',' : '').json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $count++;
            }
            if ($count === 0) {
                throw new RuntimeException('Natural Earth input must contain at least one country.');
            }
            $this->writeStream($output, "]}\n");
        } finally {
            fclose($output);
        }

        return $count;
    }

    /** @param array<string, string> $supplementArchives */
    private function cities(string $archivePath, string $destination, string $member, array $supplementArchives): int
    {
        $output = $this->openOutput($destination);
        $seen = [];
        try {
            $count = $this->appendSettlements($archivePath, $member, $output, $seen, array_keys($supplementArchives));
            foreach ($supplementArchives as $country => $archive) {
                $count += $this->appendSettlements($archive, $country.'.txt', $output, $seen, [$country], $country);
            }
            if ($count === 0) {
                throw new RuntimeException('The GeoNames archive contains no current settlements.');
            }

            return $count;
        } finally {
            fclose($output);
        }
    }

    /**
     * @param  resource  $output
     * @param  array<int, true>  $seen
     * @param  list<string>  $deduplicateCountries
     */
    private function appendSettlements(string $archivePath, string $member, mixed $output, array &$seen, array $deduplicateCountries, ?string $onlyCountry = null): int
    {
        $archive = new ZipArchive();
        if ($archive->open($archivePath) !== true) {
            throw new RuntimeException('Could not open the GeoNames settlement ZIP archive.');
        }
        $input = $archive->getStream($member);
        if ($input === false) {
            $archive->close();
            throw new RuntimeException('The GeoNames ZIP archive must contain '.$member.'.');
        }
        $count = 0;
        $lineNumber = 0;
        try {
            while (($line = fgets($input)) !== false) {
                $lineNumber++;
                $columns = explode("\t", mb_rtrim($line, "\r\n"));
                if (count($columns) !== 19 || ! ctype_digit($columns[0]) || (int) $columns[0] <= 0) {
                    throw new RuntimeException("Invalid GeoNames city record on line {$lineNumber}.");
                }
                if ($columns[6] !== 'P' || in_array($columns[7], self::EXCLUDED_SETTLEMENT_CODES, true)) {
                    continue;
                }
                if ($onlyCountry !== null && $columns[8] !== $onlyCountry) {
                    throw new RuntimeException("GeoNames supplemental archive [{$onlyCountry}] contains a settlement from another country.");
                }
                $this->label($columns[1], 'city');
                if (! is_numeric($columns[4]) || ! is_numeric($columns[5])
                    || ! is_finite((float) $columns[4]) || ! is_finite((float) $columns[5])
                    || abs((float) $columns[4]) > 90 || abs((float) $columns[5]) > 180
                    || preg_match('/^[A-Z]{2}$/D', $columns[8]) !== 1
                    || ($columns[14] !== '' && ! ctype_digit($columns[14]))) {
                    throw new RuntimeException("Invalid coordinates, country, or population on GeoNames city line {$lineNumber}.");
                }

                // Track only countries that are supplemented. A global all-country
                // stream never needs a multi-million-entry PHP deduplication array.
                if (in_array($columns[8], $deduplicateCountries, true)) {
                    $id = (int) $columns[0];
                    if (isset($seen[$id])) {
                        continue;
                    }
                    $seen[$id] = true;
                }

                $compact = array_fill(0, 19, '');
                foreach ([0, 1, 4, 5, 6, 7, 8, 10, 14] as $column) {
                    $compact[$column] = $columns[$column];
                }
                $normalized = implode("\t", $compact)."\n";
                if (fwrite($output, $normalized) !== mb_strlen($normalized, '8bit')) {
                    throw new RuntimeException('Could not write normalized GeoNames cities.');
                }
                $count++;
            }
            if (! feof($input)) {
                throw new RuntimeException('Could not finish reading the GeoNames cities archive.');
            }
        } finally {
            fclose($input);
            $archive->close();
        }

        return $count;
    }

    private function regions(string $source, string $destination): int
    {
        $input = @fopen($source, 'rb');
        if ($input === false) {
            throw new RuntimeException('Could not read the GeoNames first-level administrative names.');
        }
        $output = $this->openOutput($destination);
        $count = 0;
        try {
            while (($line = fgets($input)) !== false) {
                if (mb_trim($line) === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $columns = explode("\t", mb_rtrim($line, "\r\n"));
                if (count($columns) !== 4 || preg_match('/^[A-Z]{2}\..+$/D', $columns[0]) !== 1) {
                    throw new RuntimeException('Invalid GeoNames first-level administrative record.');
                }
                $this->label($columns[1], 'administrative region');
                $normalized = implode("\t", $columns)."\n";
                if (fwrite($output, $normalized) !== mb_strlen($normalized, '8bit')) {
                    throw new RuntimeException('Could not write normalized administrative regions.');
                }
                $count++;
            }
            if (! feof($input) || $count === 0) {
                throw new RuntimeException('The GeoNames administrative names file is unreadable or empty.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }

        return $count;
    }

    private function label(mixed $value, string $field, bool $optional = false): ?string
    {
        if ($optional && ($value === null || $value === '')) {
            return null;
        }
        if (! is_string($value) || mb_trim($value) === '' || ! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 255) {
            throw new RuntimeException("The {$field} label must be non-empty UTF-8 text of at most 255 characters.");
        }

        return mb_trim($value);
    }

    private function validateGeometry(array $geometry): void
    {
        $coordinates = $geometry['coordinates'] ?? null;
        if (! is_array($coordinates) || ! array_is_list($coordinates) || $coordinates === []) {
            throw new RuntimeException('Country boundaries must have non-empty coordinates.');
        }
        $polygons = $geometry['type'] === 'Polygon' ? [$coordinates] : $coordinates;
        foreach ($polygons as $polygon) {
            if (! is_array($polygon) || ! array_is_list($polygon) || $polygon === []) {
                throw new RuntimeException('Country polygons must contain linear rings.');
            }
            foreach ($polygon as $ring) {
                if (! is_array($ring) || ! array_is_list($ring) || count($ring) < 4) {
                    throw new RuntimeException('Country linear rings must contain at least four positions.');
                }
                $first = null;
                $last = null;
                $distinct = [];
                foreach ($ring as $position) {
                    if (! is_array($position) || ! array_is_list($position) || count($position) < 2
                        || ! is_numeric($position[0]) || ! is_numeric($position[1])
                        || ! is_finite((float) $position[0]) || ! is_finite((float) $position[1])
                        || abs((float) $position[0]) > 180 || abs((float) $position[1]) > 90) {
                        throw new RuntimeException('Country positions must have finite WGS84 longitude and latitude.');
                    }
                    $last = [(float) $position[0], (float) $position[1]];
                    $first ??= $last;
                    if (count($distinct) < 3) {
                        $distinct[serialize($last)] = true;
                    }
                }
                if ($first !== $last || count($distinct) < 3) {
                    throw new RuntimeException('Country linear rings must be closed with three distinct positions.');
                }
            }
        }
    }

    private function writeStream(mixed $output, string $contents): void
    {
        if (fwrite($output, $contents) !== mb_strlen($contents, '8bit')) {
            throw new RuntimeException('Could not write normalized country geography.');
        }
    }

    private function openOutput(string $path): mixed
    {
        $stream = @fopen($path, 'wb');
        if ($stream === false) {
            throw new RuntimeException("Could not write normalized file [{$path}].");
        }

        return $stream;
    }
}
