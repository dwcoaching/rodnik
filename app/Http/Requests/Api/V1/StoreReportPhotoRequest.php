<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;

final class StoreReportPhotoRequest extends FormRequest
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
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
