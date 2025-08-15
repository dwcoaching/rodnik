<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HeatmapController extends Controller
{
    public function index()
    {
        return view('heatmap');
    }
}
