<x-app-layout navbar>
    <div>
        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-black">{{ $user->name }}</h1>
            <h2 class="mt-2 text-2xl">Photos <span class="badge badge-neutral">{{ $photos->count() }}</span></h2>
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
