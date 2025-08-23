<div class="h-full" x-data="{
        showLegendModal: false
    }">
    <div wire:loading.delay.long.flex class="grow hidden w-full h-full flex justify-center items-center">
        <div class="animate-spin w-6 h-6 border border-4 rounded-full border-stone-400 border-t-transparent"></div>
    </div>
    <div wire:loading.remove>
        @if ($userId)
            <div class="px-4 flex items-stretch">
                <div
                class="rounded-lg flex items-center">
                    <div class="mr-2 text-xl font-medium">{{ $user?->name }}</div>
                    <span class="ml-0 text-sm font-medium px-1.5 py-0 rounded-full bg-[#FFD300]/[0.25] border border-[#ff6633]">{{ $user?->rating }}</span>
                </div>
            </div>
        @else
            <div class="px-4">
                <span class="font-normal text-base text-blue-600 hover:text-blue-700">
                    <span class="text-gray-900 mr-1">Community-driven map of public water sources with real-world user reports</span>
                
                <a href="/docs/about" class="text-blue-600 font-normal text-base text-blue-600 hover:text-blue-700 whitespace-nowrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="relative -mt-0.5 inline" viewBox="0 0 16 16">
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                    </svg>
                    <span class="hover:underline">
                        Learn more
                    </span>
                </a>
                {{-- 
                    <div class="">
                        <a href="https://www.instagram.com/rodnik.today/" target="_blank" class="flex items-center font-normal text-sm text-blue-600 hover:text-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="mr-1 block" viewBox="0 0 16 16">
                                <path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.917 3.917 0 0 0-1.417.923A3.927 3.927 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.916 3.916 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.926 3.926 0 0 0-.923-1.417A3.911 3.911 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0h.003zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599.28.28.453.546.598.92.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.47 2.47 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.478 2.478 0 0 1-.92-.598 2.48 2.48 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233 0-2.136.008-2.388.046-3.231.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92.28-.28.546-.453.92-.598.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045v.002zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92zm-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217zm0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334z"/>
                            </svg>
                            <div>
                                Instagram
                            </div>
                        </a>
                    </div>
                --}}
            </div>
            <div class="mt-2 px-4 mb-3 text-sm font-medium">
                <span class="px-1.5 py-0 rounded-full bg-[#33A9FF]/[0.1] border border-[#33A9FF]">{{ number_format($springsCount, 0, ',', ' ') }}</span>
                {{ \Str::plural('water source', $springsCount) }} with
                <span class="ml-0 px-1.5 py-0 rounded-full bg-[#FFD300]/[0.25] border border-[#ff6633]">{{ number_format($reportsCount, 0, ',', ' ') }}</span>
                {{ \Str::plural('report', $reportsCount) }}. 
                <button @click="showLegendModal = true" class="text-blue-600 hover:text-blue-700 hover:underline cursor-pointer">
                    Show map legend
                </button>.
            </div>
        @endif
        <ul x-cloak role="list" class="grid grid-cols-2 lg:grid-cols-3 mt-2 md:px-4
            bg-stone-200
            border-t
            border-b
            border-stone-200
            gap-[1px]
            md:bg-inherit
            md:border-0
            md:gap-4 items-stretch md:items-start" wire:key="reports">
            @foreach ($lastReports as $report)
                <x-last-reports.teaser :report="$report" />
            @endforeach
        </ul>
        @if (count($lastReports) == $limit)
            <livewire:duo.components.show-more-reports
                user-id="{{ $userId }}"
                skip="{{ $limit }}"
                take="{{ $limit }}"
                key="show-more-reports-user-{{ $userId }}-skip-{{ $limit }}-take-{{ $limit }}"
                />
        @endif
    </div>

    <!-- Map Legend Modal -->
    <div x-show="showLegendModal" 
        role="dialog"
        aria-modal="true"
         x-cloak
         @click="showLegendModal = false"
         @keydown.escape.window="showLegendModal = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto"
             role="dialog"
             x-trap.noscroll.inert="showLegendModal"
             @click.stop
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Map Legend</h3>
                <button @click="showLegendModal = false" 
                        class="text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6">
                <div class="space-y-4">
                    <!-- Default/Unreported Sources -->
                    <div class="flex items-center space-x-3">
                        <div class="w-6 h-6 rounded-full border border-[#33A9FF] bg-[#33A9FF]/10 flex-shrink-0"></div>
                        <div class="flex-1">
                            <div class="font-medium text-gray-900">No user reports</div>
                            <div class="text-sm text-gray-600">Nobody has visited the source yet</div>
                        </div>
                    </div>

                    <!-- Good Water Quality -->
                    <div class="flex items-center space-x-3">
                        <div class="w-6 h-6 rounded-full border border-[#006600] bg-[#009900]/50 flex-shrink-0"></div>
                        <div class="flex-1">
                            <div class="font-medium text-gray-900">Good water</div>
                            <div class="text-sm text-gray-600">People mostly report good water</div>
                        </div>
                    </div>
                    
                    <!-- Bad Water Quality -->
                    <div class="flex items-center space-x-3">
                        <div class="w-6 h-6 rounded-full border border-[#FF0000] bg-[#FF0000]/50 flex-shrink-0"></div>
                        <div class="flex-1">
                            <div class="font-medium text-gray-900">Poor water</div>
                            <div class="text-sm text-gray-600">People mostly report poor water</div>
                        </div>
                    </div>

                    <!-- Sources with Reports -->
                    <div class="flex items-center space-x-3">
                        <div class="w-6 h-6 rounded-full border border-[#ff9900] bg-[#FFB400]/80 flex-shrink-0"></div>
                        <div class="flex-1">
                            <div class="font-medium text-gray-900">Unsure</div>
                            <div class="text-sm text-gray-600">People are unsure or had different observations</div>
                        </div>
                    </div>
                    
                    <!-- Not Found Sources -->
                    <div class="flex items-center space-x-3">
                        <div class="w-6 h-6 rounded-full border border-red-500 flex-shrink-0 flex items-center justify-center">
                            <span class="text-red-500 font-bold text-sm">✕</span>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium text-gray-900">Not Found</div>
                            <div class="text-sm text-gray-600">Some people haven't found the source</div>
                        </div>
                    </div>
                    
                    <!-- Additional Info -->
                    <div class="mt-6 p-4 bg-gray-100 rounded-lg">
                        <div class="text-sm text-gray-800">
                            <strong>Numbers on markers</strong> indicate the count of reports for that water source.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
