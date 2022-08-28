<button {{ $attributes }} @click="{{ $key }} = {{ $key }} == '{{ $value }}' ? null : '{{ $value }}'" type="button"
    class="mr-1 mb-2 bg-white inline-flex border-2 items-center px-4 py-2 text-sm font-regular rounded-full shadow"
    :class="{
        'text-gray-600': {{ $key }} != '{{ $value }}',
        'border-blue-600': {{ $key }} == '{{ $value }}',
        'border-white': {{ $key }} != '{{ $value }}',
        'text-blue-700': {{ $key }} == '{{ $value }}'
    }"
>{{ $name }}</button>
