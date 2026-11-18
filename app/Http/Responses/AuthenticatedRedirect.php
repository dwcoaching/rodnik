<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Actions\UpdateLocalePreference;
use App\Http\Controllers\LocaleController;
use App\Models\User;
use App\Support\LocaleCookie;
use App\Support\LocalizedUrl;
use App\Support\ReturnUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a visitor who just logged in or registered back to the page they came from,
 * in the language they should see:
 *
 * 1. a language picked during this visit, before signing in;
 * 2. otherwise the language saved in the profile;
 * 3. otherwise the language of the page they came from;
 * 4. otherwise the language they were browsing in as a guest.
 *
 * The result is saved to the profile and to the locale cookie.
 */
final readonly class AuthenticatedRedirect implements LoginResponse, RegisterResponse
{
    public function __construct(
        private LocalizedUrl $localizedUrl,
        private ReturnUrl $returnUrl,
        private UpdateLocalePreference $updateLocalePreference,
    ) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $chosen = $request->session()->pull(LocaleController::CHOSEN_SESSION_KEY);

        if ($request->wantsJson()) {
            return $request->routeIs('register.store')
                ? new JsonResponse('', 201)
                : new JsonResponse(['two_factor' => false]);
        }

        $returnPath = $this->returnPath($request->session()->pull('url.intended'));
        $locale = $this->locale($request->user(), $chosen, $returnPath);

        $this->updateLocalePreference->execute($request->user(), $locale);

        // An absolute URL keeps a trailing slash (saved maps use one) that redirect()->to() would trim.
        $path = $this->localizedUrl->forPath($returnPath ?? '/', $locale);
        $root = mb_rtrim(url('/'), '/');

        return redirect()
            ->to($path === '/' ? $root : $root.$path)
            ->withCookie(LocaleCookie::make($locale));
    }

    private function locale(?User $user, mixed $chosen, ?string $returnPath): string
    {
        $supported = array_keys(config('localization.supported'));

        foreach ([$chosen, $user?->locale] as $candidate) {
            if (is_string($candidate) && in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        return ($returnPath === null ? null : $this->localizedUrl->localeOfPath($returnPath))
            ?? app()->getLocale();
    }

    /**
     * The local part of the intended URL; anything off this site falls back to the map.
     */
    private function returnPath(mixed $intended): ?string
    {
        if (! is_string($intended) || $intended === '') {
            return null;
        }

        $root = mb_rtrim(url('/'), '/');

        if ($intended === $root) {
            return '/';
        }

        if (str_starts_with($intended, $root.'/')) {
            $intended = mb_substr($intended, mb_strlen($root));
        } elseif (str_starts_with($intended, $root.'?') || str_starts_with($intended, $root.'#')) {
            $intended = '/'.mb_substr($intended, mb_strlen($root));
        } elseif (! str_starts_with($intended, '/')) {
            return null;
        }

        return $this->returnUrl->acceptable($intended) ? $intended : null;
    }
}
