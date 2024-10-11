<li>
    @if (! $report->hidden_at)
        <div class="border-t border-stone-200 p-4">
            <div class="flex space-x-3">
                <div class="flex-1">
                    <div class="flex justify-between">
                        <h3 class="flex flex-wrap items-baseline text-base">
                            @if ($report->created_at)
                                <span class="mr-1 text-sm">
                                    {{ $report->created_at->format('d.m.Y') }}<span class="text-sm font-regular">,</span>
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
                            && ($report->user_id == Auth::user()->id
                                || Gate::allows('admin'))
                            )
                            <div x-data>
                                <div x-menu class="relative">
                                    <button x-menu:button
                                        class="rounded-full bg-stone-200 p-1 text-sm font-semibold text-stone-600 hover:bg-stone-300
                                            outline-blue-700 outline-2 outline-offset-[3px]">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                                <path d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM11.5 15.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" />
                                            </svg>

                                    </button>

                                    <div x-menu:items x-cloak
                                        style="display: none;"
                                        class="absolute overflow-hidden border border-stone-300 right-0 w-48 p-1 mt-2 z-10 origin-top-right bg-white rounded-lg shadow-lg
                                        focus:outline-none
                                    ">
                                        @if (! $report->spring_edit
                                            && Auth::check()
                                            && $report->user_id == Auth::user()->id)
                                            <a href="{{ route('reports.edit', $report) }}"
                                                x-menu:item
                                                :class="{
                                                    'bg-stone-200 text-gray-900': $menuItem.isActive,
                                                    'text-gray-600': ! $menuItem.isActive,
                                                    'opacity-50 cursor-not-allowed': $menuItem.isDisabled,
                                                }"
                                                class="flex items-center gap-x-2 rounded-md block w-full px-4 py-3 text-sm font-medium transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4">
                                                    <path d="M13.488 2.513a1.75 1.75 0 0 0-2.475 0L6.75 6.774a2.75 2.75 0 0 0-.596.892l-.848 2.047a.75.75 0 0 0 .98.98l2.047-.848a2.75 2.75 0 0 0 .892-.596l4.261-4.262a1.75 1.75 0 0 0 0-2.474Z" />
                                                    <path d="M4.75 3.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h6.5c.69 0 1.25-.56 1.25-1.25V9A.75.75 0 0 1 14 9v2.25A2.75 2.75 0 0 1 11.25 14h-6.5A2.75 2.75 0 0 1 2 11.25v-6.5A2.75 2.75 0 0 1 4.75 2H7a.75.75 0 0 1 0 1.5H4.75Z" />
                                                </svg>
                                                Edit
                                            </a>
                                            <button type="button"
                                                x-menu:item
                                                wire:click="hideByAuthor"
                                                :class="{
                                                    'bg-red-200 text-red-700': $menuItem.isActive,
                                                    'text-red-600': ! $menuItem.isActive,
                                                    'opacity-50 cursor-not-allowed': $menuItem.isDisabled,
                                                }"
                                                class="flex items-center gap-x-2 rounded-md block w-full px-4 py-3 text-sm font-medium transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4">
                                                    <path fill-rule="evenodd" d="M5 3.25V4H2.75a.75.75 0 0 0 0 1.5h.3l.815 8.15A1.5 1.5 0 0 0 5.357 15h5.285a1.5 1.5 0 0 0 1.493-1.35l.815-8.15h.3a.75.75 0 0 0 0-1.5H11v-.75A2.25 2.25 0 0 0 8.75 1h-1.5A2.25 2.25 0 0 0 5 3.25Zm2.25-.75a.75.75 0 0 0-.75.75V4h3v-.75a.75.75 0 0 0-.75-.75h-1.5ZM6.05 6a.75.75 0 0 1 .787.713l.275 5.5a.75.75 0 0 1-1.498.075l-.275-5.5A.75.75 0 0 1 6.05 6Zm3.9 0a.75.75 0 0 1 .712.787l-.275 5.5a.75.75 0 0 1-1.498-.075l.275-5.5a.75.75 0 0 1 .786-.711Z" clip-rule="evenodd" />
                                                </svg>
                                                Delete
                                            </button>
                                        @elseif (! $report->spring_edit
                                            && Auth::check()
                                            && Gate::allows('admin'))
                                            <button
                                                type="button"
                                                x-menu:item
                                                wire:click="hideByModerator"
                                                wire:confirm="Hide this report?"
                                                :class="{
                                                    'bg-amber-200 text-amber-600': $menuItem.isActive,
                                                    'text-amber-500': ! $menuItem.isActive,
                                                    'opacity-50 cursor-not-allowed': $menuItem.isDisabled,
                                                }"
                                                class="flex items-center gap-x-2 rounded-md block w-full px-4 py-2 text-sm font-medium transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4">
                                                    <path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l10.5 10.5a.75.75 0 1 0 1.06-1.06l-1.322-1.323a7.012 7.012 0 0 0 2.16-3.11.87.87 0 0 0 0-.567A7.003 7.003 0 0 0 4.82 3.76l-1.54-1.54Zm3.196 3.195 1.135 1.136A1.502 1.502 0 0 1 9.45 8.389l1.136 1.135a3 3 0 0 0-4.109-4.109Z" clip-rule="evenodd" />
                                                    <path d="m7.812 10.994 1.816 1.816A7.003 7.003 0 0 1 1.38 8.28a.87.87 0 0 1 0-.566 6.985 6.985 0 0 1 1.113-2.039l2.513 2.513a3 3 0 0 0 2.806 2.806Z" />
                                                </svg>
                                                Hide as Moderator
                                            </button>
                                        @endif
                                    </div>
                                </div>
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
            @if ($report->visited_at)
                <div class="text-sm mt-3 text-gray-500">
                    Date of visit:
                    <span>{{ $report->visited_at->format('d.m.Y') }}</span>
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
