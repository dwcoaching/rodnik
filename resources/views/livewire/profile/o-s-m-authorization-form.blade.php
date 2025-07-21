<div>
    <x-action-section>
        <x-slot name="title">
            {{ __('OSM Authorization') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Authorize your account to connect with OpenStreetMap for uploading springs data.') }}
        </x-slot>

        <x-slot name="content">
            <div class="max-w-xl">
                @if($this->hasOSMToken())
                    <div class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ __('You have connected to OpenStreetMap.') }}
                        </h3>
                        <div class="mt-3 max-w-xl text-sm text-gray-600">
                            <p>
                                {{ __('Your account is authorized to upload springs data to OSM. You can now submit springs directly to OpenStreetMap.') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <x-danger-button wire:click="revokeOSM" wire:loading.attr="disabled">
                            {{ __('Revoke Access') }}
                        </x-danger-button>
                    </div>
                @else
                    <div class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ __('You have not connected to OpenStreetMap.') }}
                        </h3>
                        <div class="mt-3 max-w-xl text-sm text-gray-600">
                            <p>
                                {{ __('When OpenStreetMap authorization is enabled, you will be able to upload springs data directly to OSM. You may connect your account using the button below.') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <x-button wire:click="authorizeOSM" wire:loading.attr="disabled">
                            {{ __('Authorize OSM') }}
                        </x-button>
                    </div>
                @endif
            </div>
        </x-slot>
    </x-action-section>
</div>
