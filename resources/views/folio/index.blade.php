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
                  <div class="flex-1 items-stretch">
                    <label for="my-drawer" class="btn btn-ghost flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </label>
                    <a href="/" class="btn btn-ghost flex items-center">
                      <img src="/rodnik-nunito-logo.svg" class="h-6 mt-0.5 " />
                    </a>

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
                  <li class="mb-2"><a href="/">
                      <img src="/rodnik-nunito-logo.svg" class="h-6 mt-0.5" />
                  </a></li>
                  {{--<li><a href="/">🌍&nbsp; Map</a></li>--}}
                  <li><a href="/docs/about"
                    @if (Request::is('docs/about'))
                      class="active"
                    @endif
                  >😀&nbsp; About</a></li>
                  <li><a href="/docs/exports"
                    @if (Request::is('docs/exports'))
                      class="active"
                    @endif
                  >🦜&nbsp; Exports</a></li>
                  {{--@auth
                    @can('admin')--}}
                      <li><a href="/docs/admin"
                        @if (Request::is('docs/admin'))
                          class="active"
                        @endif
                      >🦸&nbsp; Admin</a></li>
                  {{--  @endcan
                  @endauth--}}
                  <li><a href="/docs/contact-us"
                    @if (Request::is('docs/contact-us'))
                      class="active"
                    @endif
                  >💬&nbsp; Contact Us</a></li>
                </ul>
              </div>
            </div>
        </div>
    </body>
</html>
