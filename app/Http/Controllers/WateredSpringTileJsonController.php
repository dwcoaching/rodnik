<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WateredSpringTile;
use Illuminate\Support\Facades\Storage;

class WateredSpringTileJsonController extends Controller
{
    public function show(Request $request, $z, $x, $y)
    {
        $tile = WateredSpringTile::fromXYZ($x, $y, $z);
        $tile->saveFile();

        return $tile->geoJSON();
    }
}
