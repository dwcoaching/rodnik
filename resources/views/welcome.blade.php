<x-app-layout>
    <div class="flex flex-col-reverse sm:flex-row w-screen h-full">
        <div class="flex-none sm:w-1/2 h-1/2 sm:h-full" id="map"
            x-data="{}"
            x-init="initOpenLayers($el.id)">
        </div>
        <div class="w-full sm:w-1/2 sm:h-full h-1/2 overflow-y-scroll px-6">
             @include('navbar')
            <div class="">
                <livewire:spring spring_id="{{ $springId }}" />
            </div>
        </div>
    </div>
</x-app-layout>
