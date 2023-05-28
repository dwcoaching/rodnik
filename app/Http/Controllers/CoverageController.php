<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OverpassBatch;
use App\Models\OverpassCheck;

class CoverageController extends Controller
{
    public function index(OverpassBatch $overpassBatch)
    {
        $overpassChecks = $overpassBatch->overpassChecks()
            ->orderBy('longitude_from')
            ->orderBy('latitude_from', 'desc')
            ->get();

        $map = $overpassChecks->groupBy('latitude_from');

        return view('coverage.index', compact('map'));
    }
}
