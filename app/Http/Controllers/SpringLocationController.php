<?php

namespace App\Http\Controllers;

use App\Models\Spring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SpringLocationController extends Controller
{
    public function edit(Spring $spring)
    {
        return redirect(route('duo', ['s' => $spring, 'location' => true]), 301);
    }
}
