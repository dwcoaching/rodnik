<div>
    <div class="-mt-3">
        <div wire:key="global" x-cloak x-show="! userId || userId == myId">
            <div class="flex text-sm items-center my-1">
                <div class="font-medium mr-2">All Water Sources</div>
                @auth
                <button @click="setPersonal(! personal)" type="button"
                    type="button" class="mr-2 group relative inline-flex h-5 w-10 flex-shrink-0 cursor-pointer items-center justify-center rounded-full focus:outline-none" role="switch" aria-checked="false">
                    <span aria-hidden="true" class="pointer-events-none absolute h-full w-full rounded-md"></span>
                    <span
                        :class="{
                            'bg-orange-400': personal,
                            'bg-blue-400': ! personal,
                        }"
                        aria-hidden="true" class="pointer-events-none absolute mx-auto h-5 w-9 rounded-full transition-colors duration-200 ease-in-out"></span>
                    <span
                         :class="{
                            'translate-x-5': personal,
                            'translate-x-1': ! personal,
                        }"
                        aria-hidden="true" class="translate-x-0 pointer-events-none absolute left-0 inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition-transform duration-200 ease-in-out"></span>
                </button>
                <div class="mt-0 text-sm font-medium">My Water Sources</div>
                @endauth
            </div>
        </div>

        <div x-show="! userId">
            <livewire:duo.reports.index :loaded="$loaded" />
        </div>
        <div x-show="userId">
            <livewire:duo.users.show :userId="$userId" />
        </div>
    </div>
</div>
