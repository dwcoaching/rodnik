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
        $redirect = $this->legacyRedirect($request);
        
        return $redirect ?: view('duo');
    }

    public function user($userId)
    {
        return redirect(duo_route(['user' => $userId]), 301);
    }

    private function legacyRedirect(Request $request)
    {
        $routeParams = [];
        
        if ($request->has('s')) {
            $routeParams['spring'] = $request->get('s');
        }
        
        if ($request->has('u')) {
            $routeParams['user'] = $request->get('u');
        }
        
        if ($request->has('location')) {
            $routeParams['location'] = $request->get('location');
        }
        
        if (!empty($routeParams)) {
            return redirect(duo_route($routeParams), 301);
        }

        return false;
    }
}
