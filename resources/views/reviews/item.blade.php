<li class="py-4">
  <div class="flex space-x-3">
    <img class="h-6 w-6 rounded-full" src="https://images.unsplash.com/photo-1517841905240-472988babdf9?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=3&w=256&h=256&q=80" alt="">
    <div class="flex-1 space-y-1">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-medium">Анонимно</h3>
        <p class="text-sm text-gray-500">{{ Date::parse($review->created_at)->format('j F Y') }}</p>
      </div>
      <p class="text-sm text-gray-500">{{ $review->comment }}</p>

      <div class="mt-1">
        @if ($review->state == 'dry')
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"> Воды нет </span>
        @endif

        @if ($review->state == 'dripping')
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"> Воды мало </span>
        @endif

        @if ($review->state == 'running')
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"> Вода есть </span>
        @endif

        @if ($review->quality == 'bad')
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"> Вода плохая </span>
        @endif

        @if ($review->quality == 'uncertain')
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"> Вода не понятная </span>
        @endif

        @if ($review->quality == 'good')
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"> Вода отличная </span>
        @endif
      </div>

      <div class="mt-6">
        <ul role="list" class="mt-3 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 sm:gap-x-6 lg:grid-cols-4 xl:gap-x-8">
          <li class="">
            <div style="padding-bottom: 100%;" class="relative group block w-full h-0 rounded-lg bg-gray-100 focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-offset-gray-100 focus-within:ring-indigo-500 overflow-hidden">
              <img style="" src="https://images.unsplash.com/photo-1582053433976-25c00369fc93?ixid=MXwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHw%3D&ixlib=rb-1.2.1&auto=format&fit=crop&w=512&q=80" alt="" class="object-cover absolute h-full w-full">
            </div>
          </li>
          <li class="">
            <div style="padding-bottom: 100%;" class="relative group block w-full h-0 rounded-lg bg-gray-100 focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-offset-gray-100 focus-within:ring-indigo-500 overflow-hidden">
              <img style="" src="https://images.unsplash.com/photo-1582053433976-25c00369fc93?ixid=MXwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHw%3D&ixlib=rb-1.2.1&auto=format&fit=crop&w=512&q=80" alt="" class="object-cover absolute h-full w-full">
            </div>
          </li>

        </ul>
      </div>
    </div>

  </div>
</li>
