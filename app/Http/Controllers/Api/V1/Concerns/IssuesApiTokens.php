<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Resources\Api\V1\CurrentUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait IssuesApiTokens
{
    private function tokenResponse(Request $request, User $user, string $deviceName, int $status = 200): JsonResponse
    {
        $token = $user->createToken($deviceName, ['*']);

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'user' => (new CurrentUserResource($user))->resolve($request),
            ],
        ], $status);
    }
}
