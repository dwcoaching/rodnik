<?php

namespace App\Http\Controllers;

use App\Models\Spring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SpringHistoryController extends Controller
{
    public function index(Spring $spring)
    {
        if (! Auth::check()) {
            abort(401);
        }

        $springRevisions = $spring->springRevisions;
        $reports = $spring->reports;

        $events = $springRevisions->merge($reports)
            ->sortBy(function ($item) {
                return $item->created_at;
            });

        return view('springs.history', compact('spring', 'events'));
    }
}
