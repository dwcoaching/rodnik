<li>
    @if (! $report->hidden_at)
        <div class="border-t border-slade-200 p-4">
            <div class="flex space-x-3">
                <div class="flex-1">
                    <div class="flex justify-between">
                        <h3 class="flex flex-wrap items-baseline text-base font-light">
                            @if ($report->visited_at)
                                <span class="mr-1 text-sm font-bold">
                                    {{ $report->visited_at->format('F d, Y') }}<span class="text-sm font-regular">,</span>
                                </span>
                            @endif
                            <div class="flex">
                                @if ($report->user_id)
                                    <a class="block flex flex-wrap text-sm text-blue-600 cursor-pointer hover:text-blue-700"
                                        @click.prevent="
                                            window.dispatchEvent(
                                                new CustomEvent('turbo-visit-user',
                                                    {
                                                        detail: {
                                                            userId: {{ intval($report->user_id )}},
                                                        }
                                                    }
                                                )
                                            )
                                        "
                                        href="{{ route('users.show', $report->user) }}">
                                        <div class="mr-1">{{ $report->user->name }}</div>
                                        <div class="-mt-0.5 text-xs font-semibold text-gray-600">{{ $report->user->rating }}</div>
                                    </a>
                                @else
                                    Anonymous
                                @endif
                            </div>
                        </h3>
                        @if (! $report->spring_edit
                            && Auth::check()
                            && $report->user_id == Auth::user()->id)
                            <div class="flex-1 text-right">
                                <a href="{{ route('reports.edit', $report) }}" class="text-xs text-gray-400 hover:text-blue-600 hover:underline cursor-pointer">edit</a>
                                <span wire:click="hideByAuthor" class="ml-1 text-xs text-gray-400 hover:text-red-600 hover:underline cursor-pointer">delete</span>
                            </div>
                        @elseif (! $report->spring_edit
                            && Auth::check()
                            && Auth::user()->is_admin)
                            <div class="flex-1 text-right">
                                <span wire:click="hideByModerator" class="ml-1 text-xs text-gray-400 hover:text-red-600 hover:underline cursor-pointer">hide</span>
                            </div>
                        @endif
                    </div>


                    <div class="mt-1 text-base text-black break-normal [overflow-wrap:anywhere]">
                        {!! nl2br(e($report->comment)) !!}
                    </div>

                    <div class="mt-1">
                        @if ($report->state == 'dry')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-red-600 text-white"> Dry </span>
                        @endif

                        @if ($report->state == 'notfound')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-red-600 text-white"> Water source not found </span>
                        @endif

                      @if ($report->state == 'dripping')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-yellow-400 text-black"> Very little water </span>
                      @endif

                      @if ($report->state == 'running')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-green-600 text-white"> Watered </span>
                      @endif

                      @if ($report->quality == 'bad')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-red-600 text-white"> Poor water </span>
                      @endif

                      @if ($report->quality == 'uncertain')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-yellow-400 text-black"> Questionable water </span>
                      @endif

                      @if ($report->quality == 'good')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-green-600 text-white"> Good water </span>
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
            @if (false)
                <div class="text-xs mt-2 text-gray-500">
                    Added on
                    <span>{{ $report->created_at->format('F d, Y') }}</span>
                    at <span>{{ $report->created_at->format('H:i') }} UTC</span>
                </div>
            @endif
        </div>
    @elseif ($justHidden)
        <div class="border-t border-slade-200 p-4 pb-8 flex items-center">
            @if ($report->hidden_by_author_id)
                <div class="text-sm text-medium text-red-700 mr-2">
                    Report deleted
                </div>
                <span wire:click="unhideByAuthor" class="rounded-full border border-red-600 hover:border-red-700 cursor-pointer text-red-600 text-xs px-3 py-1">Restore</span>
            @endif
            @if ($report->hidden_by_moderator_id)
                <div class="text-sm text-medium text-red-700 mr-2">
                    Report hidden
                </div>
                <span wire:click="unhideByModerator" class="rounded-full border border-red-600 hover:border-red-700 cursor-pointer text-red-600 text-xs px-3 py-1">Unhide</span>
            @endif
        </div>
    @endif
</li>
