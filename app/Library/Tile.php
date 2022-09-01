<?php

namespace App\Library;

use App\Models\Spring;
use Illuminate\Support\Facades\DB;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Database\Eloquent\Builder;

class Tile
{
    public static function createJson($z, $x, $y)
    {
        $tileCount = pow(2, $z);
        $longitude_from = $x / $tileCount * 360 - 180;
        $longitude_to = ($x + 1) / $tileCount * 360 - 180;

        $latitude_from = rad2deg(atan(sinh(pi() * (1 - 2 * $y / $tileCount))));
        $latitude_to = rad2deg(atan(sinh(pi() * (1 - 2 * ($y - 1) / $tileCount))));

        switch ($z) {
            case '0':
            case '5':
                $limit = 1000;
                break;
            default:
                $limit = 0;
                break;
        }

        $springsQuery = Spring::with('osm_tags');

        $latitudeFunction = function($query) use ($latitude_from, $latitude_to, $longitude_from, $longitude_to) {
            $query->where('latitude', '>', $latitude_from)
                ->where('latitude', '<', $latitude_to)
                ->where('longitude', '>', $longitude_from)
                ->where('longitude', '<', $longitude_to);
        };

        $randomQuery = DB::table('springs')
            ->select('id')
            ->where($latitudeFunction)
            ->inRandomOrder()
            ->limit($limit);

        if ($limit) {
            $springsQuery->joinSub($randomQuery, 'randomSprings', function($join) {
                $join->on('springs.id', '=', 'randomSprings.id');
            });
        } else {
            $springsQuery
                ->where($latitudeFunction)
                ->withCount(
                    [
                        'reports' => function(Builder $query) {
                            $query->whereNull('hidden_at');
                        }
                    ]
                );
        }

        Debugbar::startMeasure('sql',);
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
        Debugbar::stopMeasure('sql');

        Debugbar::startMeasure('preparing json');
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
        Debugbar::stopMeasure('preparing json');

        $result = [
            "type" => "FeatureCollection",
            "features" => $features
        ];

        Debugbar::startMeasure('converting to string');
        $json_encoded = json_encode($result, JSON_PRETTY_PRINT);
        Debugbar::stopMeasure('converting to string');

        return $json_encoded;
    }
}
