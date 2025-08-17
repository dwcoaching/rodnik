<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OSMToken;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class OSMTokenService
{
    public function getAuthUrl(User $user): string
    {
        $state = $this->generateState($user);

        $params = [
            'response_type' => 'code',
            'client_id' => config('osm.oauth.client_id'),
            'redirect_uri' => config('osm.oauth.redirect_uri'),
            'scope' => config('osm.oauth.scope'),
            'state' => $state,
        ];

        return config('osm.oauth.auth_url').'?'.http_build_query($params);
    }

    public function handleCallback(string $code, string $state): ?OSMToken
    {
        $user = $this->getUserFromState($state);

        if (!$user) {
            return null;
        }

        $tokenResponse = $this->exchangeCodeForToken($code);

        if (!$tokenResponse) {
            return null;
        }

        return $this->saveToken($user, $tokenResponse['access_token']);
    }

    public function hasToken(User $user): bool
    {
        return $user->osmToken()->exists();
    }

    public function getToken(User $user): ?string
    {
        $token = $user->osmToken;

        return $token ? $token->access_token : null;
    }

    public function getUserInfo(User $user): ?array
    {
        $token = $this->getToken($user);

        if (!$token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])->get(config('osm.api.user_details_url'));

            if (!$response->successful()) {
                return null;
            }

            $userData = $response->json();

            if (!isset($userData['user'])) {
                return null;
            }

            return $userData['user'];
        } catch (Exception $e) {
            Log::error('Error getting OSM user info: '.$e->getMessage());

            return null;
        }
    }

    public function revokeToken(User $user): bool
    {
        $token = $this->getToken($user);

        if (!$token) {
            return false;
        }

        try {
            $response = Http::withBasicAuth(
                config('osm.oauth.client_id'),
                config('osm.oauth.client_secret')
            )->asForm()->post(config('osm.oauth.revoke_url'), [
                'token' => $token,
            ]);

            if (!$response->successful()) {
                Log::error('Error revoking OSM token: HTTP '.$response->status());
            }
        } catch (Exception $e) {
            Log::error('Error revoking OSM token: '.$e->getMessage());
        }

        $user->osmToken()->delete();

        return true;
    }

    private function generateState(User $user): string
    {
        $state = Str::random(32);
        session(['osm_state' => $state, 'osm_user_id' => $user->id]);

        return $state;
    }

    private function getUserFromState(string $state): ?User
    {
        $sessionState = session('osm_state');
        $userId = session('osm_user_id');

        if ($sessionState !== $state || !$userId) {
            return null;
        }

        session()->forget(['osm_state', 'osm_user_id']);

        return User::find($userId);
    }

    private function exchangeCodeForToken(string $code): ?array
    {
        $response = Http::asForm()->post(config('osm.oauth.token_url'), [
            'grant_type' => 'authorization_code',
            'client_id' => config('osm.oauth.client_id'),
            'client_secret' => config('osm.oauth.client_secret'),
            'code' => $code,
            'redirect_uri' => config('osm.oauth.redirect_uri'),
        ]);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    private function saveToken(User $user, string $accessToken): OSMToken
    {
        return OSMToken::updateOrCreate(
            ['user_id' => $user->id],
            ['access_token' => $accessToken]
        );
    }
}
