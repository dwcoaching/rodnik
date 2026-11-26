<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Jetstream\DeleteUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DestroyCurrentUserRequest;
use App\Http\Resources\Api\V1\CurrentUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    /**
     * Permanently deletes the account, the same way as the profile page does on the web.
     */
    public function destroy(DestroyCurrentUserRequest $request, DeleteUser $deleteUser): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $deleteUser->delete($user);

        return response()->noContent();
    }
}
