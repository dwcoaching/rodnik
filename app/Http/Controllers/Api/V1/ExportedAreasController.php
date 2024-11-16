<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Spring;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExportedSpringResource;

class ExportedAreasController extends Controller
{
    public function show(string $area)
    {
        if (! in_array($area, ['armenia', 'yerevan'])) {
            abort(404);
        }

        $springs = Spring::where([
            ['latitude', '>=', 38.8],
            ['latitude', '<=', 41.4],
            ['longitude', '>=', 43.4],
            ['longitude', '<=', 46.7],
        ])->with(['reports.photos'])->get();

        if (extension_loaded('geos')) {
            $areafile = match($area) {
                'armenia' => 'geojson/armenia.geojson',
                'yerevan' => 'geojson/yerevan.geojson',
            };
            $area = \geoPHP::load(file_get_contents(resource_path($areafile)), 'json');

            $springs = $springs->filter(function ($spring) use ($area) {
                $point = \geoPHP::load('POINT('.$spring->longitude.' '.$spring->latitude.')', 'wkt');
                return $area->contains($point);
            });
        }

        return ExportedSpringResource::collection($springs)->toJson(
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );
    }
}
