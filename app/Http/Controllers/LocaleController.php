<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateLocalePreference;
use App\Http\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

final class LocaleController extends Controller
{
    public function __invoke(
        UpdateLocaleRequest $request,
        UpdateLocalePreference $updateLocalePreference,
    ): RedirectResponse {
        $validated = $request->validated();
        $locale = $validated['locale'];

        $updateLocalePreference($request->user(), $locale);

        return redirect()->to($validated['redirect'])->withCookie(cookie(
            name: config('localization.cookie'),
            value: $locale,
            minutes: config('localization.cookie_minutes'),
            path: '/',
            secure: null,
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
