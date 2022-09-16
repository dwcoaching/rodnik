<?php

namespace App\Http\Controllers;

use App\Models\SpringTile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SpringTileJsonController extends Controller
{
    public function show(Request $request, $z, $x, $y)
    {
        $tile = SpringTile::fromXYZ($x, $y, $z);
        $tile->saveFile();

        return $tile->geoJSON();
    }
}
