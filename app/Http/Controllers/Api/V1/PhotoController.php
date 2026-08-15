<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\DeletePhotoAction;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PhotoController extends Controller
{
    public function destroy(Request $request, Photo $photo, DeletePhotoAction $deletePhoto): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $deletePhoto($user, $photo);

        return response()->noContent();
    }
}
