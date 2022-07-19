<div
    x-data="{}"
    x-on:spring-selected.window="$wire.setSpring($event.detail.id)"
    >
    @if ($spring)
        <div class="">
            <div class="text-3xl font-bold">
                {{ $spring->name }}
            </div>
            <div class="mt-3 text-gray-500 text-sm flex">
                <span class="mr-1">{{ $spring->latitude }}, {{ $spring->longitude }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                  <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                </svg>
            </div>
        </div>

        <a href="{{ route('reviews.create', ['spring_id' => $spring]) }}" class="mt-3 inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Добавить отзыв</a>

        <div class="mt-3">
            <ul role="list" class="divide-y divide-gray-200">
                @foreach ($reviews as $review)
                    @include('reviews.item')
                @endforeach
            </ul>
        </div>
    @endif
</div>
