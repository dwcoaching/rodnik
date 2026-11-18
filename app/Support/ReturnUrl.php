<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Livewire\LivewireManager;

/**
 * The page a visitor came from when they followed a login or registration link.
 *
 * Guests can open most pages (a new report, for instance), so the auth middleware
 * never records where they were. Login and registration links carry the current
 * path in `?redirect=` instead, and the auth pages remember it as the intended URL.
 */
final class ReturnUrl
{
    private const string AUTH_PATHS = '~\A/(?:login|register|logout|forgot-password|reset-password|user/confirm-password|two-factor-challenge|email/verify|livewire)(?:[/?#]|\z)~';

    public function __construct(private readonly LivewireManager $livewire) {}

    /**
     * Link to a Fortify page (login, register) that brings the visitor back here afterwards.
     */
    public function authRoute(Request $request, string $name): string
    {
        $current = $this->current($request);

        return $current === null ? route($name) : route($name, ['redirect' => $current]);
    }

    /**
     * Store an acceptable `?redirect=` path as the URL to return to after authentication.
     */
    public function remember(Request $request): void
    {
        $redirect = $request->query('redirect');

        if (is_string($redirect) && $this->acceptable($redirect)) {
            $request->session()->put('url.intended', url($redirect));
        }
    }

    public function acceptable(string $path): bool
    {
        return mb_strlen($path) <= 2048
            && preg_match('/\A\/(?!\/)[^\\\\\r\n]*\z/', $path) === 1
            && preg_match(self::AUTH_PATHS, $path) !== 1;
    }

    private function current(Request $request): ?string
    {
        // A Livewire update renders under /livewire/update; the page itself is in the snapshot.
        // Its query string is lost there, so the browser refreshes the link before it is followed.
        $path = $this->livewire->isLivewireRequest()
            ? '/'.mb_ltrim((string) $this->livewire->originalPath(), '/')
            : $request->getRequestUri();

        return $this->acceptable($path) ? $path : null;
    }
}
