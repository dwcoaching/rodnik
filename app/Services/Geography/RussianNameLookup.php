<?php

declare(strict_types=1);

namespace App\Services\Geography;

use PDO;
use RuntimeException;

/** Disk-backed lookup prevents the full language catalog consuming worker RAM. */
final class RussianNameLookup
{
    private PDO $index;

    public function __construct(string $path, string $cacheDirectory)
    {
        if (! is_file($path) || ! is_readable($path) || ($hash = hash_file('sha256', $path)) === false) {
            throw new RuntimeException("Could not read Russian names [{$path}]. Run geocoding:update-names first.");
        }
        if (! is_dir($cacheDirectory) && ! mkdir($cacheDirectory, 0775, true) && ! is_dir($cacheDirectory)) {
            throw new RuntimeException('Could not create the Russian name cache directory.');
        }
        $cache = $cacheDirectory.'/russian-names-'.$hash.'.sqlite';
        if (! is_file($cache)) {
            $this->build($path, $cache, $hash);
        }
        $this->index = new PDO('sqlite:'.$cache);
        $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /** @param list<int> $ids @return array<int, string> */
    public function names(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $names = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $query = $this->index->prepare('SELECT geonames_id, name FROM names WHERE geonames_id IN ('.implode(',', array_fill(0, count($chunk), '?')).')');
            $query->execute($chunk);
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $names[(int) $row['geonames_id']] = $row['name'];
            }
        }

        return $names;
    }

    private function build(string $path, string $cache, string $hash): void
    {
        $temporary = $cache.'.'.bin2hex(random_bytes(8)).'.tmp';
        $input = @fopen($path, 'rb');
        if ($input === false) {
            throw new RuntimeException('Could not open the Russian name source.');
        }
        try {
            $index = new PDO('sqlite:'.$temporary);
            $index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $index->exec('PRAGMA journal_mode = OFF');
            $index->exec('CREATE TABLE names (geonames_id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
            $statement = $index->prepare('INSERT INTO names VALUES (?, ?)');
            $index->beginTransaction();
            $count = 0;
            while (($line = fgets($input)) !== false) {
                $columns = explode("\t", mb_rtrim($line, "\r\n"));
                if (count($columns) !== 2 || ! ctype_digit($columns[0]) || (int) $columns[0] <= 0 || mb_trim($columns[1]) === '' || ! mb_check_encoding($columns[1], 'UTF-8')) {
                    throw new RuntimeException('Invalid normalized Russian name record.');
                }
                $statement->execute([(int) $columns[0], $columns[1]]);
                $count++;
            }
            if (! feof($input) || $count === 0) {
                throw new RuntimeException('Russian names source is empty or incomplete.');
            }
            $index->commit();
            unset($statement, $index);
            if (hash_file('sha256', $path) !== $hash || ! rename($temporary, $cache)) {
                throw new RuntimeException('Russian names changed during cache generation, or the cache could not be published.');
            }
        } finally {
            fclose($input);
            unset($statement, $index);
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
