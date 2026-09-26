<svg viewBox="0 0 100 76" class="aspect-100/76 w-full" fill="none" aria-hidden="true">
    <path d="M0 19h100M0 38h100M0 57h100M25 0v76M50 0v76M75 0v76" stroke="#dbeafe" stroke-width="0.6" />
    <path x-show="preview({{ $previewRecord }}.preview)" :d="preview({{ $previewRecord }}.preview)" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
    <g x-show="!preview({{ $previewRecord }}.preview)" stroke="#60a5fa" stroke-width="1.4">
        <circle cx="50" cy="33" r="9" /><path d="M50 20v7m0 12v7M37 33h7m12 0h7" /><circle cx="50" cy="33" r="1.5" fill="#2563eb" stroke="none" />
    </g>
    <text x-show="!preview({{ $previewRecord }}.preview)" x="50" y="61" text-anchor="middle" fill="#64748b" font-size="6" x-text="coordinates({{ $previewRecord }}.center)"></text>
</svg>
