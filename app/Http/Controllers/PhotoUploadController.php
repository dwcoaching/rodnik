<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UploadPhotoAction;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

final class PhotoUploadController extends Controller
{
    public function store(Request $request, UploadPhotoAction $upload): JsonResponse
    {
        $photo = $upload($request);

        return response()->json([
            'id' => $photo->id,
            'url' => $photo->url,
            'width' => $photo->width,
            'height' => $photo->height,
        ]);
    }

    public function destroy(Photo $photo): Response
    {
        Gate::authorize('delete', $photo);

        Storage::disk('photos')->delete($photo->filename);
        $photo->delete();

        return response()->noContent();
    }
}
