<li>
    @if (! $report->hidden_at)
        <div class="pb-8">
            @if ($hasName)
                <div class="flex justify-between">
                    <a href="{{ route('springs.show', $report->spring) }}" class="group cursor-pointer mr-2">
                        <span class="text-blue-600 group-hover:underline group-hover:text-blue-700 text-xl mr-2 font-semibold ">{{ $report->spring->name ? $report->spring->name : $report->spring->type }}</span>
                        <span class="text-gray-600 text-sm font-light">#{{ $report->spring_id }}</span>
                    </a>
                    @if (! $report->spring_edit
                        && Auth::check()
                        && $report->user_id == Auth::user()->id)
                        <div class="flex-1 text-right">
                            <a href="{{ route('reports.edit', $report) }}" class="text-xs text-gray-400 hover:text-red-600 hover:underline cursor-pointer">редактировать</a>
                            <span wire:click="hideByAuthor" class="ml-1 text-xs text-gray-400 hover:text-red-600 hover:underline cursor-pointer">удалить</span>
                        </div>
                    @endif
                </div>
            @endif
            <div class="flex mt-1 space-x-3">
                <div class="flex-1">
                    <div class="flex justify-between">
                        <h3 class="text-base font-light">
                            <span class="font-semibold">{{ Date::parse($report->visited_at)->format('j F Y') }}<span class="text-sm font-regular">,</span>
                            </span>
                            @if ($report->user_id)
                                <a class="relative text-sm text-blue-600 cursor-pointer hover:text-blue-700" href="{{ route('users.show', $report->user) }}">
                                    <span class="mr-1">{{ $report->user->name }}</span>
                                    <span class="absolute -top-0.5 text-xs font-semibold text-gray-600">{{ $report->user->rating }}</span>
                                </a>
                            @else
                                Анонимно
                            @endif
                        </h3>
                        @if (! $report->spring_edit
                            && ! $hasName && Auth::check()
                            && $report->user_id == Auth::user()->id)
                            <div class="flex-1 text-right">
                                <a href="{{ route('reports.edit', $report) }}" class="text-xs text-gray-400 hover:text-red-600 hover:underline cursor-pointer">редактировать</a>
                                <span wire:click="hideByAuthor" class="ml-1 text-xs text-gray-400 hover:text-red-600 hover:underline cursor-pointer">удалить</span>
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

                    <div class="overflow-clip">
                        @if ($report->new_name)
                            <div class="my-2 flex">
                                <span class="rounded-md bg-gray-200 px-3 py-1 text-sm">
                                    <span class="text-gray-500">{{ $report->old_name }}</span>
                                    → <span class="text-black">{{ $report->new_name }}</span>
                                </span>
                            </div>
                        @endif

                        @if ($report->new_type)
                            <div class="my-2 flex">
                                <span class="rounded-md bg-gray-200 px-3 py-1 text-sm">
                                    <span class="text-gray-500">{{ $report->old_type }}</span>
                                    → <span class="text-black">{{ $report->new_type }}</span>
                                </span>
                            </div>
                        @endif

                        @if ($report->new_latitude || $report->new_longitude)
                            <div class="my-2 flex">
                                <span class="rounded-md bg-gray-200 px-3 py-1 text-sm">
                                    <span class="text-gray-500">{{ $report->old_latitude }}, {{ $report->old_longitude }}</span>
                                    → <span class="text-black">{{ $report->new_latitude }}, {{ $report->new_longitude }}</span>
                                </span>
                            </div>
                            <div class="rounded-md overflow-hidden max-w-sm w-full h-64"
                                x-data
                                x-init="initOpenDiffer($el,
                                    [{{ $report->old_longitude }}, {{ $report->old_latitude }}],
                                    [{{ $report->new_longitude }}, {{ $report->new_latitude }}])">
                            </div>
                        @endif
                    </div>

                    @if ($report->photos->count())
                        <div class="mt-1">
                            <ul role="list" class="pswp-gallery mt-3 grid grid-cols-2 gap-x-3 gap-y-3 sm:grid-cols-2 sm:gap-x-3 lg:grid-cols-3 xl:grid-cols-4">
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
                    @endif
                </div>
            </div>
            @if ($hasName)
                <div class="text-xs mt-2 text-gray-500">
                    Добавлено
                    <span>{{ Date::parse($report->created_at)->format('j F Y') }}</span>
                    в <span>{{ Date::parse($report->created_at)->format('H:i') }} UTC</span>
                </div>
            @endif
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
