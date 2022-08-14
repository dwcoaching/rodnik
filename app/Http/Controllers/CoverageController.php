<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OverpassCheck;

class CoverageController extends Controller
{
    public function index()
    {
        $overpassChecks = OverpassCheck::orderBy('longitude_from')
            ->orderBy('latitude_from', 'desc')
            ->get();

        $map = $overpassChecks->groupBy('latitude_from');

        return view('coverage.index', compact('map'));
    }
}
