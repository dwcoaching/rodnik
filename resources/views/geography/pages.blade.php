@if ($paginator->hasPages())
    @php
        // The first page is the canonical URL without a page parameter.
        $pageUrl = fn (int $page): string => $page === 1 ? $paginator->path() : $paginator->url($page);
        $link = 'inline-flex min-w-9 justify-center rounded-md border border-zinc-200 px-3 py-1.5 text-blue-700 hover:border-blue-400 hover:bg-blue-50';
    @endphp
    <nav aria-label="{{ __('geography.pages') }}" data-rodnik-pagination class="flex flex-wrap items-center gap-1 text-sm">
        @unless ($paginator->onFirstPage())
            <a href="{{ $pageUrl($paginator->currentPage() - 1) }}" rel="prev" class="{{ $link }}">‹ {{ __('geography.previous') }}</a>
        @endunless
        @foreach (array_slice($paginator->linkCollection()->all(), 1, -1) as $item)
            @if ($item['url'] === null)
                <span class="px-2 text-zinc-500">{{ $item['label'] }}</span>
            @elseif ($item['active'])
                <span aria-current="page" class="inline-flex min-w-9 justify-center rounded-md border border-blue-600 bg-blue-600 px-3 py-1.5 font-semibold text-white">{{ $item['label'] }}</span>
            @else
                <a href="{{ $pageUrl((int) $item['label']) }}" class="{{ $link }}">{{ $item['label'] }}</a>
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $pageUrl($paginator->currentPage() + 1) }}" rel="next" class="{{ $link }}">{{ __('geography.next') }} ›</a>
        @endif
    </nav>
@endif
