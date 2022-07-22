<li class="py-4">
  <div class="flex space-x-3">
    @if ($review->user_id)
        <img class="h-6 w-6 rounded-full object-cover" src="{{ $review->user->profile_photo_url }}" alt="{{ $review->user->name }}" />
    @else
        <img class="h-6 w-6 rounded-full" src="https://images.unsplash.com/photo-1517841905240-472988babdf9?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=3&w=256&h=256&q=80" alt="">
    @endif
    <div class="flex-1">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-medium">
          @if ($review->user_id)
              {{ $review->user->name }}
          @else
              Анонимно
          @endif
        </h3>
        <p class="text-sm text-gray-500">{{ Date::parse($review->created_at)->format('j F Y') }}</p>
      </div>

      <div class="mt-2">
          @if ($review->state == 'dry')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-sm text-xs font-medium bg-red-100 text-red-800"> Воды нет </span>
          @endif

          @if ($review->state == 'dripping')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-sm text-xs font-medium bg-yellow-100 text-yellow-800"> Воды мало </span>
          @endif

          @if ($review->state == 'running')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-sm text-xs font-medium bg-green-100 text-green-800"> Вода есть </span>
          @endif

          @if ($review->quality == 'bad')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-sm text-xs font-medium bg-red-100 text-red-800"> Вода плохая </span>
          @endif

          @if ($review->quality == 'uncertain')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-sm text-xs font-medium bg-yellow-100 text-yellow-800"> Вода не понятная </span>
          @endif

          @if ($review->quality == 'good')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-sm text-xs font-medium bg-green-100 text-green-800"> Вода отличная </span>
          @endif
        </div>

        <div class="mt-4 text-sm text-gray-700">
          {!! nl2br(e($review->comment)) !!}
        </div>

        <div class="mt-6">
            <ul role="list" class="mt-3 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 sm:gap-x-6 lg:grid-cols-4 xl:gap-x-8">
                @foreach ($review->photos as $photo)
                    <li class="">
                        <div style="padding-bottom: 100%;" class="relative group block w-full h-0 rounded-lg bg-gray-100 focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-offset-gray-100 focus-within:ring-indigo-500 overflow-hidden">
                            <img style="" src="{{ $photo->url }}" alt="" class="object-cover absolute h-full w-full">
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

  </div>
</li>
