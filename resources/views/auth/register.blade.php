<x-app-layout navbar>
    <x-authentication-card :title="__('ui.auth.register.title')" :subtitle="__('ui.auth.register.subtitle')">
        {{-- Errors of the visible fields show next to them. --}}
        <x-auth-alert class="mb-5" :messages="collect($errors->getMessages())->except(['name', 'email', 'password'])->flatten()->all()" />

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <x-auth-field name="name" :label="__('ui.auth.register.name')" :hint="__('ui.auth.register.name_hint')" :value="old('name')"
                required autofocus autocomplete="name" />

            {{--  Honeypot trap against bots: should be empty --}}
            <div class="hidden">
                <x-label for="nickname" value="{{ __('Nickname') }}" />
                <x-input id="nickname" class="block mt-1 w-full" type="text" name="nickname" value="" />
            </div>

            {{--  Honeypot trap against bots: should not be submitted --}}
            <div class="hidden" x-data x-ref="country" x-init="$refs.country.remove()">
                <x-label for="country" value="{{ __('Country') }}" />
                <x-input id="country" class="block mt-1 w-full" type="text" name="country" value="" />
            </div>

            <x-auth-field name="email" type="email" :label="__('ui.auth.email')" :hint="__('ui.auth.register.email_hint')" :value="old('email')"
                required autocomplete="email" autocapitalize="none" spellcheck="false" />

            <x-auth-field name="password" type="password" :label="__('ui.auth.password')" :hint="__('ui.auth.password_hint')"
                required autocomplete="new-password" />

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <label for="terms" class="flex items-start gap-2 text-sm text-gray-700">
                    <x-checkbox name="terms" id="terms" class="mt-0.5" />

                    <span>
                        {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="font-medium text-blue-600 hover:text-blue-700 hover:underline">'.__('Terms of Service').'</a>',
                                'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="font-medium text-blue-600 hover:text-blue-700 hover:underline">'.__('Privacy Policy').'</a>',
                        ]) !!}
                    </span>
                </label>
            @endif

            <button type="submit" class="ui-button ui-button-primary h-11 w-full">{{ __('ui.auth.register.submit') }}</button>
        </form>

        <x-slot name="footer">
            {{ __('ui.auth.register.have_account') }}
            <a href="{{ auth_route('login') }}" data-rodnik-return data-rodnik-navigate class="font-semibold text-blue-600 hover:text-blue-700 hover:underline">{{ __('ui.auth.register.login') }}</a>
        </x-slot>
    </x-authentication-card>
</x-app-layout>
