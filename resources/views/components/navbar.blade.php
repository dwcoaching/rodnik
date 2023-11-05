<div class="flex justify-between flex-nowrap">
    <div class="flex flex-wrap">
        <!-- Logo -->
        <div class="shrink-0 flex items-center">
            @if ($map)
                <span x-data
                    @click.prevent="
                        window.dispatchEvent(new CustomEvent('spring-turbo-visit-index'))"
                    class="pt-4 pb-2 mr-4 cursor-pointer">
                    <img src="/rodnik-nunito-logo.svg" class="h-6" />
                </span>
            @else
                <a href="/" wire:navigate
                    class="pt-4 pb-2 mr-4 cursor-pointer">
                    <img src="/rodnik-nunito-logo.svg" class="h-6" />
                </a>
            @endif
        </div>
    </div>

    <div class="flex items-center">
        @guest
            <div class="my-1 flex">
                <a href="{{ route('login') }}" wire:navigate class="pt-4 pb-2 block mr-4 text-sm text-gray-500">{{ __('Login') }}</a>
                <a href="{{ route('register') }}" wire:navigate class="pt-4 pb-2 block text-sm text-gray-500">{{ __('Register') }}</a>
            </div>
        @endguest
        @auth
            <!-- Settings Dropdown -->
            <div class="relative">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                            <div>
                                <button class="h-7 w-7 flex text-sm rounded-full focus:outline-none transition">
                                    <img class="h-7 w-7 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                                </button>
                            </div>
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
                        <x-dropdown-link wire:navigate href="{{ route('springs.create') }}">
                            {{ __('New water source') }}
                        </x-dropdown-link>

                        <x-dropdown-link href="{{ route('users.show', Auth::user()->id) }}" wire:navigate
                            @click.prevent="
                                window.dispatchEvent(
                                    new CustomEvent('turbo-visit-user',
                                        {
                                            detail: {
                                                userId: {{ intval(Auth::user()->id) }},
                                            }
                                        }
                                    )
                                )">
                            <span class="mr-1">{{ __('My water sources') }}</span>
                            <span class="ml-0 text-xs font-medium px-1 py-0 rounded-full bg-[#FFD300]/[0.25] border border-[#ff6633]">{{ number_format(Auth::user()->rating, 0, ',', ' ') }}</span>
                        </x-dropdown-link>

                        <!-- Account Management -->
                        <div class="block px-4 py-2 text-xs text-gray-400">
                            {{ __('Manage Account') }}
                        </div>

                        <x-dropdown-link href="{{ route('profile.show') }}">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                            <x-dropdown-link href="{{ route('api-tokens.index') }}">
                                {{ __('API Tokens') }}
                            </x-dropdown-link>
                        @endif

                        <div class="border-t border-gray-100"></div>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}" x-data>
                            @csrf

                            <x-dropdown-link href="{{ route('logout') }}"
                                     @click.prevent="$root.submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        @endauth
    </div>
</div>
