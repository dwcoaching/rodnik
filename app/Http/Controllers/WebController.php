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
        dd($springs);

        return view('welcome', compact('springs'));
    }

    public function test()
    {
        echo '<h1>';
        echo 2 * 10;
        echo '</h1>';
    }
}
