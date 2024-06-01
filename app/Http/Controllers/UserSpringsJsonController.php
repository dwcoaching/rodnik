<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Library\SpringsGeoJSON;
use Illuminate\Contracts\Database\Query\Builder;

class UserSpringsJsonController extends Controller
{
    public function index(Request $request, User $user)
    {
        $springs = $user->springs()
            ->with('osm_tags')
            ->whereNull('springs.hidden_at')
            ->withCount(
            [
                'reports' => function(Builder $query) {
                    $query->whereNull('reports.hidden_at');
                }
            ]
        )->get();

        return SpringsGeoJSON::convert($springs);
    }
}
