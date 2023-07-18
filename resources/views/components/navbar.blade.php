<div class="py-3 flex justify-between flex-nowrap">
    <div class="flex flex-wrap">
        <!-- Logo -->
        <div class="shrink-0 flex items-center">
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
        </div>
        <div class="flex items-center my-2">
            <div class="mr-4">
                <a href="/about" class="text-blue-600 text-sm flex items-center font-normal text-sm text-blue-600 hover:text-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="mr-1 block" viewBox="0 0 16 16">
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                    </svg>
                    <div>
                        About
                    </div>
                </a>
            </div>
            <div class="">
                <a href="https://t.me/rodnik_today" target="_blank" class="flex items-center font-normal text-sm text-blue-600 hover:text-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="mr-1 block" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.287 5.906c-.778.324-2.334.994-4.666 2.01-.378.15-.577.298-.595.442-.03.243.275.339.69.47l.175.055c.408.133.958.288 1.243.294.26.006.549-.1.868-.32 2.179-1.471 3.304-2.214 3.374-2.23.05-.012.12-.026.166.016.047.041.042.12.037.141-.03.129-1.227 1.241-1.846 1.817-.193.18-.33.307-.358.336a8.154 8.154 0 0 1-.188.186c-.38.366-.664.64.015 1.088.327.216.589.393.85.571.284.194.568.387.936.629.093.06.183.125.27.187.331.236.63.448.997.414.214-.02.435-.22.547-.82.265-1.417.786-4.486.906-5.751a1.426 1.426 0 0 0-.013-.315.337.337 0 0 0-.114-.217.526.526 0 0 0-.31-.093c-.3.005-.763.166-2.984 1.09z"/>
                    </svg>
                    <div>
                        News & Chat
                    </div>
                </a>
            </div>
        </div>
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
