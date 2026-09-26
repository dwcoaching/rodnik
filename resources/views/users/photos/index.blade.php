<x-app-layout navbar>
    <div>
        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-black">{{ $user->name }}</h1>
            <h2 class="mt-2 text-2xl">{{ __('ui.photos.title') }} <span class="inline-flex h-6 w-fit items-center justify-center gap-2 rounded-[1.9rem] border border-[#3d4451] bg-[#3d4451] px-[11px] align-middle text-sm leading-normal text-white">{{ $photos->count() }}</span></h2>
            <div class="mt-4 flex flex-col gap-y-4 max-w-3xl">
                @foreach ($photos as $photo)
                    <div>
                        <img src="{{ $photo->url }}" alt="{{ $photo->report->spring->name }}">
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
