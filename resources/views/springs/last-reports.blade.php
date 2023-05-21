<div>
    <div class="-mt-3">
        <div wire:key="user" class="flex items-stretch" x-show="userId && userId !== myId">
            @if ($user && (! Auth::check() || $user->id !== Auth::user()->id))
                <div
                class="bg-slate-300 text-black rounded-lg mr-1 px-3 py-1 my-1 text-sm font-medium flex items-stretch">
                    <div class="mr-1">Showing Water Sources of <b>{{ $user->name }}</b>
                        <span class="bg-slate-500 rounded-full text-xs px-1.5 font-semibold text-white">{{ $user->rating }}</span>
                    </div>
                </div>
                <a href="/"
                    @click.prevent="
                        window.dispatchEvent(
                            new CustomEvent('turbo-visit-user',
                                {
                                    detail: {
                                        userId: 0,
                                    }
                                }
                            )
                        )
                    "
                class="bg-slate-500 text-white rounded-lg px-3 py-1 my-1 text-sm font-semibold flex text-center items-center">Show All</a>
            @endif
        </div>
        <div wire:key="global" x-show="! userId || userId == myId">
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
                        aria-hidden="true" class=" pointer-events-none absolute mx-auto h-4 w-9 rounded-full transition-colors duration-200 ease-in-out"></span>
                    <span
                         :class="{
                            'translate-x-5': personal,
                            'translate-x-0': ! personal,
                        }"
                        aria-hidden="true" class="translate-x-0 pointer-events-none absolute left-0 inline-block h-5 w-5 transform rounded-full border border-gray-200 bg-white shadow ring-0 transition-transform duration-200 ease-in-out"></span>
                </button>
                <div class="mt-0 text-sm font-medium">My Water Sources</div>
                @endauth
            </div>
        </div>

        <ul wire:loading.remove role="list" class="grid grid-cols-2 lg:grid-cols-3 mt-2 gap-4 items-stretch" wire:key="reports">
            @foreach ($lastReports as $report)
                <x-last-reports.teaser :report="$report" />
            @endforeach
        </ul>
    </div>
</div>
