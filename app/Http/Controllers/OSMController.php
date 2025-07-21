<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OSMService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class OSMController extends Controller
{
    public function __construct(
        private OSMService $osmService
    ) {
    }

    public function authorizeOSM(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $authUrl = $this->osmService->getAuthUrl($user);

        return redirect($authUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $error = $request->get('error');

        if ($error) {
            return redirect()->route('profile.show')->with('error', 'Ошибка авторизации в OSM: '.$error);
        }

        if (!$code || !$state) {
            return redirect()->route('profile.show')->with('error', 'Неверные параметры авторизации');
        }

        $token = $this->osmService->handleCallback($code, $state);

        if (!$token) {
            return redirect()->route('profile.show')->with('error', 'Не удалось получить токен OSM');
        }

        return redirect()->route('profile.show')->with('success', 'Успешная авторизация в OSM');
    }
}
