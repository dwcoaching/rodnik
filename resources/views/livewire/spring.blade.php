<div
    x-data="{}"
    x-on:spring-selected.window="$wire.set('springId', $event.detail.id)"
    >
    <div class="text-xl font-bold">
        💧 Rodnik.today 2.0
    </div>
    @if ($spring)
        <div class="mt-4 text-4xl font-bold">
            {{ $spring->name }}
        </div>
        <div class="mt-3">
            {{ $spring->latitude }}, {{ $spring->longitude }}
        </div>

    @endif
</div>
