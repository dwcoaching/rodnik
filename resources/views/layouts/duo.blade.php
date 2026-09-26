<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, shrink-to-fit=no">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="track-polygons-url" content="{{ route('track-polygons.store') }}">
        <meta name="tracks-url" content="{{ route('tracks.store') }}">


        <x-seo :indexable="$sharedMap || $missingMap ? false : null" />
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="manifest" href="/site.webmanifest">
        <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
        <meta name="msapplication-TileColor" content="#ffffff">
        <meta name="theme-color" content="#ffffff">

        <!-- Fonts -->

        <!-- Styles -->
        @livewireStyles

        <!-- Scripts -->

        <script defer src="/js/@alpinejs/ui@3.14.1-beta.0.dist.cdn.min.js"></script>
        <script defer src="/js/@alpinejs/focus@3.14.1.dist.cdn.min.js"></script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireScriptConfig

        <!-- Yandex.Metrika counter -->
            <script type="text/javascript" >
               (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
               var z = null;m[i].l=1*new Date();
               for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
               k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
               (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

               ym(90143259, "init", {
                    clickmap:true,
                    trackLinks:true,
                    accurateTrackBounce:true
               });

            </script>
            <noscript><div><img src="https://mc.yandex.ru/watch/90143259" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
        <!-- /Yandex.Metrika counter -->
    </head>
    <body class="w-full min-h-screen bg-stone-100 flex flex-col"
        x-data="{ dragover: false, dragoverTimeout: null }"
        @dragover.window="dragover = true; if (dragoverTimeout) {clearTimeout(dragoverTimeout)}"
        @dragleave.window="dragoverTimeout = setTimeout(() => { dragover = false; }, 10)"
        @drop="dragover = false; if (dragoverTimeout) {clearTimeout(dragoverTimeout)}"
        x-bind:class="{ 'dragover': dragover }"
        >
        <x-language-suggestion />
        <div class="grow h-full flex flex-col">
            <div
                x-data="mapLayout"
                id="map-layout"
                class="h-full flex flex-col grow"
            >
                <div class="top-0 w-full h-full sm:pl-[50%] sm:pb-0 flex flex-col grow"
                    :class="{
                        hidden: fullscreen,
                        block: ! fullscreen,
                    }"
                >
                    <div class="grow h-full w-full flex flex-col items-stretch">
                        <x-navbar map />
                        <div class="flex grow">
                            {{ $slot }}
                        </div>
                        <div class="grow-0 sm:hidden"
                            :class="{
                                'pb-[50vh]': ! minimized,
                                'pb-10': minimized,
                            }"
                        >
                        </div>
                    </div>
                </div>
                <x-rodnik-map />
                <div x-cloak x-show="! minimized" class="pointer-events-none fixed h-9 w-9 right-2 bottom-[calc(50vh-2.75rem)] sm:bottom-auto sm:top-2 sm:right-[calc(50%+0.5rem)] z-[10000]"
                    :class="{ 'sm:!right-2': fullscreen }"
                    :style="fullscreen ? 'top: 0.5rem; bottom: auto' : ''">
                    <x-map-controls />
                </div>
            </div>
        </div>
        <script id="rodnik-shared-map" type="application/json">{!! json_encode($sharedMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        <x-js-translations />
    </body>
</html>
