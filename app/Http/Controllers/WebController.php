<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Spring;
use Illuminate\Http\Request;
use App\Http\Resources\SpringResource;

class WebController extends Controller
{
    public function index(Request $request, User $user = null)
    {
        $springId = null;
        $userId = null;

        return view('welcome', compact('springId', 'userId'));
    }

    public function user($userId)
    {
        $springId = null;

        return view('welcome', compact('userId', 'springId'));
    }
}
