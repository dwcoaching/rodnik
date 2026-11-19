@props(['type' => 'error', 'messages' => []])

@php
    $messages = array_values(array_filter((array) $messages));
@endphp

@if ($messages !== [])
    <div
        role="{{ $type === 'error' ? 'alert' : 'status' }}"
        {{ $attributes->class([
            'rounded-lg border px-3 py-2.5 text-sm',
            'border-red-200 bg-red-50 text-red-800' => $type === 'error',
            'border-green-200 bg-green-50 text-green-800' => $type !== 'error',
        ]) }}
    >
        @if (count($messages) === 1)
            <p>{{ $messages[0] }}</p>
        @else
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($messages as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
