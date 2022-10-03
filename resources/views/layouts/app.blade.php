<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, shrink-to-fit=no">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="manifest" href="/site.webmanifest">
        <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
        <meta name="msapplication-TileColor" content="#ffffff">
        <meta name="theme-color" content="#ffffff">

        <!-- Fonts -->

        <!-- Styles -->
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
        @livewireStyles

        <!-- Scripts -->

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireScripts

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
    <body class="w-full h-screen bg-gray-100 flex flex-col">
        @if ($navbar)
            <div class="grow-0">
                <nav class="">
                    <div class="max-w-7xl mx-auto px-6 lg:px-8">
                        @include('navbar')
                    </div>
                </nav>
            </div>
        @endif
        <div class="grow h-full">
            {{ $slot }}
        </div>
    </body>
</html>
