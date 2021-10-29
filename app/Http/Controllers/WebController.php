<?php

namespace App\Http\Controllers;

use App\Models\Spring;
use Illuminate\Http\Request;
use App\Http\Resources\SpringResource;

class WebController extends Controller
{
    public function index()
    {
        $springs = SpringResource::collection(Spring::all());

        return view('welcome', compact('springs'));
    }
}
