<?php

namespace App\Library;

class SpringsGeoJSON
{
    static public function convert($springs)
    {
        $features = $springs->map(function($spring) {
            return [
                'type' => 'Feature',
                'id' => $spring->id,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        floatval($spring->longitude),
                        floatval($spring->latitude)
                    ]
                ],
                'properties' => [
                    'id' => $spring->id,
                    'name' => $spring->name,
                    'intermittent' => $spring->intermittent,
                    'drinking' => $spring->drinking,
                    'hasReports' => $spring->reports_count,
                    'type' => $spring->type(),
                ]
            ];
        });

        $result = [
            "type" => "FeatureCollection",
            "features" => $features
        ];

        $json_encoded = json_encode($result, JSON_UNESCAPED_UNICODE);

        return $json_encoded;
    }
}
