<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\ReportQuality;
use App\Enums\ReportState;
use App\Models\Spring;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Report::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'spring_id' => [
                'required',
                'integer',
                Rule::exists(Spring::class, 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->whereNull('hidden_at')
                        ->whereNull('redirect_to_spring_id'),
                ),
            ],
            'visited_at' => [
                'nullable',
                Rule::date()
                    ->format('Y-m-d')
                    ->beforeOrEqual(today($this->validationTimezone())),
            ],
            'state' => ['nullable', Rule::enum(ReportState::class)],
            'quality' => ['nullable', Rule::enum(ReportQuality::class)],
            'access_limited' => ['nullable', 'boolean'],
            'littered' => ['nullable', 'boolean'],
            'broken' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:65535'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
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
