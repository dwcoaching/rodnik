<button {{ $attributes }} @click="{{ $key }} = {{ $key }} ? false : true" type="button"
class="mr-1 mb-2 bg-white inline-flex flex-nowrap whitespace-nowrap border-2 items-center px-4 py-2 text-sm font-regular rounded-full shadow"
    :class="{
        'text-gray-600': ! {{ $key }},
        'border-blue-600': {{ $key }},
        'border-white': ! {{ $key }},
        'text-blue-700': {{ $key }}
    }"
>
    <svg x-show="! {{ $key }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#ddd" class="w-5 h-5 -ml-2 mr-1">
      <circle cx="10" cy="10" r="8"/>
    </svg>

    <svg x-cloak x-show="{{ $key }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 -ml-2 mr-1">
      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
    </svg>
    {{ $name }}
</button>
