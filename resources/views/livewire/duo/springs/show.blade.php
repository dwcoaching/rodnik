<div class="px-0 md:px-4 md:pb-4 h-full bg-stone-100" x-data="{}">
    <div wire:loading.delay.long.flex class="h-full w-full hidden justify-center items-center">
        <div class="-top-6 relative animate-spin w-6 h-6 border border-4 rounded-full border-gray-400 border-t-transparent"></div>
    </div>
    <div x-cloak wire:loading.remove class="w-full">
        @if ($spring)
            @if ($spring->hidden_at)
                <div class="alert alert-warning mb-2">
                    <div>
                        <b>This object is hidden.</b> Probably it's not a water source,
                        or a duplicate, or something else.
                    </div>
                </div>
            @endif
            <div class="mt-1 bg-white md:rounded-lg shadow">
                <div class="p-4" wire:key="spring.{{ $spring->id }}">
                    <div class="flex items-start justify-between flex-nowrap">
                        <div class="text-xl font-extrabold mr-3">
                            <div class="mr-1">
                                {{ ( $spring->name ? $spring->name : $spring->type ) ?: 'No name' }}
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
                                <div class="relative">
                                    <div x-data="{
                                        springDropdownOpen: false
                                    }">
                                        <div x-menu x-model="springDropdownOpen" class="relative">
                                            <button x-menu:button
                                                class="rounded-md bg-stone-200 px-2.5 py-1.5 text-sm font-semibold text-stone-600 hover:bg-stone-300
                                                    outline-blue-700 outline-2 outline-offset-[3px]">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                                        <path d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM11.5 15.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" />
                                                    </svg>
                                            </button>

                                            <div x-menu:items x-cloak
                                                style="display: none;"
                                                class="absolute overflow-hidden right-0 w-56 p-1 mt-2 z-10 origin-top-right bg-white rounded-lg border border-stone-300 shadow-lg
                                                focus:outline-none
                                                ">
                                                <a x-menu:item href="{{ route('duo', ['s' => $spring->id, 'location' => true]) }}"
                                                    @click.prevent="
                                                        window.dispatchEvent(
                                                            new CustomEvent('duo-visit',
                                                                {
                                                                    detail: {
                                                                        springId: {{ intval($springId) }},
                                                                        location: true,
                                                                        coordinates: {{ json_encode([
                                                                            floatval($spring->longitude),
                                                                            floatval($spring->latitude)
                                                                        ]) }},
                                                                    }
                                                                }
                                                            )
                                                        )
                                                        springDropdownOpen = false
                                                        "
                                                    :class="{
                                                        'bg-stone-200 text-gray-900': $menuItem.isActive,
                                                        'text-gray-600': ! $menuItem.isActive,
                                                        'opacity-50 cursor-not-allowed': $menuItem.isDisabled,
                                                    }"
                                                    class="flex items-center gap-x-2 rounded-md block w-full px-4 py-3 text-sm font-medium transition-colors">
                                                    {{--<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4">
                                                        <path fill-rule="evenodd" d="m7.539 14.841.003.003.002.002a.755.755 0 0 0 .912 0l.002-.002.003-.003.012-.009a5.57 5.57 0 0 0 .19-.153 15.588 15.588 0 0 0 2.046-2.082c1.101-1.362 2.291-3.342 2.291-5.597A5 5 0 0 0 3 7c0 2.255 1.19 4.235 2.292 5.597a15.591 15.591 0 0 0 2.046 2.082 8.916 8.916 0 0 0 .189.153l.012.01ZM8 8.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" clip-rule="evenodd" />
                                                    </svg>--}}
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="#000000" viewBox="0 0 256 256"><path d="M232,116h-4.72A100.21,100.21,0,0,0,140,28.72V24a12,12,0,0,0-24,0v4.72A100.21,100.21,0,0,0,28.72,116H24a12,12,0,0,0,0,24h4.72A100.21,100.21,0,0,0,116,227.28V232a12,12,0,0,0,24,0v-4.72A100.21,100.21,0,0,0,227.28,140H232a12,12,0,0,0,0-24Zm-92,87v-3a12,12,0,0,0-24,0v3a76.15,76.15,0,0,1-63-63h3a12,12,0,0,0,0-24H53a76.15,76.15,0,0,1,63-63v3a12,12,0,0,0,24,0V53a76.15,76.15,0,0,1,63,63h-3a12,12,0,0,0,0,24h3A76.15,76.15,0,0,1,140,203ZM128,84a44,44,0,1,0,44,44A44.05,44.05,0,0,0,128,84Zm0,64a20,20,0,1,1,20-20A20,20,0,0,1,128,148Z"></path></svg>
                                                    Update Location
                                                </a>
                                                <a x-menu:item href="{{ route('springs.edit', $spring) }}"
                                                    :class="{
                                                        'bg-stone-200 text-gray-900': $menuItem.isActive,
                                                        'text-gray-600': ! $menuItem.isActive,
                                                        'opacity-50 cursor-not-allowed': $menuItem.isDisabled,
                                                    }"
                                                    class="flex items-center gap-x-2 rounded-md block w-full px-4 py-3 text-sm font-medium transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-4 w-4">
                                                        <path fill-rule="evenodd" d="M11.013 2.513a1.75 1.75 0 0 1 2.475 2.474L6.226 12.25a2.751 2.751 0 0 1-.892.596l-2.047.848a.75.75 0 0 1-.98-.98l.848-2.047a2.75 2.75 0 0 1 .596-.892l7.262-7.261Z" clip-rule="evenodd" />
                                                    </svg>
                                                    Edit Name and Type
                                                </a>
                                                <a x-menu:item href="{{ route('springs.history', $spring) }}"
                                                    :class="{
                                                        'bg-stone-200 text-gray-900': $menuItem.isActive,
                                                        'text-gray-600': ! $menuItem.isActive,
                                                        'opacity-50 cursor-not-allowed': $menuItem.isDisabled,
                                                    }"
                                                    class="flex items-center gap-x-2 rounded-md block w-full px-4 py-3 text-sm font-medium transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-4 w-4">
                                                        <path d="M3 2a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H3Z" />
                                                        <path fill-rule="evenodd" d="M3 6h10v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Zm3 2.75A.75.75 0 0 1 6.75 8h2.5a.75.75 0 0 1 0 1.5h-2.5A.75.75 0 0 1 6 8.75Z" clip-rule="evenodd" />
                                                    </svg>
                                                    View History
                                                    <div class="border-stone-300 bg-stone-100 border rounded-full text-xs px-1.5 py-0.5">
                                                        {{ $spring->springRevisions->count() ? $spring->springRevisions->count() : 'Empty' }}
                                                    </div>
                                                </a>


                                                @if (Gate::allows('admin'))
                                                    <div class="border-t border-stone-300 h-0 -mx-1 px-5 mt-1 mb-1 text-sm text-gray-400 font-bold">
                                                        {{--Admin Zone--}}
                                                    </div>
                                                        <button
                                                            type="button"
                                                            x-menu:item
                                                            wire:click="invalidateTiles"
                                                            :class="{
                                                                'bg-gray-200 text-gray-700': $menuItem.isActive,
                                                                'text-gray-600': ! $menuItem.isActive,
                                                                'opacity-50 cursor-not-allowed': $menuItem.isDisabled,
                                                            }"
                                                            class="flex items-center gap-x-2 rounded-md block w-full px-4 py-3 text-sm font-medium transition-colors">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="w-4 h-4" viewBox="0 0 16 16">
                                                                <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2z"/>
                                                                <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466"/>
                                                            </svg>
                                                            Regenerate Tiles
                                                        </button>
                                                    @if ($spring->visible())
                                                        <button
                                                            type="button"
                                                            x-menu:item
                                                            wire:click="hide"
                                                            wire:confirm="Hide this water source?"
                                                            :class="{
                                                                'bg-amber-200 text-amber-700': $menuItem.isActive,
                                                                'text-amber-600': ! $menuItem.isActive,
                                                                'opacity-50 cursor-not-allowed': $menuItem.isDisabled,
                                                            }"
                                                            class="flex items-center gap-x-2 rounded-md block w-full px-4 py-3 text-sm font-medium transition-colors">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4">
                                                                <path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l10.5 10.5a.75.75 0 1 0 1.06-1.06l-1.322-1.323a7.012 7.012 0 0 0 2.16-3.11.87.87 0 0 0 0-.567A7.003 7.003 0 0 0 4.82 3.76l-1.54-1.54Zm3.196 3.195 1.135 1.136A1.502 1.502 0 0 1 9.45 8.389l1.136 1.135a3 3 0 0 0-4.109-4.109Z" clip-rule="evenodd" />
                                                                <path d="m7.812 10.994 1.816 1.816A7.003 7.003 0 0 1 1.38 8.28a.87.87 0 0 1 0-.566 6.985 6.985 0 0 1 1.113-2.039l2.513 2.513a3 3 0 0 0 2.806 2.806Z" />
                                                            </svg>
                                                            Hide water source
                                                        </button>
                                                    @endif
                                                    @if ($spring->canBeAnnihilated())
                                                        <button
                                                            type="button"
                                                            @click.prevent="springDropdownOpen = false"
                                                            x-menu:item
                                                            wire:click.prevent="annihilate"
                                                            wire:confirm="Annihilate this water source? This auction is not reversible"
                                                            :class="{
                                                                'bg-red-200 text-red-700': $menuItem.isActive,
                                                                'text-red-600': ! $menuItem.isActive,
                                                                'opacity-50 cursor-not-allowed': $menuItem.isDisabled,
                                                            }"
                                                            class="flex items-center gap-x-2 rounded-md block w-full px-4 py-3 text-sm font-medium transition-colors">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4">
                                                                <path fill-rule="evenodd" d="M8.074.945A4.993 4.993 0 0 0 6 5v.032c.004.6.114 1.176.311 1.709.16.428-.204.91-.61.7a5.023 5.023 0 0 1-1.868-1.677c-.202-.304-.648-.363-.848-.058a6 6 0 1 0 8.017-1.901l-.004-.007a4.98 4.98 0 0 1-2.18-2.574c-.116-.31-.477-.472-.744-.28Zm.78 6.178a3.001 3.001 0 1 1-3.473 4.341c-.205-.365.215-.694.62-.59a4.008 4.008 0 0 0 1.873.03c.288-.065.413-.386.321-.666A3.997 3.997 0 0 1 8 8.999c0-.585.126-1.14.351-1.641a.42.42 0 0 1 .503-.235Z" clip-rule="evenodd" />
                                                            </svg>
                                                            Delete water source
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endcan
                        </div>
                    </div>
                    <div class="text-gray-600 mt-3 text-sm flex gap-y-2 flex-wrap items-start">
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
                        <div class="mr-3 flex-1">
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
                                class="flex items-center font-semibold mr-3 text-gray-500">
                                <div class="mr-1 text-gray-500">OpenStreetMap Data</div>
                                <div class="border-gray-300 bg-gray-100 border rounded-full text-xs px-1.5 py-0.5">{{ $spring->osm_tags->count() }} {{ Str::plural('tag', $spring->osm_tags->count()) }}</div>
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
                        <div x-show="osmTagsShown" class="flex flex-col gap-y-1 mt-1">
                            @foreach ($spring->osm_tags as $tag)
                                <div class="text-gray-900 text-sm break-all">{{ $tag->key }}={{ $tag->value }}</div>
                            @endforeach

                            <div class="mt-1"></div>
                            @if ($spring->osm_node_id)
                                <span class="text-gray-900 text-sm font-bold flex items-center mt-1 gap-x-1">
                                    <div class="text-gray-500 text-sm font-semibold">
                                        OSM Node Id: </div>
                                    <a class="cursor-pointer text-blue-600 hover:text-blue-700 hover:underline flex items-center gap-x-1" target="_blank" href="https://www.openstreetmap.org/node/{{ $spring->osm_node_id }}">{{ $spring->osm_node_id }}
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4 -mt-0.5">
                                            <path d="M6.22 8.72a.75.75 0 0 0 1.06 1.06l5.22-5.22v1.69a.75.75 0 0 0 1.5 0v-3.5a.75.75 0 0 0-.75-.75h-3.5a.75.75 0 0 0 0 1.5h1.69L6.22 8.72Z" />
                                            <path d="M3.5 6.75c0-.69.56-1.25 1.25-1.25H7A.75.75 0 0 0 7 4H4.75A2.75 2.75 0 0 0 2 6.75v4.5A2.75 2.75 0 0 0 4.75 14h4.5A2.75 2.75 0 0 0 12 11.25V9a.75.75 0 0 0-1.5 0v2.25c0 .69-.56 1.25-1.25 1.25h-4.5c-.69 0-1.25-.56-1.25-1.25v-4.5Z" />
                                        </svg>
                                    </a>
                                </span>
                            @endif
                            @if ($spring->osm_way_id)
                                <span class="text-gray-900 text-sm font-bold flex items-center mt-1 gap-x-1">
                                    <div class="text-gray-500 text-sm font-semibold">
                                        OSM Way Id: </div>
                                    <a class="cursor-pointer text-blue-600 hover:text-blue-700 hover:underline flex items-center gap-x-1" target="_blank" href="https://www.openstreetmap.org/way/{{ $spring->osm_way_id }}">{{ $spring->osm_way_id }}
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4">
                                            <path d="M6.22 8.72a.75.75 0 0 0 1.06 1.06l5.22-5.22v1.69a.75.75 0 0 0 1.5 0v-3.5a.75.75 0 0 0-.75-.75h-3.5a.75.75 0 0 0 0 1.5h1.69L6.22 8.72Z" />
                                            <path d="M3.5 6.75c0-.69.56-1.25 1.25-1.25H7A.75.75 0 0 0 7 4H4.75A2.75 2.75 0 0 0 2 6.75v4.5A2.75 2.75 0 0 0 4.75 14h4.5A2.75 2.75 0 0 0 12 11.25V9a.75.75 0 0 0-1.5 0v2.25c0 .69-.56 1.25-1.25 1.25h-4.5c-.69 0-1.25-.56-1.25-1.25v-4.5Z" />
                                        </svg>
                                    </a>
                                </span>
                            @endif
                            @if ($spring->osm_latitude && $spring->osm_longitude)
                                <span class="text-gray-500 text-sm font-bold flex items-center gap-x-1"
                                    x-data="{
                                        copied: false,
                                        timeout: null
                                    }"
                                    >
                                    <div class="text-gray-500 font-semibold">OSM Location </div>
                                    <span class="cursor-pointer text-gray-600" @click="
                                        $clipboard('{{ $spring->osm_latitude }}, {{ $spring->osm_longitude }}');
                                        copied = true;
                                        clearTimeout(timeout);
                                        timeout = setTimeout(() => {
                                            copied = false;
                                        }, 1000)
                                    ">
                                        {{ $spring->osm_latitude }}, {{ $spring->osm_longitude }}
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline -mt-0.5 w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5A3.375 3.375 0 006.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0015 2.25h-1.5a2.251 2.251 0 00-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 00-9-9z" />
                                        </svg>
                                        <span x-cloak x-show="copied" x-transition.opacity.duration.300 class="font-regular">
                                            copied
                                        </span>
                                    </span>
                                </span>
                            @endif
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
                    <ul role="list" class="photoSwipeGallery"
                        x-data
                        x-init="window.initPhotoSwipe('.photoSwipeGallery')">
                        @foreach ($reports as $report)
                            <livewire:reports.show :report="$report" :key="$report->id" />
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
</div>
