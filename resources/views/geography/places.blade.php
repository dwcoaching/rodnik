@php
    // Long lists are split into letter bands, each flowing in short columns,
    // so reading never jumps between screen-high columns.
    $grouped ??= count($places) > 60;
    $groups = $grouped
        ? app(\App\Services\Geography\PublicGeography::class)->byInitial($places)
        : [['letter' => null, 'places' => $places]];
    $level ??= 'h3';
    $href = localized_route($route, [$parameter => '__slug__']);
@endphp
@if ($grouped && count($groups) > 1)
    <nav aria-label="{{ __('geography.letters') }}" class="flex flex-wrap gap-1 text-sm font-semibold">
        @foreach ($groups as $group)
            <a href="#{{ $id }}-{{ $loop->iteration }}" data-rodnik-exact-url class="inline-flex min-w-8 justify-center rounded-md border border-zinc-200 px-2 py-1 text-blue-700 hover:border-blue-400 hover:bg-blue-50">{{ $group['letter'] }}</a>
        @endforeach
    </nav>
@endif
<div>
    @foreach ($groups as $group)
        @if ($grouped)
            <div class="grid gap-x-4 border-t border-zinc-100 py-2 sm:grid-cols-[2rem_minmax(0,1fr)]">
                <{{ $level }} id="{{ $id }}-{{ $loop->iteration }}" class="scroll-mt-4 py-0.5 text-sm font-semibold text-zinc-500">{{ $group['letter'] }}</{{ $level }}>
        @endif
        <ul class="columns-2 gap-x-6 text-sm sm:columns-3 xl:columns-4 *:flex *:break-inside-avoid *:items-baseline *:justify-between *:gap-2 *:py-0.5 [&_a]:text-blue-700 [&_a:hover]:underline [&_span]:shrink-0 [&_span]:tabular-nums [&_span]:text-zinc-500">
            @foreach ($group['places'] as $place)
                <li><a href="{{ str_replace('__slug__', $place->slug, $href) }}">{{ $place->displayName() }}</a><span>{{ number_format((int) $place->sources_count, 0, ',', ' ') }}</span></li>
            @endforeach
        </ul>
        @if ($grouped)
            </div>
        @endif
    @endforeach
</div>
