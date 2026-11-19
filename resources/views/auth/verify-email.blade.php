<x-app-layout navbar>
    <x-authentication-card
        :title="__('ui.auth.verify.title')"
        :subtitle="__('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.')"
    >
        <x-auth-alert type="success" class="mb-5"
            :messages="session('status') == 'verification-link-sent' ? __('A new verification link has been sent to the email address you provided during registration.') : null" />

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <button type="submit" class="ui-button ui-button-primary h-11 w-full">{{ __('Resend Verification Email') }}</button>
        </form>

        <x-slot name="footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="font-semibold text-blue-600 hover:text-blue-700 hover:underline">{{ __('Log Out') }}</button>
            </form>
        </x-slot>
    </x-authentication-card>
</x-app-layout>
