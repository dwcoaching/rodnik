<?php

namespace App\Http\Controllers;

use App\Models\Spring;
use Illuminate\Http\Request;
use App\Http\Resources\SpringResource;

class WebController extends Controller
{
    public function index()
    {
        $springs = SpringResource::collection(Spring::limit(10000)->get());
        $springId = null;

        return view('welcome', compact('springs', 'springId'));
    }

    public function show($springId)
    {
        return view('welcome', compact('springId'));
    }
}
