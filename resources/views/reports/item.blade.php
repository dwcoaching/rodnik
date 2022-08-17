<li class="pb-8">
  <a href="{{ route('show', ['springId' => $report->spring_id]) }}" class="display flex items-baseline group cursor-pointer">
    <div class="text-blue-600 group-hover:underline group-hover:text-blue-700 text-xl mr-2 font-semibold ">{{ $report->spring->name }}</div>
    <div class="text-gray-600 text-sm font-light">#{{ $report->spring_id }}</div>
  </a>
  <div class="flex mt-1 space-x-3">
    <div class="flex-1">
      <div class="flex items-center justify-between">
        <h3 class="text-base font-light">
          <span class="font-semibold">{{ Date::parse($report->created_at)->format('j F Y') }},</span>
          <span class="">{{ Date::parse($report->created_at)->format('H:i') }}</span>,
          @if ($report->user_id)
              {{ $report->user->name }}:
          @else
              Анонимно:
          @endif
        </h3>
        <p class="text-sm text-gray-500"></p>
      </div>

      <div class="mt-1 text-base text-black">
        {!! nl2br(e($report->comment)) !!}
      </div>

      <div class="mt-1">
          @if ($report->state == 'dry')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-sm text-xs font-medium bg-red-600 text-white"> Воды нет </span>
          @endif

          @if ($report->state == 'dripping')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-sm text-xs font-medium bg-yellow-400 text-black"> Воды мало </span>
          @endif

          @if ($report->state == 'running')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-green-600 text-white"> Вода есть </span>
          @endif

          @if ($report->quality == 'bad')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-sm text-xs font-medium bg-red-600 text-white"> Вода плохая </span>
          @endif

          @if ($report->quality == 'uncertain')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-sm text-xs font-medium bg-yellow-400 text-black"> Вода не понятная </span>
          @endif

          @if ($report->quality == 'good')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-sm text-xs font-medium bg-green-600 text-white"> Вода отличная </span>
          @endif
        </div>



        <div class="mt-1">
            <ul role="list" class="mt-3 grid grid-cols-1 gap-x-3 gap-y-3 sm:grid-cols-1 sm:gap-x-3 xl:grid-cols-2">
                @foreach ($report->photos as $photo)
                    <li class="">
                        <div style="padding-bottom: 100%;" class="relative group block w-full h-0 rounded-lg bg-gray-100 focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-offset-gray-100 focus-within:ring-blue-500 overflow-hidden">
                            <img style="" src="{{ $photo->url }}" alt="" class="object-cover absolute h-full w-full">
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

  </div>
</li>
