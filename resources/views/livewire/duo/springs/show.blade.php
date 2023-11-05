<div class="h-full" x-data="{
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
    <div x-show="springId" wire:loading.delay.long.flex class="grow hidden w-full h-full flex justify-center items-center">
        <div class="animate-spin w-6 h-6 border border-4 rounded-full border-gray-400 border-t-transparent"></div>
    </div>
    <div x-cloak wire:loading.remove x-show="springId == $wire.springId">
        <div class="mt-1 bg-white p-4 rounded-lg shadow">
            @if ($spring)
                <div class="" wire:key="spring.{{ $spring->id }}">
                    <div class="flex items-start justify-between flex-nowrap">
                        <div class="text-xl font-extrabold mr-3">
                            <span class="mr-1">
                                {{ $spring->name ? $spring->name : $spring->type }}
                            </span>
                            @if ($spring->name)
                                <span class="font-normal text-slate-600 text-sm mr-3 px-2 py-0.5 bg-slate-200/50 rounded">
                                    {{ $spring->type }}
                                </span>
                            @endif
                            </span>
                        </div>
                        <div class="flex items-center">
                            {{--<span class="mr-3 text-gray-600 text-2xl font-thin">#{{ $spring->id }}</span>--}}
                            @can('update', $spring)
                                <a wire:navigate href="{{ route('springs.edit', $spring) }}"
                                    class="flex items-center text-sm font-regular text-blue-600 cursor-pointer rounded-md bg-white border border-transparent px-2 py-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mr-2">
                                        <path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
                                        <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0010 3H4.75A2.75 2.75 0 002 5.75v9.5A2.75 2.75 0 004.75 18h9.5A2.75 2.75 0 0017 15.25V10a.75.75 0 00-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5z" />
                                    </svg>
                                    Edit
                                </a>
                            @endcan
                        </div>
                    </div>
                    <div class="text-gray-600 mt-1 text-sm flex flex-wrap items-center">
                        <span class="mr-3 mb-2">
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
                                    <span class="">{{ $spring->latitude }}, {{ $spring->longitude }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline -mt-0.5 w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5A3.375 3.375 0 006.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0015 2.25h-1.5a2.251 2.251 0 00-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 00-9-9z" />
                                    </svg>


                                    <span x-cloak x-show="copied" x-transition.opacity.duration.300 class="font-regular">
                                        copied
                                    </span>
                                </div>
                        </span>
                    </div>
                </div>

                @if ($spring->osm_tags->count())
                    <div class="mt-0" x-data="{
                        osmTagsShown: {{ intval($spring->osm_tags->count() && ! $reports->count()) }}
                    }">
                        <div class="text-gray-900 text-sm flex">
                            <div
                                @click="osmTagsShown = ! osmTagsShown"
                                class="flex items-center font-semibold cursor-pointer mr-3 text-blue-600">
                                <div class="mr-1 border-b text-blue-600 border-b-blue-600/30">OSM tags</div>
                                <div class="border-blue-300 bg-blue-100 border rounded-full text-xs px-1.5 py-0.5">{{ $spring->osm_tags->count() }}</div>
                            </div>
                        </div>
                        <div x-show="osmTagsShown"
                            class="bg-stone-100 px-2 pt-0.5 pb-1 mt-1 rounded">
                            @foreach ($spring->osm_tags as $tag)
                                <div class="text-gray-900 mt-1 text-sm">{{ $tag->key }}={{ $tag->value }}</div>
                            @endforeach
                            @if ($spring->osm_node_id)
                                <span class="text-gray-900 mt-1 text-sm">
                                    node id={{ $spring->osm_node_id }}
                                </span>
                            @endif
                            @if ($spring->osm_way_id)
                                <span class="text-gray-900 mt-1 text-sm">
                                    way id={{ $spring->osm_way_id }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($reports->count())
                    <div class="flex mt-3 pb-2 items-end justify-between">
                        <div class="text-xl font-extrabold">Reports</div>
                        <a wire:navigate href="{{ route('reports.create', ['spring_id' => $spring]) }}"
                            type="button" class="inline-flex items-center px-4 py-2 h-[40px] shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                            </svg>
                            New Report
                        </a>
                    </div>
                    <div class="mt">
                        <ul role="list" class="">
                            @foreach ($reports as $report)
                                <livewire:reports.show has-name="false" :report="$report" />
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="mt-16">
                        <a wire:navigate href="{{ route('reports.create', ['spring_id' => $spring]) }}" type="button" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded text-white bg-blue-600 hover:bg-blue-700 focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                            </svg>
                            New Report
                        </a>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
