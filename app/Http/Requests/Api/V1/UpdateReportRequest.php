<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\ReportQuality;
use App\Enums\ReportState;
use App\Models\Report;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('report');

        return $report instanceof Report
            && $report->hidden_at === null
            && ! $report->spring_edit
            && ! $report->from_osm
            && $this->user()?->can('update', $report) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visited_at' => [
                'sometimes',
                'nullable',
                Rule::date()
                    ->format('Y-m-d')
                    ->beforeOrEqual(today($this->validationTimezone())),
            ],
            'state' => ['sometimes', 'nullable', Rule::enum(ReportState::class)],
            'quality' => ['sometimes', 'nullable', Rule::enum(ReportQuality::class)],
            'access_limited' => ['sometimes', 'nullable', 'boolean'],
            'littered' => ['sometimes', 'nullable', 'boolean'],
            'broken' => ['sometimes', 'nullable', 'boolean'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone:all'],
        ];
    }

    public function messages(): array
    {
        return [
            'visited_at.before_or_equal' => __('ui.report.visit_date_future'),
        ];
    }

    private function validationTimezone(): string
    {
        $timezone = $this->input('timezone');

        if (is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            return $timezone;
        }

        return config('app.timezone');
    }
}
