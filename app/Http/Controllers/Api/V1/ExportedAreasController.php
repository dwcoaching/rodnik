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
        if (! in_array($area, ['armenia', 'yerevan', 'lycian-way'])) {
            abort(404);
        }

        $armeniaBoundingBox = [
            ['latitude', '>=', 38.8],
            ['latitude', '<=', 41.4],
            ['longitude', '>=', 43.4],
            ['longitude', '<=', 46.7],
        ];

        $lycianWayBoudingBox = [
            ['latitude', '>=', 36],
            ['latitude', '<=', 37],
            ['longitude', '>=', 29],
            ['longitude', '<=', 31],
        ];

        $boundingBox = match ($area) {
            'armenia' => $armeniaBoundingBox,
            'yerevan' => $armeniaBoundingBox,
            'lycian-way' => $lycianWayBoudingBox,
        };

        $springs = Spring::where($boundingBox)->with(['reports.photos'])->get();

        if (extension_loaded('geos')) {
            $areafile = match($area) {
                'armenia' => 'geojson/armenia.geojson',
                'yerevan' => 'geojson/yerevan.geojson',
                'lycian-way' => null,
            };

            if ($areafile) {
                $area = \geoPHP::load(file_get_contents(resource_path($areafile)), 'json');

                $springs = $springs->filter(function ($spring) use ($area) {
                    $point = \geoPHP::load('POINT('.$spring->longitude.' '.$spring->latitude.')', 'wkt');
                    return $area->contains($point);
                });
            }
        }

        return ExportedSpringResource::collection($springs)->toJson(
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );
    }
}
