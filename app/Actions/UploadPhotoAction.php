<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Photo;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;

final class UploadPhotoAction
{
    public function __invoke(Request $request): Photo
    {
        $this->authorize($request);
        $validated = $this->validate($request);

        return $this->execute($validated);
    }

    public function authorize(Request $request): void
    {
        if ($request->filled('report_id')) {
            Gate::authorize('update', Report::findOrFail($request->integer('report_id')));

            return;
        }

        Gate::authorize('create', Report::class);
    }

    public function validate(Request $request): array
    {
        return Validator::make($request->all(), [
            'photo' => ['required', 'image', 'max:10240'],
            'report_id' => ['nullable', 'integer', 'exists:reports,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ])->validate();
    }

    public function execute(array $validated): Photo
    {
        $file = $validated['photo'];

        $image = Image::make($file)->orientate();

        $photo = new Photo();
        $photo->original_extension = $file->getClientOriginalExtension();
        $photo->original_filename = $file->getClientOriginalName();
        $photo->extension = 'jpg';

        $image->resize(1280, 1280, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        $photo->width = $image->width();
        $photo->height = $image->height();

        $photo->latitude = $validated['latitude'] ?? null;
        $photo->longitude = $validated['longitude'] ?? null;
        $photo->save();

        Storage::disk('photos')->put($photo->filename, $image->stream('jpg', 80));

        return $photo;
    }
}
