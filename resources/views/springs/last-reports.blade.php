<div>
    <div class="-mt-3">
        <div wire:key="user">
            @if ($user && (! Auth::check() || $user->id !== Auth::user()->id))
                <div x-show="userId !== myId" class="my-1 text-sm font-medium flex items-center">
                    <div class="mr-1">Water Sources of <b>{{ $user->name }}</b></div>
                    <div class="bg-green-600 rounded-full  text-xs px-1.5 font-semibold text-white mr-2">{{ $user->rating }}</div>
                    <a href="/" @click.prevent="setUserId(0)" class="text-blue-600 text-sm font-bold">Show All</a>
                </div>
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

        <ul role="list" class="grid grid-cols-2 lg:grid-cols-3 mt-2 gap-4 items-stretch" wire:key="reports">
            @foreach ($lastReports as $report)
                <x-last-reports.teaser :report="$report" />
            @endforeach
        </ul>
    </div>
</div>
