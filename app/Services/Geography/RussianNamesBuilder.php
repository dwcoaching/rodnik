<?php

declare(strict_types=1);

namespace App\Services\Geography;

use PDO;
use RuntimeException;
use ZipArchive;

/** Select one current Russian label per stable GeoNames identifier. */
final class RussianNamesBuilder
{
    public function build(string $archivePath, string $destination): int
    {
        $archive = new ZipArchive();
        if ($archive->open($archivePath) !== true) {
            throw new RuntimeException('Could not open the GeoNames alternate-names archive.');
        }
        $input = $archive->getStream('alternateNamesV2.txt');
        if ($input === false) {
            $archive->close();
            throw new RuntimeException('The archive must contain alternateNamesV2.txt.');
        }
        $temporary = tempnam(sys_get_temp_dir(), 'rodnik-russian-names-');
        if ($temporary === false) {
            fclose($input);
            $archive->close();
            throw new RuntimeException('Could not create a temporary names index.');
        }

        $output = null;
        try {
            $index = new PDO('sqlite:'.$temporary);
            $index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $index->exec('PRAGMA journal_mode = OFF');
            $index->exec('CREATE TABLE names (geonames_id INTEGER PRIMARY KEY, name TEXT NOT NULL, score INTEGER NOT NULL, alternate_id INTEGER NOT NULL)');
            $statement = $index->prepare('INSERT INTO names VALUES (?, ?, ?, ?) ON CONFLICT(geonames_id) DO UPDATE SET name = excluded.name, score = excluded.score, alternate_id = excluded.alternate_id WHERE excluded.score > names.score OR (excluded.score = names.score AND excluded.alternate_id < names.alternate_id)');
            $index->beginTransaction();
            $lineNumber = 0;
            while (($line = fgets($input)) !== false) {
                $lineNumber++;
                $columns = explode("\t", mb_rtrim($line, "\r\n"));
                if (count($columns) !== 10 || ! ctype_digit($columns[0]) || ! ctype_digit($columns[1])) {
                    throw new RuntimeException("Invalid GeoNames alternate-name record on line {$lineNumber}.");
                }
                if ($columns[2] !== 'ru' || $columns[6] === '1' || $columns[7] === '1' || $columns[9] !== '') {
                    continue;
                }
                $name = mb_trim($columns[3]);
                if ($name === '' || ! mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > 255 || preg_match('/[\x00-\x1f\x7f]/', $name) === 1) {
                    continue;
                }
                // Prefer current official names, then ordinary short display names.
                $score = ($columns[4] === '1' ? 4 : 0) + ($columns[5] === '1' ? 1 : 0);
                $statement->execute([(int) $columns[1], $name, $score, (int) $columns[0]]);
            }
            if (! feof($input)) {
                throw new RuntimeException('Could not finish reading the Russian alternate names.');
            }
            $index->commit();

            $output = @fopen($destination, 'wb');
            if ($output === false) {
                throw new RuntimeException('Could not write the Russian names file.');
            }
            $count = 0;
            foreach ($index->query('SELECT geonames_id, name FROM names ORDER BY geonames_id') as $row) {
                $record = $row['geonames_id']."\t".$row['name']."\n";
                if (fwrite($output, $record) !== mb_strlen($record, '8bit')) {
                    throw new RuntimeException('Could not finish writing the Russian names file.');
                }
                $count++;
            }
            if ($count === 0) {
                throw new RuntimeException('The archive contains no current Russian names.');
            }

            return $count;
        } finally {
            if (is_resource($output)) {
                fclose($output);
            }
            fclose($input);
            $archive->close();
            unset($index, $statement);
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
