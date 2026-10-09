<?php

declare(strict_types=1);

namespace App\Services\Geography;

use Generator;
use RuntimeException;

final class GeographyCatalogReader
{
    /** @return Generator<array{code:string,geonames_id:?int,name:string}> */
    public function countries(string $path): Generator
    {
        foreach ($this->rows($path) as $columns) {
            if (count($columns) < 18 || preg_match('/^[A-Z]{2}$/D', $columns[0]) !== 1 || ! ctype_digit($columns[16])) {
                throw new RuntimeException('Invalid GeoNames countryInfo record.');
            }
            yield ['code' => $columns[0], 'geonames_id' => (int) $columns[16] ?: null, 'name' => $this->name($columns[4])];
        }
    }

    /** @return Generator<array{source_key:string,source_country_code:string,admin1_code:string,geonames_id:int,name:string}> */
    public function regions(string $path): Generator
    {
        foreach ($this->rows($path) as $columns) {
            if (count($columns) !== 4 || preg_match('/^([A-Z]{2})\.([^.]+)$/D', $columns[0], $matches) !== 1 || ! ctype_digit($columns[3]) || (int) $columns[3] <= 0) {
                throw new RuntimeException('Invalid GeoNames administrative region record.');
            }
            yield ['source_key' => 'geonames-admin1:'.$columns[0], 'source_country_code' => $matches[1], 'admin1_code' => $matches[2], 'geonames_id' => (int) $columns[3], 'name' => $this->name($columns[1])];
        }
    }

    /** @return Generator<array<string, mixed>> */
    public function cities(string $path): Generator
    {
        foreach ($this->rows($path) as $columns) {
            if (count($columns) !== 19 || ! ctype_digit($columns[0]) || (int) $columns[0] <= 0
                || preg_match('/^[A-Z]{2}$/D', $columns[8]) !== 1
                || ! is_numeric($columns[4]) || ! is_numeric($columns[5])
                || ! is_finite((float) $columns[4]) || ! is_finite((float) $columns[5])
                || abs((float) $columns[4]) > 90 || abs((float) $columns[5]) > 180
                || ($columns[14] !== '' && ! ctype_digit($columns[14]))) {
                throw new RuntimeException('Invalid normalized GeoNames settlement record.');
            }
            if ($columns[6] !== 'P' || in_array($columns[7], ['PPLX', 'PPLQ', 'PPLH', 'PPLCH', 'PPLW'], true)) {
                continue;
            }
            yield [
                'geonames_id' => (int) $columns[0],
                'source_country_code' => $columns[8], 'admin1_code' => $columns[10] ?: null,
                'name' => $this->name($columns[1]), 'latitude' => (float) $columns[4], 'longitude' => (float) $columns[5],
                'population' => $columns[14] === '' ? null : (int) $columns[14],
            ];
        }
    }

    /** @return Generator<list<string>> */
    private function rows(string $path): Generator
    {
        $input = @fopen($path, 'rb');
        if ($input === false) {
            throw new RuntimeException("Could not read geography catalog input [{$path}].");
        }
        try {
            while (($line = fgets($input)) !== false) {
                if (str_starts_with($line, '#') || mb_trim($line) === '') {
                    continue;
                }
                yield explode("\t", mb_rtrim($line, "\r\n"));
            }
            if (! feof($input)) {
                throw new RuntimeException('Could not finish reading a geography catalog input.');
            }
        } finally {
            fclose($input);
        }
    }

    private function name(string $name): string
    {
        $name = mb_trim($name);
        if ($name === '' || ! mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > 255 || preg_match('/[\x00-\x1f\x7f]/', $name) === 1) {
            throw new RuntimeException('Geography names must be nonempty UTF-8 labels of at most 255 characters.');
        }

        return $name;
    }
}
