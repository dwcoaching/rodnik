@props([
    'name',
    'label',
    'type' => 'text',
    'hint' => null,
    // The login page reports a failed attempt above the form: it is about both fields.
    'inlineError' => true,
])

@php
    $id = $attributes->get('id', $name);
    $error = $inlineError ? $errors->first($name) : null;
    $isPassword = $type === 'password';
    $describedBy = $error ? $id.'-error' : ($hint ? $id.'-hint' : null);
@endphp

<div @if ($isPassword) x-data="{ shown: false }" @endif>
    <div class="flex items-baseline justify-between gap-3">
        <label for="{{ $id }}" class="block text-sm font-semibold text-gray-800">{{ $label }}</label>
        {{ $action ?? '' }}
    </div>

    <div class="relative mt-1.5">
        <input
            {{ $attributes->except('class')->merge(['id' => $id, 'name' => $name, 'type' => $type]) }}
            @if ($isPassword) :type="shown ? 'text' : 'password'" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            @class([
                'block h-11 w-full rounded-lg border bg-white px-3 text-base text-gray-900 shadow-xs transition-colors focus:ring-2',
                'pr-11' => $isPassword,
                'border-stone-300 focus:border-blue-600 focus:ring-blue-600/20' => ! $error,
                'border-red-500 focus:border-red-600 focus:ring-red-600/20' => $error,
            ])
        >

        @if ($isPassword)
            <button
                type="button"
                @click="shown = ! shown"
                :aria-pressed="shown ? 'true' : 'false'"
                :aria-label="shown ? @js(__('ui.auth.hide_password')) : @js(__('ui.auth.show_password'))"
                aria-label="{{ __('ui.auth.show_password') }}"
                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-gray-500 transition-colors hover:text-gray-900 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue-600"
            >
                <svg x-show="! shown" class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
                    <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" />
                </svg>
                <svg x-show="shown" x-cloak class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" />
                    <path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" />
                </svg>
            </button>
        @endif
    </div>

    @if ($error)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm text-red-700">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-sm text-gray-500">{{ $hint }}</p>
    @endif
</div>
