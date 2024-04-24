<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        @vite(['resources/css/app.css'])

        <script src="//unpkg.com/alpinejs" defer></script>
        <!-- Scripts -->
    </head>
    <body class="folio">
        <div>
            <div class="drawer lg:drawer-open">
              <input id="my-drawer" type="checkbox" class="drawer-toggle" />
              <div class="drawer-content">
                <div class="lg:hidden navbar bg-base-200">
                  <div class="flex-1">
                    <label for="my-drawer" class="btn btn-square btn-ghost">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </label>
                    <img src="/rodnik-nunito-logo.svg" class="h-6 mt-0.5" />

                  </div>
                </div>
                <div class="p-8">
                    @yield('content')
                </div>
              </div>
              <div class="drawer-side">
                <label for="my-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
                <ul data-theme="light" class="menu p-4 w-80 min-h-full bg-base-200 text-base-content">
                  <!-- Sidebar content here -->
                  <li><a class="/">
                      <img src="/rodnik-nunito-logo.svg" class="h-6 mt-0.5" />
                  </a></li>
                  <li><a href="/">🌍&nbsp; Map</a></li>
                  <li><a href="/docs/about">😀&nbsp; About</a></li>
                </ul>
              </div>
            </div>
        </div>
    </body>
</html>
