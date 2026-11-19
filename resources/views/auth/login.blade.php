<x-app-layout navbar>
    <x-authentication-card :title="__('ui.auth.login.title')">
        <x-auth-alert type="success" class="mb-5" :messages="session('status')" />
        <x-auth-alert class="mb-5" :messages="$errors->all()" />

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <x-auth-field name="email" type="email" :label="__('ui.auth.email')" :value="old('email')" :inline-error="false"
                required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" />

            <x-auth-field name="password" type="password" :label="__('ui.auth.password')" :inline-error="false"
                required autocomplete="current-password">
                @if (Route::has('password.request'))
                    <x-slot name="action">
                        <a data-rodnik-navigate href="{{ route('password.request') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700 hover:underline">
                            {{ __('ui.auth.login.forgot') }}
                        </a>
                    </x-slot>
                @endif
            </x-auth-field>

            <input type="hidden" id="remember_me" name="remember" value="true" />

            <button type="submit" class="ui-button ui-button-primary h-11 w-full">{{ __('ui.auth.login.submit') }}</button>
        </form>

        @if (Route::has('register'))
            <x-slot name="footer">
                {{ __('ui.auth.login.no_account') }}
                <a href="{{ auth_route('register') }}" data-rodnik-return data-rodnik-navigate class="font-semibold text-blue-600 hover:text-blue-700 hover:underline">{{ __('ui.auth.login.register') }}</a>
            </x-slot>
        @endif
    </x-authentication-card>
</x-app-layout>
