<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateLocalePreference;
use App\Http\Requests\UpdateLocaleRequest;
use App\Support\LocaleCookie;
use Illuminate\Http\RedirectResponse;

final class LocaleController extends Controller
{
    /**
     * Session key for a language picked during this visit; it outranks the
     * profile's saved language when the visitor signs in afterwards.
     */
    public const string CHOSEN_SESSION_KEY = 'locale.chosen';

    public function __invoke(
        UpdateLocaleRequest $request,
        UpdateLocalePreference $updateLocalePreference,
    ): RedirectResponse {
        $validated = $request->validated();
        $locale = $validated['locale'];

        $updateLocalePreference($request->user(), $locale);

        if (! $request->user()) {
            $request->session()->put(self::CHOSEN_SESSION_KEY, $locale);
        }

        return redirect()->to($validated['redirect'])->withCookie(LocaleCookie::make($locale));
    }
}
