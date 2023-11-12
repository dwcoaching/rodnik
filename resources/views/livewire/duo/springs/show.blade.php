<div class="px-0 md:px-4 md:pb-4 h-full bg-stone-100" x-data="{
        previousSpringId: {{ intval($springId) }},
        loadSpring: function() {
            if (this.springId != this.previousSpringId) {
                $wire.setSpring(this.springId)
                this.previousSpringId = this.springId
            }
        }
    }"
    x-on:duo-load-spring.window="loadSpring()"
    x-init="
        if ({{ intval($springId)}} && locateMap) {
            window.rodnikMap.locate({{ json_encode($coordinates) }});
            window.rodnikMap.highlightFeatureById({{ $springId }});
        }

        locateMap = false;
    ">
    <div x-show="springId" wire:loading.delay.long.flex class="h-full w-full hidden justify-center items-center">
        <div class="animate-spin w-6 h-6 border border-4 rounded-full border-gray-400 border-t-transparent"></div>
    </div>
    <div x-cloak wire:loading.remove x-show="springId == $wire.springId">
        @if ($spring)
            <div class="mt-1 bg-white md:rounded-lg shadow">
                <div class="p-4" wire:key="spring.{{ $spring->id }}">
                    <div class="flex items-start justify-between flex-nowrap">
                        <div class="text-xl font-extrabold mr-3">
                            <div class="mr-1">
                                {{ $spring->name ? $spring->name : $spring->type }}
                            </div>
                            <div class="font-light text-gray-900 text-sm">
                                @if ($spring->name)
                                    {{ $spring->type }}
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center">
                            {{--<span class="mr-3 text-gray-600 text-2xl font-thin">#{{ $spring->id }}</span>--}}
                            @can('update', $spring)
                                <details class="dropdown dropdown-end">
                                    <summary tabindex="0" class="text-blue-600 btn btn-sm flex flex-nowrap">
                                        Edit
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    </summary>
                                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow-lg ring-1 ring-black ring-opacity-5 bg-base-100 rounded-box w-52">
                                        <li>
                                            <a href="{{ route('springs.edit', $spring) }}"
                                                class="flex items-center py-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                                    <path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
                                                    <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0010 3H4.75A2.75 2.75 0 002 5.75v9.5A2.75 2.75 0 004.75 18h9.5A2.75 2.75 0 0017 15.25V10a.75.75 0 00-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5z" />
                                                </svg>
                                                Edit
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{ route('springs.history', $spring) }}"
                                                class="flex items-center py-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                                  <path d="M5.127 3.502L5.25 3.5h9.5c.041 0 .082 0 .123.002A2.251 2.251 0 0012.75 2h-5.5a2.25 2.25 0 00-2.123 1.502zM1 10.25A2.25 2.25 0 013.25 8h13.5A2.25 2.25 0 0119 10.25v5.5A2.25 2.25 0 0116.75 18H3.25A2.25 2.25 0 011 15.75v-5.5zM3.25 6.5c-.04 0-.082 0-.123.002A2.25 2.25 0 015.25 5h9.5c.98 0 1.814.627 2.123 1.502a3.819 3.819 0 00-.123-.002H3.25z" />
                                                </svg>
                                                View history
                                                <div class="border-stone-400 bg-stone-100 border rounded-full text-xs px-1.5 py-0.5">{{ $spring->springRevisions->count() }}</div>
                                            </a>
                                        </li>
                                    </ul>
                                </details>
                            @endcan
                        </div>
                    </div>
                    <div class="text-gray-600 mt-3 text-sm md:flex flex-wrap items-center">
                        <div class="mr-3 flex-1">
                            <div class="text-gray-600 text-sm cursor-pointer"
                                x-data="{
                                    copied: false,
                                    timeout: null
                                }"
                                @click="
                                    $clipboard('{{ $spring->latitude }}, {{ $spring->longitude }}');
                                    copied = true;
                                    clearTimeout(timeout);
                                    timeout = setTimeout(() => {
                                        copied = false;
                                    }, 1000)
                                "
                                >
                                    <div class="text-gray-500 font-medium">
                                        Location
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline -mt-0.5 w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5A3.375 3.375 0 006.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0015 2.25h-1.5a2.251 2.251 0 00-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 00-9-9z" />
                                        </svg>
                                        <span x-cloak x-show="copied" x-transition.opacity.duration.300 class="font-regular">
                                            copied
                                        </span>
                                    </div>
                                    <div>
                                        <span class="font-bold">{{ $spring->latitude }}, {{ $spring->longitude }}</span>
                                    </div>
                                </div>
                        </div>
                        <div class="mr-3 mt-2 md:mt-0 flex-1">
                            <div class="text-gray-600 text-sm cursor-pointer"
                                x-data="{
                                    copied: false,
                                    timeout: null
                                }"
                                @click="
                                    $clipboard('{{ route('springs.show', $spring) }}');
                                    copied = true;
                                    clearTimeout(timeout);
                                    timeout = setTimeout(() => {
                                        copied = false;
                                    }, 1000)
                                "
                                >
                                    <div class="text-gray-500 font-medium">
                                        URL
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline -mt-0.5 w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5A3.375 3.375 0 006.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0015 2.25h-1.5a2.251 2.251 0 00-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 00-9-9z" />
                                        </svg>
                                        <span x-cloak x-show="copied" x-transition.opacity.duration.300 class="font-regular">
                                            copied
                                        </span>
                                    </div>
                                    <div class="font-bold">{{ without_http(route('springs.show', $spring)) }}</div>
                                </div>
                        </div>
                    </div>
                </div>

                @if ($spring->osm_tags->count())
                    <div class="p-4 border-t border-slade-200" x-data="{
                        osmTagsShown: {{ intval($spring->osm_tags->count() && ! $reports->count()) }}
                    }">
                        <div @click="osmTagsShown = ! osmTagsShown" class="cursor-pointer text-gray-900 text-sm flex justify-between">
                            <div
                                class="flex items-center font-medium mr-3 text-blue-500">
                                <div class="mr-1 text-blue-600">OSM tags</div>
                                <div class="border-blue-300 bg-blue-100 border rounded-full text-xs px-1.5 py-0.5">{{ $spring->osm_tags->count() }}</div>
                            </div>
                            <div>
                                <svg x-show="! osmTagsShown" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                                <svg x-show="osmTagsShown" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                    <path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 01-1.06-.02L10 8.832 6.29 12.77a.75.75 0 11-1.08-1.04l4.25-4.5a.75.75 0 011.08 0l4.25 4.5a.75.75 0 01-.02 1.06z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <div x-show="osmTagsShown" class="">
                            @if ($spring->osm_node_id)
                                <span class="text-gray-900 text-sm font-bold">
                                    Node Id: {{ $spring->osm_node_id }}
                                </span>
                            @endif
                            @if ($spring->osm_way_id)
                                <span class="text-gray-900 text-sm font-bold">
                                    Way Id: {{ $spring->osm_way_id }}
                                </span>
                            @endif
                            @foreach ($spring->osm_tags as $tag)
                                <div class="text-gray-900 text-sm">{{ $tag->key }}={{ $tag->value }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <div class="mt-1 md:mt-2 bg-white md:rounded-lg shadow">
                <div class="p-4 flex mt-0 items-center justify-between">
                    <div class="text-xl font-extrabold">
                        @if ($reports->count())
                            Reports
                        @else
                            No reports yet
                        @endif
                        </div>
                    <a type="button" href="{{ route('reports.create', ['spring_id' => $spring]) }}" class="btn btn-primary">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                        </svg>
                      New Report
                    </a>
                </div>
                <div class="mt">
                    <ul role="list" class="">
                        @foreach ($reports as $report)
                            <livewire:reports.show :report="$report" :key="$report->id" />
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
</div>
