<x-app-layout navbar>
    <x-authentication-card :title="__('ui.auth.confirm.title')" :subtitle="__('ui.auth.confirm.subtitle')">
        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
            @csrf

            <x-auth-field name="password" type="password" :label="__('ui.auth.password')"
                required autofocus autocomplete="current-password" />

            <button type="submit" class="ui-button ui-button-primary h-11 w-full">{{ __('ui.auth.confirm.submit') }}</button>
        </form>
    </x-authentication-card>
</x-app-layout>
