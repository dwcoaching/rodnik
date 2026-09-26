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

        <script defer src="/js/@alpinejs/ui@3.14.1-beta.0.dist.cdn.min.js"></script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireScriptConfig
        <!-- Scripts -->
    </head>
    <body class="folio">
        <x-language-suggestion />
        <div>
            <div
                x-data="{ navigationOpen: false, desktop: window.matchMedia('(min-width: 64rem)').matches }"
                @resize.window="desktop = window.matchMedia('(min-width: 64rem)').matches; if (desktop) navigationOpen = false"
                @keydown.escape.window="navigationOpen = false"
                class="min-h-dvh bg-white text-[#111827] lg:grid lg:grid-cols-[20rem_minmax(0,1fr)]"
            >
              <div class="min-w-0 max-w-full overflow-x-hidden lg:col-start-2 lg:row-start-1">
                <header class="flex min-h-16 w-full flex-nowrap items-center gap-2 bg-[#f3f4f6] px-3 py-2 lg:hidden">
                    <button type="button" @click="navigationOpen = true" :aria-expanded="navigationOpen" aria-controls="docs-navigation" aria-label="{{ __('ui.common.navigation') }}" class="ui-button ui-button-icon ui-button-ghost shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <a data-rodnik-navigate href="{{ localized_public_path() }}" class="flex shrink-0 items-center">
                      <img src="/rodnik-nunito-logo.svg" alt="Rodnik.today" class="h-6 w-auto" />
                    </a>
                    <x-language-switcher class="ml-auto shrink-0" />
                </header>
                <div class="min-w-0 max-w-full p-8">
                    @yield('content')
                </div>
              </div>
              <div
                id="docs-navigation"
                x-cloak
                x-show="navigationOpen || desktop"
                x-transition:enter="transition-opacity duration-200 ease-out motion-reduce:transition-none"
                x-transition:enter-start="opacity-0 lg:opacity-100"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity duration-200 ease-out motion-reduce:transition-none"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 lg:opacity-100"
                x-trap.inert.noscroll="navigationOpen && !desktop"
                :role="desktop ? null : 'dialog'"
                :aria-modal="desktop ? null : 'true'"
                aria-label="{{ __('ui.common.navigation') }}"
                class="fixed inset-0 z-10 overflow-y-auto lg:sticky lg:top-0 lg:col-start-1 lg:row-start-1 lg:h-dvh lg:self-start"
              >
                <button type="button" @click="navigationOpen = false" tabindex="-1" aria-label="{{ __('ui.common.close') }}" class="fixed inset-0 cursor-pointer bg-[rgba(17,24,39,0.8)] lg:hidden"></button>
                <div
                  x-show="navigationOpen || desktop"
                  x-transition:enter="transition-transform duration-300 ease-out motion-reduce:transition-none"
                  x-transition:enter-start="-translate-x-full lg:translate-x-0"
                  x-transition:enter-end="translate-x-0"
                  x-transition:leave="transition-transform duration-300 ease-out motion-reduce:transition-none"
                  x-transition:leave-start="translate-x-0"
                  x-transition:leave-end="-translate-x-full lg:translate-x-0"
                  class="relative min-h-full w-80 bg-[#f3f4f6] p-4 text-[#111827]"
                >
                  <div class="mb-4 flex flex-nowrap items-center justify-between gap-3 px-2">
                    <a data-rodnik-navigate href="{{ localized_public_path() }}" class="flex shrink-0 items-center">
                      <img src="/rodnik-nunito-logo.svg" alt="Rodnik.today" class="h-6 w-auto" />
                    </a>
                    <x-language-switcher class="hidden shrink-0 lg:block" />
                    <button type="button" @click="navigationOpen = false" aria-label="{{ __('ui.common.close') }}" class="ui-button ui-button-ghost h-6 min-h-6 w-6 p-0 lg:hidden">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M6 18 18 6" /></svg>
                    </button>
                  </div>
                  <nav aria-label="{{ __('ui.common.navigation') }}" class="text-sm leading-normal [&_a]:grid [&_a]:grid-flow-col [&_a]:content-start [&_a]:items-center [&_a]:gap-2 [&_a]:rounded-lg [&_a]:px-3 [&_a]:py-1.5 [&_a]:text-start [&_a]:text-balance [&_a]:select-none [&_a]:transition-colors [&_a]:duration-200 [&_a:hover]:bg-[#111827]/10 [&_a:focus-visible]:bg-[#111827]/10 [&_a:focus-visible]:outline-hidden [&_a:active]:bg-[#3d4451] [&_a:active]:text-white">
                  <ul class="flex w-full flex-col">
                  {{--<li><a href="/">🌍&nbsp; Map</a></li>--}}
                  <li><a data-rodnik-navigate href="{{ localized_public_path('/docs/about') }}"
                    @if (Request::is('docs/about', 'ru/docs/about'))
                      aria-current="page"
                    @endif
                  >😀&nbsp; {{ __('ui.common.about') }}</a></li>
                  <li><a data-rodnik-navigate href="{{ route(app()->isLocale('ru') ? 'ru.docs.users' : 'docs.users') }}"
                    @if (Request::is('docs/users', 'ru/docs/users'))
                      aria-current="page"
                    @endif
                  >💧&nbsp; {{ __('pages.users.title') }}</a></li>
                  <li><a data-rodnik-navigate href="{{ localized_public_path('/docs/legend') }}"
                    @if (Request::is('docs/legend', 'ru/docs/legend'))
                      aria-current="page"
                    @endif
                  >🗺️&nbsp; {{ __('ui.home.map_legend.title') }}</a></li>
                  <li><a data-rodnik-navigate href="{{ localized_public_path('/docs/exports') }}"
                    @if (Request::is('docs/exports', 'ru/docs/exports'))
                      aria-current="page"
                    @endif
                  >🦜&nbsp; {{ __('pages.exports.title') }}</a></li>
                  @auth
                      @if (app()->isLocale('en'))
                          <li><a data-rodnik-navigate href="{{ route('docs.api') }}"
                            @if (Request::is('docs/api'))
                              aria-current="page"
                            @endif
                          >🔌&nbsp; API</a></li>
                      @endif
                  @endauth
                  {{--@auth
                    @can('admin')--}}
                  @if (app()->isLocale('en'))
                      <li><a data-rodnik-navigate href="/docs/admin"
                        @if (Request::is('docs/admin'))
                          aria-current="page"
                        @endif
                      >🦸&nbsp; Admin</a></li>
                      <li><a data-rodnik-navigate href="/docs/admin/duplicates"
                        @if (Request::is('docs/admin/duplicates'))
                          aria-current="page"
                        @endif
                      >🔎&nbsp; Possible Duplicates</a></li>
                      <li><a data-rodnik-navigate href="/docs/admin/spring-scores"
                        @if (Request::is('docs/admin/spring-scores'))
                          aria-current="page"
                        @endif
                      >🚦&nbsp; Spring Scores</a></li>
                  @endif
                  {{--  @endcan
                  @endauth--}}
                  <li><a data-rodnik-navigate href="{{ localized_public_path('/docs/contact-us') }}"
                    @if (Request::is('docs/contact-us', 'ru/docs/contact-us'))
                      aria-current="page"
                    @endif
                  >💬&nbsp; {{ __('pages.contact.title') }}</a></li>
                  </ul>
                  <ul class="mt-4 flex w-full flex-col" aria-labelledby="docs-legal">
                  <li class="px-3 py-2 text-sm leading-normal font-semibold text-[#111827]/40" id="docs-legal">⚖️&nbsp; {{ __('privacy.legal') }}</li>
                  <li><a data-rodnik-navigate href="{{ route(app()->isLocale('ru') ? 'ru.docs.privacy' : 'docs.privacy') }}"
                    @if (Request::is('docs/privacy', 'ru/docs/privacy'))
                      aria-current="page"
                    @endif
                  >🔒&nbsp; {{ __('privacy.title') }}</a></li>
                  <li><a data-rodnik-navigate href="{{ route(app()->isLocale('ru') ? 'ru.docs.delete-account' : 'docs.delete-account') }}"
                    @if (Request::is('docs/delete-account', 'ru/docs/delete-account'))
                      aria-current="page"
                    @endif
                  >🗑️&nbsp; {{ __('privacy.deletion.title') }}</a></li>
                  </ul>
                  </nav>
                </div>
              </div>
            </div>
        </div>
        <x-js-translations />
    </body>
</html>
