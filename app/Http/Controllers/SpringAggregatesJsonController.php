<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpringAggregate;

class SpringAggregatesJsonController extends Controller
{
    public function index(Request $request)
    {
        $latitude_from = $request->query('latitude_from', 55);
        $latitude_to = $request->query('latitude_to', 56);
        $longitude_from = $request->query('longitude_from', 37);
        $longitude_to = $request->query('longitude_to', 38);
        $step = $request->query('step', 1);

        $springAggregates = SpringAggregate::whereNotNull('count')
            ->where('count', '<>', 0)
            ->where('step', $step)
            ->where('latitude', '>', $latitude_from)
            ->where('latitude', '<', $latitude_to)
            ->where('longitude', '>', $longitude_from)
            ->where('longitude', '<', $longitude_to)
            ->get();

        $features = $springAggregates->map(function($springAggregate) {
            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        floatval($springAggregate->longitude),
                        floatval($springAggregate->latitude)
                    ]
                ],
                'properties' => [
                    'longitude' => floatval($springAggregate->longitude),
                    'latitude' => floatval($springAggregate->latitude),
                    'count' => floatval($springAggregate->count),
                ]
            ];
        });

        $result = [
            "type" => "FeatureCollection",
            "features" => $features
        ];

        return json_encode($result, JSON_PRETTY_PRINT);
    }
}
