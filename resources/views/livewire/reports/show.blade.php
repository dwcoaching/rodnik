<li wire:key="reports.show.{{ $report->id }}" >
    @if (! $report->hidden_at)
        <div class="pb-8">
            @if ($hasName)
                <div class="flex justify-between">
                    <a href="{{ route('show', ['springId' => $report->spring_id]) }}" class="group cursor-pointer mr-2">
                        <span class="text-blue-600 group-hover:underline group-hover:text-blue-700 text-xl mr-2 font-semibold ">{{ $report->spring->name }}</span>
                        <span class="text-gray-600 text-sm font-light">#{{ $report->spring_id }}</span>
                    </a>
                    @if (Auth::check() && $report->user_id == Auth::user()->id)
                        <div class="flex-1 text-right">
                            <span wire:click="hideByAuthor" class="text-xs text-gray-400 hover:text-red-600 hover:underline cursor-pointer">удалить</span>
                        </div>
                    @endif
                </div>
            @endif
            <div class="flex mt-1 space-x-3">
                <div class="flex-1">
                  <div class="flex  justify-between">
                    <h3 class="text-base font-light">
                      <span class="font-semibold">{{ Date::parse($report->created_at)->format('j F Y') }},</span>
                      <span class="">{{ Date::parse($report->created_at)->format('H:i') }}</span>,
                      @if ($report->user_id)
                          {{ $report->user->name }}
                      @else
                          Анонимно
                      @endif
                    </h3>
                    @if (! $hasName && Auth::check() && $report->user_id == Auth::user()->id)
                        <div class="flex-1 text-right">
                            <span wire:click="hideByAuthor" class="text-xs text-gray-400 hover:text-red-600 hover:underline cursor-pointer">удалить</span>
                        </div>
                    @endif
                  </div>

                  <div class="mt-1 text-base text-black">
                    {!! nl2br(e($report->comment)) !!}
                  </div>

                  <div class="mt-1">
                      @if ($report->state == 'dry')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-red-600 text-white"> Воды нет </span>
                      @endif

                      @if ($report->state == 'notfound')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-red-600 text-white"> Источник не обнаружен </span>
                      @endif

                      @if ($report->state == 'dripping')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-yellow-400 text-black"> Воды мало </span>
                      @endif

                      @if ($report->state == 'running')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-green-600 text-white"> Вода есть </span>
                      @endif

                      @if ($report->quality == 'bad')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-red-600 text-white"> Вода плохая </span>
                      @endif

                      @if ($report->quality == 'uncertain')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-yellow-400 text-black"> Вода не понятная </span>
                      @endif

                      @if ($report->quality == 'good')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-green-600 text-white"> Вода хорошая </span>
                      @endif
                    </div>



                    <div class="mt-1">
                        <ul role="list" class="pswp-gallery mt-3 grid grid-cols-1 gap-x-3 gap-y-3 sm:grid-cols-1 sm:gap-x-3 xl:grid-cols-2">
                            @foreach ($report->photos as $photo)
                                <li class="">
                                    <div style="padding-bottom: 100%;" class="relative group block w-full h-0 rounded-lg bg-gray-100 overflow-hidden">
                                        <a href="{{ $photo->url }}"
                                            data-pswp-width="{{ $photo->width }}"
                                            data-pswp-height="{{ $photo->height }}"
                                            data-cropped="true"
                                            target="blank" class="photoswipeImage">
                                            <img style="" src="{{ $photo->url }}" alt="" class="object-cover absolute h-full w-full">
                                        </a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($justHidden)
        <div class="pb-8 flex items-center">
            <div class="text-sm text-medium text-gray-600 mr-2">
                Отчет удален
            </div>
            <span wire:click="unhideByAuthor" class="rounded-full border-0 bg-green-600 hover:bg-green-700 cursor-pointer text-white text-xs px-3 py-1">Восстановить</span>
        </div>
    @endif
</li>
