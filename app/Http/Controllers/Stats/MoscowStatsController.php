<?php

namespace App\Http\Controllers\Stats;

use App\Models\Spring;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MoscowStatsController extends Controller
{
    protected $springs;

    public function __construct()
    {
        if (! extension_loaded('geos')) {
            throw new \Exception('geos not loaded');
        }

        $this->springs = Spring::where([
            ['latitude', '>=', 54],
            ['latitude', '<=', 57],
            ['longitude', '>=', 35],
            ['longitude', '<=', 40.5],
        ])->with(['reports.photos'])->get();
    }

    public function __invoke(Request $request)
    {
        $areas = collect([
            'МКАД' => 'geojson/mkad.geojson',
            // 'Москва' => 'geojson/moscow.geojson',
            // 'Московская область' => 'geojson/mo.geojson',
        ]);

        $resultSet = $areas->map(function ($areafile) {
            return $this->getStatsForArea($areafile);
        });

        return view('stats.moscow', compact('resultSet'));
    }

    public function getStatsForArea($areafile)
    {
        $area = \geoPHP::load(file_get_contents(resource_path($areafile)), 'json');

        $springs = $this->springs->filter(function ($spring) use ($area) {
            $point = \geoPHP::load('POINT('.$spring->longitude.' '.$spring->latitude.')', 'wkt');
            return $area->contains($point);
        });

        $springsGrouped = $springs->mapToGroups(function ($spring) {
            return [$spring->type => $spring];
        })->map(function ($group) {
            return $group->mapToGroups(function ($spring) {
                $visited = $spring->reports->count() > 0 ? 'visited' : 'unknown';
                return [$visited => $spring];
            })->map(function ($category) {
                return $category->count();
            });
        });

        return $springsGrouped;
    }
}
