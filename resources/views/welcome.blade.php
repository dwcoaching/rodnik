<x-guest-layout>
    <div class="flex flex-col-reverse sm:flex-row h-screen w-screen">
        <div class="w-full h-1/2 sm:w-1/2 sm:h-screen" id="map"
            x-data="{
                springs: @js($springs)
            }"
            x-init="initMap($el.id, springs)">
        </div>
        <div class="w-full h-1/2 sm:h-screen sm:w-1/2 p-6 overflow-y-scroll">
            <livewire:spring />
        </div>
    </div>
    <script>


    </script>
</x-guest-layout>
