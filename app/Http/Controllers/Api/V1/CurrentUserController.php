<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CurrentUserResource;
use App\Models\User;
use Illuminate\Http\Request;

final class CurrentUserController extends Controller
{
    public function show(Request $request): CurrentUserResource
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return new CurrentUserResource($user);
    }
}
