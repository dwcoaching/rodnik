<?php

declare(strict_types=1);

namespace App\Library;

final class SpringsGeoJSON
{
    public static function convert($springs)
    {
        $features = $springs->map(function ($spring) {
            return [
                'type' => 'Feature',
                'id' => $spring->id,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        (float) ($spring->longitude),
                        (float) ($spring->latitude),
                    ],
                ],
                'properties' => [
                    'id' => $spring->id,
                    'name' => $spring->name,
                    'intermittent' => $spring->intermittent,
                    'hasReports' => $spring->reports_count,
                    'waterConfirmed' => $spring->waterConfirmed(),
                    'score' => $spring->getWaterScore(),
                    'notFound' => $spring->isNotFound(),
                    'type' => $spring->type,
                ],
            ];
        });

        $result = [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];

        $json_encoded = json_encode($result, JSON_UNESCAPED_UNICODE);

        return $json_encoded;
    }
}
