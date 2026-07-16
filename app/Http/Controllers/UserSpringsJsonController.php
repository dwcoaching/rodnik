<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Library\SpringsGeoJSON;
use App\Models\User;
use Illuminate\Http\Request;

final class UserSpringsJsonController extends Controller
{
    public function index(Request $request, User $user)
    {
        $springs = $user->springs()
            ->with('osm_tags')
            ->withVisibleReportConditions()
            ->withCount('visibleReports as reports_count')
            ->get();

        return SpringsGeoJSON::convert($springs);
    }
}
