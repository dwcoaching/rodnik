<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OverpassBatch;
use App\Models\OverpassCheck;
use Illuminate\Support\Facades\DB;

class CoverageController extends Controller
{
    public function index(OverpassBatch $overpassBatch)
    {
        // $overpassChecks = $overpassBatch->overpassChecks()
        //     ->orderBy('longitude_from')
        //     ->orderBy('latitude_from', 'desc')
        //     ->get();

        $overpassChecks = DB::table('overpass_checks')
            ->select([
                'latitude_from',
                'latitude_to',
                'longitude_from',
                'longitude_to',
                'covered_by',
            ])
            ->where('overpass_batch_id', $overpassBatch->id)
            ->orderBy('longitude_from')
            ->orderBy('latitude_from', 'desc')
            ->get();

        $map = $overpassChecks->groupBy('latitude_from');

        return view('coverage.index', compact('map'));
    }
}
