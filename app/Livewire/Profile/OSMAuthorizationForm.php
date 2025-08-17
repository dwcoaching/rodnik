<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Services\OSMTokenService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class OSMAuthorizationForm extends Component
{
    private OSMTokenService $osmTokenService;

    public function mount(OSMTokenService $osmTokenService)
    {
        $this->osmTokenService = $osmTokenService;
    }

    public function hydrate(OSMTokenService $osmTokenService)
    {
        $this->osmTokenService = $osmTokenService;
    }

    public function authorizeOSM()
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        $authUrl = $this->osmTokenService->getAuthUrl($user);

        return redirect($authUrl);
    }

    public function revokeOSM()
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        $this->osmTokenService->revokeToken($user);

        // TODO: make event for API request to revoke OSM token?
    }

    public function hasOSMToken(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        return $this->osmTokenService->hasToken($user);
    }

    public function render()
    {
        return view('livewire.profile.o-s-m-authorization-form');
    }
}
