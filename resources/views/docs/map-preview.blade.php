<svg viewBox="0 0 100 76" class="block aspect-100/76 w-full" fill="none" aria-hidden="true" focusable="false">
    <path d="M0 19h100M0 38h100M0 57h100M25 0v76M50 0v76M75 0v76" stroke="#dbeafe" stroke-width="0.6" />
    @if ($path !== '')
        <path d="{{ $path }}" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" data-route-preview />
    @else
        <g stroke="#60a5fa" stroke-width="1.4">
            <circle cx="50" cy="38" r="9" /><path d="M50 25v7m0 12v7M37 38h7m12 0h7" /><circle cx="50" cy="38" r="1.5" fill="#2563eb" stroke="none" />
        </g>
    @endif
</svg>
