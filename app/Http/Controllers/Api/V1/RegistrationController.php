<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\UpdateLocalePreference;
use App\Http\Controllers\Api\V1\Concerns\IssuesApiTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRegistrationRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;

final class RegistrationController extends Controller
{
    use IssuesApiTokens;

    public function store(
        StoreRegistrationRequest $request,
        CreateNewUser $createNewUser,
        UpdateLocalePreference $updateLocalePreference,
    ): JsonResponse {
        $validated = $request->validated();
        $user = $createNewUser->register($request->only(['name', 'email', 'password']));

        if (($validated['locale'] ?? null) !== null) {
            $updateLocalePreference->execute($user, $validated['locale']);
        }

        event(new Registered($user));

        return $this->tokenResponse($request, $user->refresh(), $validated['device_name'], 201);
    }
}
