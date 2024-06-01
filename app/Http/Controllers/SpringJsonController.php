<?php

namespace App\Http\Controllers;

use App\Models\Spring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Database\Eloquent\Builder;

class SpringJsonController extends Controller
{
    public function index(Request $request)
    {
        $latitude_from = $request->query('latitude_from', 55);
        $latitude_to = $request->query('latitude_to', 56);
        $longitude_from = $request->query('longitude_from', 37);
        $longitude_to = $request->query('longitude_to', 38);
        $limit = $request->query('limit', 0);

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
                            $query->whereNull('reports.hidden_at');
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

        $json_encoded = UserSpringsJsonController::convert($springs);

        return $json_encoded;
    }
}
