<x-app-layout navbar>
    <x-authentication-card :title="__('ui.auth.forgot.title')" :subtitle="__('ui.auth.forgot.subtitle')">
        <x-auth-alert type="success" class="mb-5" :messages="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf

            <x-auth-field name="email" type="email" :label="__('ui.auth.email')" :value="old('email')"
                required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" />

            <button type="submit" class="ui-button ui-button-primary h-11 w-full">{{ __('ui.auth.forgot.submit') }}</button>
        </form>

        <x-slot name="footer">
            {{ __('ui.auth.forgot.remembered') }}
            <a href="{{ auth_route('login') }}" data-rodnik-return data-rodnik-navigate class="font-semibold text-blue-600 hover:text-blue-700 hover:underline">{{ __('ui.auth.register.login') }}</a>
        </x-slot>
    </x-authentication-card>
</x-app-layout>
