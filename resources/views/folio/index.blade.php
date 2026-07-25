<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-seo
            :title="trim($__env->yieldContent('title')) ?: null"
            :description="trim($__env->yieldContent('description')) ?: null"
        />

        <!-- Fonts -->
        @livewireStyles

        <x-js-translations />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireScriptConfig
        <!-- Scripts -->
    </head>
    <body class="folio">
        <x-language-suggestion />
        <div>
            <div class="drawer lg:drawer-open">
              <input id="my-drawer" type="checkbox" class="drawer-toggle" />
              <div class="drawer-content min-w-0 max-w-full overflow-x-hidden">
                <div class="lg:hidden navbar bg-base-200">
                  <div class="flex-1 items-stretch">
                    <label for="my-drawer" class="btn btn-ghost flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </label>
                    <a href="{{ localized_public_path() }}" class="btn btn-ghost flex items-center">
                      <img src="/rodnik-nunito-logo.svg" class="h-6 mt-0.5 " />
                    </a>

                  </div>
                </div>
                <div class="p-8 min-w-0 max-w-full">
                    @yield('content')
                </div>
              </div>
              <div class="drawer-side">
                <label for="my-drawer" aria-label="{{ __('ui.common.close') }}" class="drawer-overlay"></label>
                <ul data-theme="light" class="menu p-4 w-80 min-h-full bg-base-200 text-base-content">
                  <!-- Sidebar content here -->
                  <li class="mb-2"><a href="{{ localized_public_path() }}">
                      <img src="/rodnik-nunito-logo.svg" class="h-6 mt-0.5" />
                  </a></li>
                  <li class="mb-2"><x-language-switcher /></li>
                  {{--<li><a href="/">🌍&nbsp; Map</a></li>--}}
                  <li><a href="{{ localized_public_path('/docs/about') }}"
                    @if (Request::is('docs/about', 'ru/docs/about'))
                      class="active"
                    @endif
                  >😀&nbsp; {{ __('ui.common.about') }}</a></li>
                  <li><a href="{{ localized_public_path('/docs/exports') }}"
                    @if (Request::is('docs/exports', 'ru/docs/exports'))
                      class="active"
                    @endif
                  >🦜&nbsp; {{ __('pages.exports.title') }}</a></li>
                  {{--@auth
                    @can('admin')--}}
                  @if (app()->isLocale('en'))
                      <li><a href="/docs/admin"
                        @if (Request::is('docs/admin'))
                          class="active"
                        @endif
                      >🦸&nbsp; Admin</a></li>
                      <li><a href="/docs/admin/duplicates"
                        @if (Request::is('docs/admin/duplicates'))
                          class="active"
                        @endif
                      >🔎&nbsp; Possible Duplicates</a></li>
                      <li><a href="/docs/admin/spring-scores"
                        @if (Request::is('docs/admin/spring-scores'))
                          class="active"
                        @endif
                      >🚦&nbsp; Spring Scores</a></li>
                  @endif
                  {{--  @endcan
                  @endauth--}}
                  <li><a href="{{ localized_public_path('/docs/contact-us') }}"
                    @if (Request::is('docs/contact-us', 'ru/docs/contact-us'))
                      class="active"
                    @endif
                  >💬&nbsp; {{ __('pages.contact.title') }}</a></li>
                </ul>
              </div>
            </div>
        </div>
    </body>
</html>
