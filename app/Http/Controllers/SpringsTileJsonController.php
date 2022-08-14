<?php

namespace App\Http\Controllers;

use App\Models\Spring;
use Illuminate\Http\Request;

class SpringsTileJsonController extends Controller
{
    public function show(Request $request, $z, $x, $y)
    {
        $tileCount = pow(2, $z);
        $longitude_from = $x / $tileCount * 360 - 180;
        $longitude_to = ($x + 1) / $tileCount * 360 - 180;

        $latitude_from = rad2deg(atan(sinh(pi() * (1 - 2 * $y / $tileCount))));
        $latitude_to = rad2deg(atan(sinh(pi() * (1 - 2 * ($y - 1) / $tileCount))));

        $springs = Spring::with('osm_tags')
            ->where('latitude', '>', $latitude_from)
            ->where('latitude', '<', $latitude_to)
            ->where('longitude', '>', $longitude_from)
            ->where('longitude', '<', $longitude_to)
            ->limit(100)
            // ->whereDoesntHave('osm_tags', function($query) {
            //     $query->where(function($query) {
            //             return $query->where('key', 'amenity')
            //                 ->where('value', 'fountain');
            //         })
            //     ->orWhere(function($query) {
            //             return $query->where('key', 'drinking_water')
            //                 ->where('value', 'no');
            //         });

            // })
            ->get();

        $features = $springs->map(function($spring) {
            return [
                'type' => 'Feature',
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
