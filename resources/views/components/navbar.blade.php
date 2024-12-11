<div class="flex justify-between flex-nowrap
    {{ $map ? 'px-4' : '' }}
">
    <div class="flex flex-wrap">
        <!-- Logo -->
        <div class="shrink-0 flex items-center">
            @if ($map)
                <span x-data
                    @click.prevent="
                        window.dispatchEvent(
                            new CustomEvent('duo-visit',
                                {
                                    detail: {}
                                }
                            )
                        )"
                    class="pt-4 pb-2 mr-4 cursor-pointer">
                    <img src="/rodnik-nunito-logo.svg" class="h-6" />
                </span>
            @else
                <a href="/"
                    class="pt-4 pb-2 mr-4 cursor-pointer">
                    <img src="/rodnik-nunito-logo.svg" class="h-6" />
                </a>
            @endif
        </div>
    </div>

    <div class="flex items-center">
        @guest
            <div class="my-1 flex">
                <a href="{{ route('login') }}" class="pt-4 pb-2 block mr-4 text-sm text-gray-500">{{ __('Login') }}</a>
                <a href="{{ route('register') }}" class="pt-4 pb-2 block text-sm text-gray-500">{{ __('Register') }}</a>
            </div>
        @endguest
        @auth
            <!-- Settings Dropdown -->
            <div class="relative">
                <div x-data="{
                    navbarDropdownMenuOpen: false
                }">
                    <div x-menu x-model="navbarDropdownMenuOpen" class="relative">
                        <button x-menu:button
                            class="border-0 h-7 w-7 flex text-sm rounded-full outline-blue-700 outline-2 outline-offset-[3px] opacity-80 hover:opacity-100">
                                <img class="h-7 w-7 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                        </button>

                        <div x-menu:items x-cloak
                            style="display: none;"
                            class="absolute overflow-hidden right-0 w-56 p-1 mt-2 z-10 origin-top-right bg-white rounded-lg shadow-lg border border-stone-300
                            focus:outline-none
                            ">
                            <a x-menu:item href="{{ route('springs.create') }}"
                                @click.prevent="
                                    window.dispatchEvent(
                                        new CustomEvent('duo-visit',
                                            {
                                                detail: {
                                                    location: true
                                                }
                                            }
                                        )
                                    )

                                    navbarDropdownMenuOpen = false
                                    "
                                :class="{
                                    'bg-stone-200 text-gray-900': $menuItem.isActive,
                                    'text-gray-600': ! $menuItem.isActive,
                                    'opacity-50 cursor-not-allowed': $menuItem.isDisabled,
                                }"
                                class="rounded-md block w-full px-4 py-2 text-sm font-medium transition-colors">
                                New Water Source
                            </a>
                            <a x-menu:item href="{{ route('users.show', Auth::user()->id) }}"
                                @click.prevent="
                                    window.dispatchEvent(
                                        new CustomEvent('turbo-visit-user',
                                            {
                                                detail: {
                                                    userId: {{ intval(Auth::user()->id) }},
                                                }
                                            }
                                        )
                                    )
                                    navbarDropdownMenuOpen = false"
                                :class="{
                                    'bg-stone-200 text-gray-900': $menuItem.isActive,
                                    'text-gray-600': ! $menuItem.isActive,
                                    'opacity-50 cursor-not-allowed': $menuItem.isDisabled,
                                }"
                                class="rounded-md block w-full px-4 py-2 text-sm font-medium transition-colors">
                                    <span class="mr-1">My Water Sources</span>
                                    <span class="ml-0 text-xs font-medium px-1 py-0 rounded-full bg-[#FFD300]/[0.25] border border-[#ff6633]">{{ number_format(Auth::user()->rating, 0, ',', ' ') }}</span>
                            </a>
                            <div class="border-t border-stone-300 h-0 -mx-1 px-5 mt-1 mb-1 text-sm text-gray-400 font-bold">
                                {{--Account Management--}}
                            </div>
                            <a x-menu:item href="{{ route('profile.show') }}"
                                :class="{
                                    'bg-stone-200 text-gray-900': $menuItem.isActive,
                                    'text-gray-600': ! $menuItem.isActive,
                                    'opacity-50 cursor-not-allowed': $menuItem.isDisabled,
                                }"
                                class="rounded-md block w-full px-4 py-2 text-sm font-medium transition-colors">
                                Profile
                            </a>
                            <form method="POST" action="{{ route('logout') }}" x-data>
                                <a x-menu:item href="{{ route('logout') }}"
                                    @click.prevent="$root.submit()"
                                    :class="{
                                        'bg-stone-200 text-gray-900': $menuItem.isActive,
                                        'text-gray-600': ! $menuItem.isActive,
                                        'opacity-50 cursor-not-allowed': $menuItem.isDisabled,
                                    }"
                                    class="rounded-md block w-full px-4 py-2 text-sm font-medium transition-colors">
                                    @csrf
                                    Log Out
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endauth
    </div>
</div>
