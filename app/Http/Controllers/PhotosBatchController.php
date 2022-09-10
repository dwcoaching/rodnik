<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PhotosBatchController extends Controller
{
    public function create()
    {
        return view('photos.create');
    }
}
