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
        if (! in_array($area, ['armenia'])) {
            abort(404);
        }

        $springs = Spring::where([
            ['latitude', '>=', 38.8],
            ['latitude', '<=', 41.4],
            ['longitude', '>=', 43.4],
            ['longitude', '<=', 46.7],
        ])->with(['reports.photos'])->get();

        return ExportedSpringResource::collection($springs);
    }
}
