<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReportResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class UserReportController extends Controller
{
    public const PER_PAGE = 50;

    public function index(Request $request, User $user): AnonymousResourceCollection
    {
        // Public route: read the bearer token if one is sent, so owners and admins
        // also see their reports on hidden sources, as they do on the website.
        $viewer = $request->user('sanctum');

        $reports = $user->reports()
            ->visible()
            ->select('reports.*')
            ->join('springs', 'springs.id', '=', 'reports.spring_id')
            ->when(! $user->showsHiddenSpringsTo($viewer), fn ($query) => $query->whereNull('springs.hidden_at'))
            ->with([
                'user:id,name,profile_photo_path',
                'photos:id,report_id,extension,width,height,order',
                'spring:id,name,type,latitude,longitude',
            ])
            ->orderByDesc('reports.created_at')
            ->orderByDesc('reports.id')
            ->paginate(self::PER_PAGE);

        return ReportResource::collection($reports);
    }
}
