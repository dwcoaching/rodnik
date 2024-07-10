<?php

namespace App\Http\Controllers;

use App\Models\Spring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SpringLocationController extends Controller
{
    public function edit(Spring $spring)
    {
        $this->authorize('update', $spring);

        $springId = $spring->id;
        $userId = Auth::user()->id;
        $locationMode = true;

        return view('welcome', compact('springId', 'userId', 'locationMode'));
    }
}
