<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateReportAction;
use App\Actions\HideReportAction;
use App\Actions\UpdateReportAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReportRequest;
use App\Http\Requests\Api\V1\UpdateReportRequest;
use App\Http\Resources\Api\V1\ReportResource;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

final class ReportController extends Controller
{
    public function store(StoreReportRequest $request, CreateReportAction $createReport): JsonResponse
    {
        $validated = $request->validated();
        $spring = Spring::query()->findOrFail($validated['spring_id']);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $report = $createReport($user, $spring, Arr::except($validated, 'spring_id'));
        $report->load(['user', 'photos']);

        return (new ReportResource($report))->response()->setStatusCode(201);
    }

    public function update(
        UpdateReportRequest $request,
        Report $report,
        UpdateReportAction $updateReport,
    ): ReportResource {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $report = $updateReport($user, $report, $request->validated());
        $report->load(['user', 'photos']);

        return new ReportResource($report);
    }

    public function destroy(Request $request, Report $report, HideReportAction $hideReport): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $hideReport($user, $report);

        return response()->noContent();
    }
}
