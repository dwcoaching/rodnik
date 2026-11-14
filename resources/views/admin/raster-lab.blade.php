{{-- Admin-only raster lab. Standalone on purpose, so it can be deleted with its JS folder and route. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Raster lab · Rodnik.today</title>
    @vite(['resources/css/app.css', 'resources/js/rasterLab/index.js'])
</head>
<body class="h-screen overflow-hidden bg-stone-100 text-sm text-gray-700">
<div class="flex h-full" x-data="rasterLab" x-init="trackZoom()">
    <div id="raster-lab-map" class="h-full min-w-0 grow"></div>

    <aside class="h-full w-80 shrink-0 overflow-y-auto border-l border-gray-200 bg-white px-4 py-3">
        <div class="flex items-baseline justify-between">
            <h1 class="text-base font-semibold text-gray-900">Raster lab</h1>
            <a href="/" class="text-xs text-blue-700 hover:underline">Main map</a>
        </div>
        <p class="mt-1 text-xs text-gray-500">Tune the source overview drawn at zooms 0–10. Only you see your edits until you generate tiles.</p>

        <div class="mt-3 flex flex-wrap gap-1">
            <button type="button" @click="go(23, 20, 2)" class="rounded-full border border-gray-300 px-2 py-0.5 text-xs hover:bg-gray-50">World</button>
            <button type="button" @click="go(15, 50, 5)" class="rounded-full border border-gray-300 px-2 py-0.5 text-xs hover:bg-gray-50">Europe</button>
            <button type="button" @click="go(2.1734, 41.3851, 10)" class="rounded-full border border-gray-300 px-2 py-0.5 text-xs hover:bg-gray-50">Barcelona</button>
            <button type="button" @click="go(37.505, 55.6325, 9)" class="rounded-full border border-gray-300 px-2 py-0.5 text-xs hover:bg-gray-50">Moscow</button>
            <button type="button" @click="go(34.1, 44.7, 8)" class="rounded-full border border-gray-300 px-2 py-0.5 text-xs hover:bg-gray-50">Crimea</button>
        </div>
        <p class="mt-2 text-xs text-gray-500">Map zoom <span class="tabular-nums text-gray-900" x-text="mapZoom.toFixed(2)"></span>,
            showing raster zoom <span class="tabular-nums text-gray-900" x-text="mapZoom > 10 ? 'none (markers only)' : hostZoom()"></span></p>

        <p x-show="status.revision && ! status.enabled" class="mt-2 rounded-md bg-orange-50 px-2 py-1.5 text-xs text-orange-800">
            Raster is switched off: visitors see the vector overview. Turn it on in the admin Control Panel.</p>

        <template x-if="style">
            <div>
                <div class="mt-3 flex items-center gap-2 border-t border-gray-100 pt-3">
                    <label for="lab-zoom" class="font-medium text-gray-900">Editing zoom</label>
                    <select id="lab-zoom" x-model.number="zoom" @change="follow = false" class="rounded-md border-gray-300 py-1 pl-2 pr-7 text-sm">
                        <template x-for="value in 11" :key="value">
                            <option :value="value - 1" x-text="value - 1" :selected="zoom === value - 1"></option>
                        </template>
                    </select>
                    <label class="ml-auto flex items-center gap-1 text-xs text-gray-500">
                        <input type="checkbox" x-model="follow" @change="follow && (zoom = hostZoom())" class="rounded-sm border-gray-300">
                        follow map
                    </label>
                </div>

                <div class="mt-3 grid grid-cols-[4.5rem_1fr_1fr] items-center gap-x-3 gap-y-2">
                    <div></div>
                    <div class="text-xs text-gray-500">Size, CSS px</div>
                    <div class="text-xs text-gray-500">Opacity</div>
                    <template x-for="name in colors" :key="name">
                        <div class="contents">
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block h-3 w-3 rounded-full"
                                    :style="{ background: { blue: '#238fe1', yellow: '#e7a400', green: '#009145', red: '#e0302b' }[name] }"></span>
                                <span x-text="name"></span>
                            </div>
                            <div>
                                <input type="number" min="0" :max="maxDiameter" step="0.1" :aria-label="name + ' size'"
                                    :value="style.zooms[zoom][name][0]" @change="set(name, 0, $event.target.value)"
                                    class="w-full rounded-md border-gray-300 px-2 py-1 text-sm tabular-nums">
                                <input type="range" min="0" :max="maxDiameter" step="0.1" :aria-label="name + ' size'"
                                    :value="style.zooms[zoom][name][0]" @input="set(name, 0, $event.target.value)"
                                    class="mt-1 block w-full cursor-pointer accent-blue-600">
                            </div>
                            <div>
                                <input type="number" min="0" max="1" step="0.005" :aria-label="name + ' opacity'"
                                    :value="style.zooms[zoom][name][1]" @change="set(name, 1, $event.target.value)"
                                    class="w-full rounded-md border-gray-300 px-2 py-1 text-sm tabular-nums">
                                <input type="range" min="0" max="1" step="0.005" :aria-label="name + ' opacity'"
                                    :value="style.zooms[zoom][name][1]" @input="set(name, 1, $event.target.value)"
                                    class="mt-1 block w-full cursor-pointer accent-blue-600">
                            </div>
                        </div>
                    </template>
                </div>

                <button type="button" @click="copyToNextZoom()" :disabled="zoom >= 10"
                    class="mt-3 rounded-md border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50 disabled:opacity-40">Copy these values to zoom <span x-text="Math.min(10, zoom + 1)"></span></button>

                <div class="mt-3 text-xs text-gray-500">Draw order, bottom to top. Click a colour to raise it.</div>
                <div class="mt-1 flex gap-1">
                    <template x-for="name in style.order" :key="name">
                        <button type="button" @click="raise(name)" x-text="name"
                            class="rounded-full border border-gray-300 px-2 py-0.5 text-xs hover:bg-gray-50"></button>
                    </template>
                </div>

                <div class="mt-3 border-t border-gray-100 pt-3">
                    <label for="lab-markers" class="font-medium text-gray-900">Real vector markers</label>
                    <select id="lab-markers" :value="markersFrom" @change="setMarkers($event.target.value)" class="mt-1 block w-full rounded-md border-gray-300 py-1 pl-2 pr-7 text-sm">
                        <option value="10">Above zoom 10, as on the main map</option>
                        <option value="8">From zoom 8, over the raster</option>
                        <option value="99">Hidden</option>
                    </select>
                </div>

                <div class="mt-3 flex items-center gap-2 border-t border-gray-100 pt-3">
                    <button type="button" @click="generate()" :disabled="working || ! dirty"
                        class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-40">Generate tiles</button>
                    <button type="button" @click="reset()" :disabled="! dirty"
                        class="rounded-md border border-gray-300 px-2 py-1.5 text-xs hover:bg-gray-50 disabled:opacity-40">Reset</button>
                    <button type="button" @click="openTransfer()"
                        class="ml-auto rounded-md border border-gray-300 px-2 py-1.5 text-xs hover:bg-gray-50">Copy / paste</button>
                </div>

                <div x-show="transfer" class="mt-2">
                    <textarea x-model="transferText" rows="5" aria-label="Style as JSON"
                        class="w-full rounded-md border-gray-300 p-2 font-mono text-[10px] leading-tight"></textarea>
                    <button type="button" @click="applyTransfer()"
                        class="mt-1 rounded-md border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50">Apply pasted style</button>
                </div>

                <p class="mt-2 text-xs" role="status"
                    :class="working ? 'text-orange-600' : 'text-gray-500'"
                    x-text="status.busy ? 'Drawing tiles now. This takes a few minutes.'
                        : status.pending ? 'Queued. Drawing starts within a minute.'
                        : dirty ? 'Previewing unsaved changes. Visitors still see the published style.'
                        : status.revision ? 'Showing the published style.' : 'The overview has not been built yet.'"></p>
            </div>
        </template>
        <p x-show="error" x-text="error" class="mt-2 text-xs text-red-600" role="alert"></p>
    </aside>
</div>
</body>
</html>
