<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use Generator;
use JsonException;
use RuntimeException;

/** Read a FeatureCollection without materializing its complete coordinate tree. */
final class GeoJsonFeatureStream
{
    private string $buffer = '';

    private int $offset = 0;

    private function __construct(private mixed $file) {}

    public static function features(string $path): Generator
    {
        $file = @fopen($path, 'rb');

        if ($file === false) {
            throw new RuntimeException("Could not read GeoJSON source [{$path}].");
        }

        try {
            yield from (new self($file))->collection();
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid GeoJSON JSON syntax: '.$exception->getMessage(), previous: $exception);
        } finally {
            fclose($file);
        }
    }

    /** Stream the legacy flat array of geocoded water-source records. */
    public static function records(string $path): Generator
    {
        $file = @fopen($path, 'rb');

        if ($file === false) {
            throw new RuntimeException("Could not read geocoding reference source [{$path}].");
        }

        try {
            yield from (new self($file))->recordArray();
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid geocoding reference JSON syntax: '.$exception->getMessage(), previous: $exception);
        } finally {
            fclose($file);
        }
    }

    private function recordArray(): Generator
    {
        $this->expect('[');
        $this->whitespace();

        if ($this->peek() !== ']') {
            while (true) {
                $record = json_decode($this->rawValue(), true, 512, JSON_THROW_ON_ERROR);

                if (! is_array($record)) {
                    throw new RuntimeException('Every geocoding reference record must be an object.');
                }

                yield $record;
                unset($record);
                $this->whitespace();

                if ($this->peek() === ']') {
                    break;
                }

                $this->expect(',');
            }
        }

        $this->expect(']');
        $this->whitespace();

        if ($this->peek() !== null) {
            throw new RuntimeException('Expected exactly one geocoding reference array.');
        }
    }

    private function collection(): Generator
    {
        $this->expect('{');
        $keys = [];
        $type = null;
        $featuresSeen = false;

        while (true) {
            $key = json_decode($this->rawString(), true, 512, JSON_THROW_ON_ERROR);

            if (isset($keys[$key])) {
                throw new RuntimeException("Duplicate GeoJSON root property [{$key}].");
            }

            $keys[$key] = true;
            $this->expect(':');

            if ($key === 'features') {
                $featuresSeen = true;
                $this->expect('[');
                $this->whitespace();

                if ($this->peek() !== ']') {
                    while (true) {
                        $feature = json_decode($this->rawValue(), true, 512, JSON_THROW_ON_ERROR);

                        if (! is_array($feature)) {
                            throw new RuntimeException('Every GeoJSON feature must be an object.');
                        }

                        yield $feature;
                        unset($feature);
                        $this->whitespace();

                        if ($this->peek() === ']') {
                            break;
                        }

                        $this->expect(',');
                    }
                }

                $this->expect(']');
            } else {
                $value = json_decode($this->rawValue(), true, 512, JSON_THROW_ON_ERROR);

                if ($key === 'type') {
                    $type = $value;
                }
            }

            $this->whitespace();

            if ($this->peek() === '}') {
                break;
            }

            $this->expect(',');
        }

        $this->expect('}');
        $this->whitespace();

        if ($type !== 'FeatureCollection' || ! $featuresSeen || $this->peek() !== null) {
            throw new RuntimeException('Expected exactly one GeoJSON FeatureCollection with a features array.');
        }
    }

    private function rawValue(): string
    {
        $this->whitespace();
        $first = $this->peek();

        if ($first === '"') {
            return $this->rawString();
        }

        $raw = '';
        $stack = [];
        $quoted = false;
        $escaped = false;

        while (($character = $this->peek()) !== null) {
            if (! $quoted && $stack === [] && $raw !== '' && ($character === ',' || $character === '}' || $character === ']' || ctype_space($character))) {
                return $raw;
            }

            $this->offset++;
            $raw .= $character;

            if ($quoted) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $quoted = false;
                }

                continue;
            }

            if ($character === '"') {
                $quoted = true;
            } elseif ($character === '{' || $character === '[') {
                $stack[] = $character === '{' ? '}' : ']';
            } elseif ($character === '}' || $character === ']') {
                if (array_pop($stack) !== $character) {
                    throw new RuntimeException('Unbalanced GeoJSON JSON structure.');
                }

                if ($stack === []) {
                    return $raw;
                }
            }
        }

        if ($raw === '' || $stack !== [] || $quoted) {
            throw new RuntimeException('Incomplete GeoJSON JSON value.');
        }

        return $raw;
    }

    private function rawString(): string
    {
        $this->whitespace();
        $this->expect('"');
        $raw = '"';
        $escaped = false;

        while (($character = $this->peek()) !== null) {
            $this->offset++;
            $raw .= $character;

            if ($escaped) {
                $escaped = false;
            } elseif ($character === '\\') {
                $escaped = true;
            } elseif ($character === '"') {
                return $raw;
            }
        }

        throw new RuntimeException('Incomplete GeoJSON JSON string.');
    }

    private function expect(string $expected): void
    {
        $this->whitespace();

        if ($this->peek() !== $expected) {
            throw new RuntimeException("Expected [{$expected}] in GeoJSON JSON structure.");
        }

        $this->offset++;
    }

    private function whitespace(): void
    {
        while (($character = $this->peek()) !== null && in_array($character, [' ', "\t", "\r", "\n"], true)) {
            $this->offset++;
        }
    }

    private function peek(): ?string
    {
        if ($this->offset >= mb_strlen($this->buffer, '8bit')) {
            $buffer = fread($this->file, 65536);
            $this->offset = 0;

            if ($buffer === false) {
                throw new RuntimeException('Could not finish reading GeoJSON source.');
            }

            $this->buffer = $buffer;

            if ($this->buffer === '') {
                return null;
            }
        }

        return $this->buffer[$this->offset];
    }
}
