<li wire:key="reports.show.{{ $report->id }}" >
    @if (! $report->hidden_at)
        <div class="pb-8">
            @if ($hasName)
                <div class="flex justify-between">
                    <a href="{{ route('show', ['springId' => $report->spring_id]) }}" class="display flex items-baseline group cursor-pointer">
                        <div class="text-blue-600 group-hover:underline group-hover:text-blue-700 text-xl mr-2 font-semibold ">{{ $report->spring->name }}</div>
                        <div class="text-gray-600 text-sm font-light">#{{ $report->spring_id }}</div>
                    </a>
                    @if ($report->user_id == Auth::user()->id)
                        <div x-data="{
                            confirmed: false
                        }">
                            <span x-show="! confirmed" @click="confirmed = true" class="text-xs text-gray-400 hover:text-red-600 hover:underline cursor-pointer">удалить?</span>
                            <span wire:click="hideByAuthor" x-cloak x-show="confirmed" class="mr-1 rounded-full bg-red-600 cursor-pointer hover:bg-red-700 text-white text-xs font-bold px-3 py-1">Да, удалить!</span>
                            <span x-cloak x-show="confirmed" @click="confirmed = false" class=" rounded-full border-0 bg-gray-200 hover:bg-green-600 cursor-pointer text-gray-400 hover:text-white text-xs px-3 py-1">Ой, не надо!</span>
                        </div>
                    @endif
                </div>
            @endif
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
                    @if (! $hasName && $report->user_id == Auth::user()->id)
                        <div x-data="{
                            confirmed: false
                        }">
                            <span x-show="! confirmed" @click="confirmed = true" class="text-xs text-gray-400 hover:text-red-600 hover:underline cursor-pointer">удалить?</span>
                            <span wire:click="hideByAuthor" x-cloak x-show="confirmed" class="mr-1 rounded-full bg-red-600 cursor-pointer hover:bg-red-700 text-white text-xs font-bold px-3 py-1">Да, удалить!</span>
                            <span x-cloak x-show="confirmed" @click="confirmed = false" class=" rounded-full border-0 bg-gray-200 hover:bg-green-600 cursor-pointer text-gray-400 hover:text-white text-xs px-3 py-1">Ой, не надо!</span>
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
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-green-600 text-white"> Вода отличная </span>
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
    @endif
</li>
