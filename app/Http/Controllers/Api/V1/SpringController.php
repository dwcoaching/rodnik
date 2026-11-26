<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Springs\PostSpringsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SpringResource;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SpringController extends Controller
{
    public function store(Request $request, PostSpringsAction $postSprings): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $spring = $postSprings($user, $request->only(['latitude', 'longitude', 'name', 'type']));
        $spring->refresh()->load(['geoCountry', 'geoRegion', 'nearestCity']);

        return (new SpringResource($spring))->response()->setStatusCode(201);
    }

    public function show(Spring $spring): SpringResource|RedirectResponse
    {
        abort_if($spring->hidden_at !== null, 404);

        if ($spring->redirect_to_spring_id !== null) {
            $target = $spring->visibleMergeTargetForReports();
            abort_if($target === null, 404);

            return redirect()->route('api.v1.springs.show', ['spring' => $target], 308);
        }

        $spring->load([
            'geoCountry',
            'geoRegion',
            'nearestCity',
            'visibleReports' => fn ($reports) => $reports
                ->select([
                    'id',
                    'spring_id',
                    'user_id',
                    'visited_at',
                    'state',
                    'quality',
                    'access_limited',
                    'littered',
                    'broken',
                    'comment',
                    'created_at',
                    'updated_at',
                ])
                ->with([
                    'user:id,name,profile_photo_path',
                    'photos:id,report_id,extension,width,height,order',
                ])
                ->orderByRaw('COALESCE(visited_at, created_at) DESC')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
        ]);

        return new SpringResource($spring);
    }
}
