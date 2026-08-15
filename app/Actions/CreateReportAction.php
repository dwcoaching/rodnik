<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReportQuality;
use App\Enums\ReportState;
use App\Jobs\SendReportNotification;
use App\Library\StatisticsService;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use DateTimeZone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CreateReportAction
{
    public function __invoke(User $user, Spring $spring, array $attributes): Report
    {
        $this->authorize($user);
        $validated = $this->validate($spring, $attributes);

        return $this->execute($user, $spring, $validated);
    }

    public function authorize(User $user): void
    {
        Gate::forUser($user)->authorize('create', Report::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(Spring $spring, array $attributes): array
    {
        if ($spring->hidden_at !== null || $spring->redirect_to_spring_id !== null) {
            throw ValidationException::withMessages([
                'spring_id' => ['The selected spring is unavailable.'],
            ]);
        }

        $timezone = $this->validationTimezone($attributes['timezone'] ?? null);

        return Validator::make($attributes, [
            'visited_at' => [
                'nullable',
                Rule::date()->format('Y-m-d')->beforeOrEqual(today($timezone)),
            ],
            'state' => ['nullable', Rule::enum(ReportState::class)],
            'quality' => ['nullable', Rule::enum(ReportQuality::class)],
            'access_limited' => ['nullable', 'boolean'],
            'littered' => ['nullable', 'boolean'],
            'broken' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:65535'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(User $user, Spring $spring, array $validated): Report
    {
        $attributes = array_replace([
            'visited_at' => null,
            'state' => null,
            'quality' => null,
            'access_limited' => null,
            'littered' => null,
            'broken' => null,
            'comment' => null,
        ], $validated);

        $this->normalize($attributes);

        $report = new Report();
        $report->spring_id = $spring->id;
        $report->user_id = $user->id;
        $this->assign($report, $attributes);
        $report->save();

        $spring->invalidateTiles();
        StatisticsService::invalidateReportsCount();
        $user->updateRating();
        SendReportNotification::dispatch($report);

        return $report;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function normalize(array &$attributes): void
    {
        if (in_array($attributes['state'], [ReportState::Dry->value, ReportState::NotFound->value], true)) {
            $attributes['quality'] = null;
        }

        if ($attributes['state'] === ReportState::NotFound->value) {
            $attributes['access_limited'] = null;
            $attributes['littered'] = null;
            $attributes['broken'] = null;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assign(Report $report, array $attributes): void
    {
        $report->visited_at = $attributes['visited_at'];
        $report->state = $attributes['state'];
        $report->quality = $attributes['quality'];
        $report->access_limited = $attributes['access_limited'] ? true : null;
        $report->littered = $attributes['littered'] ? true : null;
        $report->broken = $attributes['broken'] ? true : null;
        $report->comment = $attributes['comment'];
    }

    private function validationTimezone(mixed $timezone): string
    {
        if (is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            return $timezone;
        }

        return config('app.timezone');
    }
}
