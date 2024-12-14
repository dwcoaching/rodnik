<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Spring;
use Illuminate\Http\Request;
use App\Http\Resources\SpringResource;

class WebController extends Controller
{
    public function index(Request $request)
    {
        return view('duo');
    }

    public function user($userId)
    {
        return redirect(route('duo', ['u' => $userId]), 301);
    }
}
