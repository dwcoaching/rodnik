<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Spring;
use Illuminate\Support\Facades\Auth;

final class SpringHistoryController extends Controller
{
    public function index(Spring $spring)
    {
        if (! Auth::check()) {
            abort(401);
        }

        $springRevisions = $spring->springRevisions;
        $reports = $spring->reports;

        $events = $springRevisions->concat($reports)
            ->sortByDesc(function ($item) {
                return $item->created_at;
            });

        return view('springs.history.index', compact('spring', 'events'));
    }
}
