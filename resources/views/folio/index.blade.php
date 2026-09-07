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
                <header class="navbar flex-nowrap gap-2 bg-base-200 px-3 lg:hidden">
                    <label for="my-drawer" class="btn btn-square btn-ghost shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </label>
                    <a href="{{ localized_public_path() }}" class="flex shrink-0 items-center">
                      <img src="/rodnik-nunito-logo.svg" alt="Rodnik.today" class="h-6 w-auto" />
                    </a>
                    <x-language-switcher class="ml-auto shrink-0" />
                </header>
                <div class="p-8 min-w-0 max-w-full">
                    @yield('content')
                </div>
              </div>
              <div class="drawer-side">
                <label for="my-drawer" aria-label="{{ __('ui.common.close') }}" class="drawer-overlay"></label>
                <div data-theme="light" class="w-80 min-h-full bg-base-200 p-4 text-base-content">
                  <div class="mb-4 flex flex-nowrap items-center justify-between gap-3 px-2">
                    <a href="{{ localized_public_path() }}" class="flex shrink-0 items-center">
                      <img src="/rodnik-nunito-logo.svg" alt="Rodnik.today" class="h-6 w-auto" />
                    </a>
                    <x-language-switcher class="hidden shrink-0 lg:block" />
                  </div>
                  <ul class="menu w-full p-0">
                  {{--<li><a href="/">🌍&nbsp; Map</a></li>--}}
                  <li><a href="{{ localized_public_path('/docs/about') }}"
                    @if (Request::is('docs/about', 'ru/docs/about'))
                      class="active"
                    @endif
                  >😀&nbsp; {{ __('ui.common.about') }}</a></li>
                  <li><a href="{{ route(app()->isLocale('ru') ? 'ru.docs.users' : 'docs.users') }}"
                    @if (Request::is('docs/users', 'ru/docs/users'))
                      class="active"
                    @endif
                  >💧&nbsp; {{ __('pages.users.title') }}</a></li>
                  <li><a href="{{ localized_public_path('/docs/legend') }}"
                    @if (Request::is('docs/legend', 'ru/docs/legend'))
                      class="active"
                    @endif
                  >🗺️&nbsp; {{ __('ui.home.map_legend.title') }}</a></li>
                  <li><a href="{{ localized_public_path('/docs/exports') }}"
                    @if (Request::is('docs/exports', 'ru/docs/exports'))
                      class="active"
                    @endif
                  >🦜&nbsp; {{ __('pages.exports.title') }}</a></li>
                  @auth
                      @if (app()->isLocale('en'))
                          <li><a href="{{ route('docs.api') }}"
                            @if (Request::is('docs/api'))
                              class="active"
                            @endif
                          >🔌&nbsp; API</a></li>
                      @endif
                  @endauth
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
                  <ul class="menu mt-4 w-full p-0" aria-labelledby="docs-legal">
                  <li class="menu-title" id="docs-legal">⚖️&nbsp; {{ __('privacy.legal') }}</li>
                  <li><a href="{{ route(app()->isLocale('ru') ? 'ru.docs.privacy' : 'docs.privacy') }}"
                    @if (Request::is('docs/privacy', 'ru/docs/privacy'))
                      class="active"
                    @endif
                  >🔒&nbsp; {{ __('privacy.title') }}</a></li>
                  <li><a href="{{ route(app()->isLocale('ru') ? 'ru.docs.delete-account' : 'docs.delete-account') }}"
                    @if (Request::is('docs/delete-account', 'ru/docs/delete-account'))
                      class="active"
                    @endif
                  >🗑️&nbsp; {{ __('privacy.deletion.title') }}</a></li>
                  </ul>
                </div>
              </div>
            </div>
        </div>
    </body>
</html>
