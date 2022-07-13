<x-app-layout>
    <div class="flex flex-col-reverse sm:flex-row w-screen h-full ">
        <div class="w-full h-1/2 sm:w-1/2 sm:h-full" id="map"
            x-data="{
                springs: @js($springs)
            }"
            x-init="initMap($el.id, springs)">
        </div>
        <div class="w-full h-1/2 sm:h-full sm:w-1/2 p-6 overflow-y-scroll">
            <livewire:spring />
        </div>
    </div>
</x-app-layout>
