<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\UploadReportPhotoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReportPhotoRequest;
use App\Http\Resources\Api\V1\PhotoResource;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class ReportPhotoController extends Controller
{
    public function store(
        StoreReportPhotoRequest $request,
        Report $report,
        UploadReportPhotoAction $uploadReportPhoto,
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $photo = $uploadReportPhoto($user, $report, $request->validated());

        return (new PhotoResource($photo))->response()->setStatusCode(201);
    }
}
