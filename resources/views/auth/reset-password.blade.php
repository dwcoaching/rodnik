<x-app-layout navbar>
    <x-authentication-card :title="__('ui.auth.reset.title')">
        <x-auth-alert class="mb-5" :messages="collect($errors->getMessages())->except(['email', 'password'])->flatten()->all()" />

        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <x-auth-field name="email" type="email" :label="__('ui.auth.email')" :value="old('email', $request->email)"
                required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" />

            <x-auth-field name="password" type="password" :label="__('ui.auth.reset.password')" :hint="__('ui.auth.password_hint')"
                required autocomplete="new-password" />

            <button type="submit" class="ui-button ui-button-primary h-11 w-full">{{ __('ui.auth.reset.submit') }}</button>
        </form>
    </x-authentication-card>
</x-app-layout>
