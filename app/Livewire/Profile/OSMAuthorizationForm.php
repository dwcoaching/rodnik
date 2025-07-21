<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Services\OSMService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class OSMAuthorizationForm extends Component
{
    private ?OSMService $osmService = null;

    public function mount()
    {
        $this->osmService = new OSMService();
    }

    public function authorizeOSM()
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        $authUrl = $this->getOsmService()->getAuthUrl($user);

        return redirect($authUrl);
    }

    public function revokeOSM()
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        $user->osmToken()->delete();

        // TODO: make event for revoke osm token
        $this->dispatch('osm-token-revoked');
    }

    public function hasOSMToken(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        return $this->getOsmService()->hasToken($user);
    }

    public function render()
    {
        return view('livewire.profile.o-s-m-authorization-form');
    }

    private function getOsmService(): OSMService
    {
        if ($this->osmService === null) {
            $this->osmService = new OSMService();
        }

        return $this->osmService;
    }
}
