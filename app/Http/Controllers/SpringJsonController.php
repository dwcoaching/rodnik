<?php

namespace App\Http\Controllers;

use App\Models\Spring;
use Illuminate\Http\Request;

class SpringJsonController extends Controller
{
    public function index(Request $request)
    {
        $latitude_from = $request->query('latitude_from', 55);
        $latitude_to = $request->query('latitude_to', 56);
        $longitude_from = $request->query('longitude_from', 37);
        $longitude_to = $request->query('longitude_to', 38);
        $limit = $request->query('limit', 0);

        $springsQuery = Spring::with('osm_tags')
            ->withCount('reports')
            ->where('latitude', '>', $latitude_from)
            ->where('latitude', '<', $latitude_to)
            ->where('longitude', '>', $longitude_from)
            ->where('longitude', '<', $longitude_to);

        if ($limit) {
            $springsQuery->inRandomOrder()
                ->limit($limit);
        }

        $springs = $springsQuery->get();
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

        return json_encode($result, JSON_PRETTY_PRINT);
    }
}
