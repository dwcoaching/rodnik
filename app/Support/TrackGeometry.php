<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use SimpleXMLElement;
use stdClass;

final class TrackGeometry
{
    /** @return array{distance_km: float, preview: list<array{float, float}>, bbox: ?array{float, float, float, float}} */
    public static function summary(stdClass $track): array
    {
        $pointCount = 0;
        foreach ($track->features as $feature) {
            foreach (self::segments($feature->geometry) as $segment) {
                $pointCount += count($segment);
            }
        }
        $step = max(1, (int) ceil($pointCount / 100));
        $index = 0;
        $distance = 0.0;
        $preview = [];
        $bbox = null;
        foreach ($track->features as $feature) {
            foreach (self::segments($feature->geometry) as $segment) {
                $previous = null;
                foreach ($segment as $position) {
                    $point = [(float) $position[0], (float) $position[1]];
                    $bbox = $bbox === null ? [$point[0], $point[1], $point[0], $point[1]] : [min($bbox[0], $point[0]), min($bbox[1], $point[1]), max($bbox[2], $point[0]), max($bbox[3], $point[1])];
                    if ($previous !== null) {
                        $a = sin(deg2rad($point[1] - $previous[1]) / 2) ** 2 + cos(deg2rad($previous[1])) * cos(deg2rad($point[1])) * sin(deg2rad($point[0] - $previous[0]) / 2) ** 2;
                        $distance += 6371 * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
                    }
                    if ($index % $step === 0 || $index === $pointCount - 1) {
                        $preview[] = $point;
                    }
                    $index++;
                    $previous = $point;
                }
            }
        }

        return ['distance_km' => round($distance, 2), 'preview' => $preview, 'bbox' => $bbox];
    }

    public static function name(stdClass $track): string
    {
        foreach ($track->features as $feature) {
            $name = $feature->properties->name ?? null;
            if (is_string($name) && mb_trim($name) !== '') {
                return mb_substr(mb_trim($name), 0, 160);
            }
        }

        return 'GPX track';
    }

    public static function gpx(stdClass $track, string $name): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><gpx version="1.1" creator="Rodnik.today" xmlns="http://www.topografix.com/GPX/1/1"/>');
        $xml->addChild('metadata')->addChild('name', htmlspecialchars($name, ENT_XML1, 'UTF-8'));
        foreach ($track->features as $feature) {
            if ($feature->geometry->type === 'Point') {
                self::point($xml, 'wpt', $feature->geometry->coordinates, $feature->properties);

                continue;
            }
            $route = $xml->addChild('trk');
            self::metadata($route, $feature->properties);
            foreach (self::segments($feature->geometry) as $segment) {
                $section = $route->addChild('trkseg');
                foreach ($segment as $position) {
                    self::point($section, 'trkpt', $position);
                }
            }
        }

        return $xml->asXML();
    }

    /** @return list<list<array{0: int|float, 1: int|float, 2?: int|float|null, 3?: int|float}>> */
    private static function segments(stdClass $geometry): array
    {
        return match ($geometry->type) {
            'MultiLineString' => $geometry->coordinates,
            'LineString' => [$geometry->coordinates],
            default => [[$geometry->coordinates]],
        };
    }

    /** @param array{0: int|float, 1: int|float, 2?: int|float|null, 3?: int|float} $position */
    private static function point(SimpleXMLElement $parent, string $tag, array $position, ?stdClass $properties = null): void
    {
        $point = $parent->addChild($tag);
        $point->addAttribute('lat', (string) $position[1]);
        $point->addAttribute('lon', (string) $position[0]);
        if (isset($position[2])) {
            $point->addChild('ele', (string) $position[2]);
        }
        if (isset($position[3])) {
            $point->addChild('time', (new DateTimeImmutable('@'.(int) $position[3]))->format('Y-m-d\TH:i:s\Z'));
        }
        self::metadata($point, $properties);
    }

    private static function metadata(SimpleXMLElement $node, ?stdClass $properties): void
    {
        foreach (['name', 'cmt', 'desc', 'src', 'sym', 'type'] as $key) {
            if (isset($properties->{$key}) && is_scalar($properties->{$key})) {
                $node->addChild($key, htmlspecialchars((string) $properties->{$key}, ENT_XML1, 'UTF-8'));
            }
        }
    }
}
