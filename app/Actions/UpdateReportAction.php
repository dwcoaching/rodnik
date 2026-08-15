<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReportQuality;
use App\Enums\ReportState;
use App\Jobs\SendReportNotification;
use App\Library\StatisticsService;
use App\Models\Report;
use App\Models\User;
use DateTimeZone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class UpdateReportAction
{
    public function __invoke(User $user, Report $report, array $attributes): Report
    {
        $this->authorize($user, $report);
        $validated = $this->validate($attributes);

        return $this->execute($report, $validated);
    }

    public function authorize(User $user, Report $report): void
    {
        Gate::forUser($user)->authorize('update', $report);
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(array $attributes): array
    {
        $timezone = $this->validationTimezone($attributes['timezone'] ?? null);

        return Validator::make($attributes, [
            'visited_at' => [
                'sometimes',
                'nullable',
                Rule::date()->format('Y-m-d')->beforeOrEqual(today($timezone)),
            ],
            'state' => ['sometimes', 'nullable', Rule::enum(ReportState::class)],
            'quality' => ['sometimes', 'nullable', Rule::enum(ReportQuality::class)],
            'access_limited' => ['sometimes', 'nullable', 'boolean'],
            'littered' => ['sometimes', 'nullable', 'boolean'],
            'broken' => ['sometimes', 'nullable', 'boolean'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone:all'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Report $report, array $validated): Report
    {
        $attributes = array_replace([
            'visited_at' => $report->visited_at?->format('Y-m-d'),
            'state' => $report->state?->value,
            'quality' => $report->quality?->value,
            'access_limited' => $report->access_limited,
            'littered' => $report->littered,
            'broken' => $report->broken,
            'comment' => $report->comment,
        ], $validated);

        if (in_array($attributes['state'], [ReportState::Dry->value, ReportState::NotFound->value], true)) {
            $attributes['quality'] = null;
        }

        if ($attributes['state'] === ReportState::NotFound->value) {
            $attributes['access_limited'] = null;
            $attributes['littered'] = null;
            $attributes['broken'] = null;
        }

        $report->visited_at = $attributes['visited_at'];
        $report->state = $attributes['state'];
        $report->quality = $attributes['quality'];
        $report->access_limited = $attributes['access_limited'] ? true : null;
        $report->littered = $attributes['littered'] ? true : null;
        $report->broken = $attributes['broken'] ? true : null;
        $report->comment = $attributes['comment'];

        if (! $report->isDirty()) {
            return $report;
        }

        $report->save();
        $report->spring()->firstOrFail()->invalidateTiles();
        StatisticsService::invalidateReportsCount();
        $report->user()->first()?->updateRating();
        SendReportNotification::dispatch($report);

        return $report;
    }

    private function validationTimezone(mixed $timezone): string
    {
        if (is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            return $timezone;
        }

        return config('app.timezone');
    }
}
