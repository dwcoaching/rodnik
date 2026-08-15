<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Photo;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UploadReportPhotoAction
{
    public function __construct(
        private UploadPhotoAction $uploadPhoto,
    ) {}

    public function __invoke(User $user, Report $report, array $attributes): Photo
    {
        $this->authorize($user, $report);
        $validated = $this->validate($report, $attributes);

        return $this->execute($report, $validated);
    }

    public function authorize(User $user, Report $report): void
    {
        Gate::forUser($user)->authorize('update', $report);
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(Report $report, array $attributes): array
    {
        if ($report->hidden_at !== null || $report->spring_edit || $report->from_osm) {
            throw ValidationException::withMessages([
                'report' => ['Photos cannot be added to this report.'],
            ]);
        }

        return Validator::make($attributes, [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Report $report, array $validated): Photo
    {
        $photo = $this->uploadPhoto->execute($validated);

        DB::transaction(function () use ($photo, $report): void {
            Report::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            $photo->report_id = $report->id;
            $photo->order = ((int) $report->photos()->max('order')) + 1;
            $photo->save();
        });

        return $photo;
    }
}
