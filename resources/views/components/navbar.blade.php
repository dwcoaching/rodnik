<div class="py-3 flex justify-between flex-wrap">
    <div class="">
        <!-- Logo -->
        <div class="shrink-0 flex items-center my-1 mr-4">
            @if ($map)
                <span x-data
                    @click.prevent="
                        window.dispatchEvent(new CustomEvent('spring-turbo-visit-index'))"
                    class="mr-4 cursor-pointer">
                    <img src="/rodnik-nunito-logo.svg" class="h-6" />
                </span>
            @else
                <a href="/"
                    class="mr-4 cursor-pointer">
                    <img src="/rodnik-nunito-logo.svg" class="h-6" />
                </a>
            @endif

            <div>
                <a href="/about" class="text-blue-600 text-sm">About</a>
            </div>
        </div>

        <!-- Navigation Links -->
        {{--
            <div class="space-x-8 -my-px ml-10 flex">
                <x-jet-nav-link href="{{ route('index') }}" :active="request()->routeIs('dashboard')">
                    {{ __('Dashboard') }}
                </x-jet-nav-link>
            </div>
        --}}
    </div>

    <div class="flex items-center">
        @guest
            <div class="my-1 block">
                <a href="{{ route('login') }}" class="mr-4 text-sm text-gray-500">{{ __('Login') }}</a>
                <a href="{{ route('register') }}" class="text-sm text-gray-500">{{ __('Register') }}</a>
            </div>
        @endguest
        @auth
            <!-- Settings Dropdown -->
            <div class="relative">
                <x-jet-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                            <button class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                <img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                            </button>
                        @else
                            <span class="inline-flex rounded-md">
                                <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition">
                                    {{ Auth::user()->name }}

                                    <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </span>
                        @endif
                    </x-slot>

                    <x-slot name="content">
                        <x-jet-dropdown-link href="{{ route('springs.create') }}">
                            {{ __('New water source') }}
                        </x-jet-dropdown-link>

                        <x-jet-dropdown-link href="{{ route('users.show', Auth::user()->id) }}"
                            @click.prevent="
                                window.dispatchEvent(
                                    new CustomEvent('spring-turbo-visit-user',
                                        {
                                            detail: {
                                                userId: {{ intval(Auth::user()->id) }},
                                            }
                                        }
                                    )
                                )">
                            {{ __('My water sources') }}
                        </x-jet-dropdown-link>

                        <!-- Account Management -->
                        <div class="block px-4 py-2 text-xs text-gray-400">
                            {{ __('Manage Account') }}
                        </div>

                        <x-jet-dropdown-link href="{{ route('profile.show') }}">
                            {{ __('Profile') }}
                        </x-jet-dropdown-link>

                        @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                            <x-jet-dropdown-link href="{{ route('api-tokens.index') }}">
                                {{ __('API Tokens') }}
                            </x-jet-dropdown-link>
                        @endif

                        <div class="border-t border-gray-100"></div>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}" x-data>
                            @csrf

                            <x-jet-dropdown-link href="{{ route('logout') }}"
                                     @click.prevent="$root.submit();">
                                {{ __('Log Out') }}
                            </x-jet-dropdown-link>
                        </form>
                    </x-slot>
                </x-jet-dropdown>
            </div>
        @endauth
    </div>
</div>
