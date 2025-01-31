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
            ['latitude', '>=', 53.9],
            ['latitude', '<=', 57.6],
            ['longitude', '>=', 34.4],
            ['longitude', '<=', 40.9],
        ])->with(['reports.photos'])->get();
    }

    public function __invoke(Request $request)
    {
        $area = $request->query('area', null);

        switch ($area) {
            case 'mkad':
                $areas = collect([
                    'МКАД' => 'geojson/mkad.geojson',
                ]);
                break;
            case 'moscow':
                $areas = collect([
                    'Москва' => 'geojson/moscow.geojson',
                ]);
                break;
            case 'mo':
                $areas = collect([
                    'Московская область' => 'geojson/mo.geojson',
                ]);
                break;
            case 'moscow-200-km':
                $areas = collect([
                    'Москва 200 км' => 'geojson/moscow-200-km.geojson',
                ]);
                break;
            case 'all':
                $areas = collect([
                    'МКАД' => 'geojson/mkad.geojson',
                    'Москва' => 'geojson/moscow.geojson',
                    'Московская область' => 'geojson/mo.geojson',
                    'Москва 200 км' => 'geojson/moscow-200-km.geojson',
                ]);
                break;
            default:
                return view('stats.moscow.index');
                break;
        }

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
