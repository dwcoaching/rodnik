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
        $springs = $user->springs()->with('osm_tags')->withCount(
            [
                'reports' => function(Builder $query) {
                    $query->whereNull('hidden_at');
                }
            ]
        )->get();

        return SpringsGeoJSON::convert($springs);
    }
}
