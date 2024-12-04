<form class="w-full max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 mt-6"
    x-on:submit.prevent="
        if (saving) {
            return
        }

        saving = true
        $wire.$call('store')
    "
    x-data="{
            saving: $wire.$entangle('saving'),
            type: $wire.$entangle('type'),
            error: function() {
                if (! this.type) {
                    return true;
                }

                return false;
            },
        }"
    >
    <a href="{{ $springId ? route('springs.show', $springId) : '/' }}" class="text-3xl font-bold text-blue-600 hover:text-blue-700">
        <span class="mr-2 inline-flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 mb-6" width="36" height="36" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/>
            </svg>
        </span>
    </a>

    <div class="flex items-center justify-between">
        <div class="flex-1 min-w-0">
            <span class="block text-3xl font-extrabold">
                <span class="mr-2 inline-flex items-center">
                    @if ($spring->type)
                        <div>
                            Edit Name and Type
                        </div>
                    @else
                        Add Name and Type
                    @endif
                </span>
            </span>
        </div>
    </div>

    <div class="mt-4">
        <div class="w-full">
            <label for="coordinates" class="text-sm font-semibold leading-6 text-gray-500 leading-6">
                <span>Type</span>
            </label>
            <div class="mt-1">
                <select x-model="type" name="type"
                    @class([
                        'font-medium',
                        'block',
                        'w-full',
                        'rounded-lg',
                        'border-0',
                        'py-2.5',
                        'text-gray-900',
                        'placeholder:text-gray-400',
                        'sm:leading-6',
                        'ring-inset',
                        'focus:ring-2',
                        'focus:ring-inset',
                        'ring-1' => ! $errors->has('type'),
                        'ring-gray-300' => ! $errors->has('type'),
                        'focus:ring-blue-600' => ! $errors->has('type'),
                        'ring-red-600' => $errors->has('type'),
                        'ring-2' => $errors->has('type'),
                        'focus:ring-red-600' => $errors->has('type'),
                    ])">
                    <option value="" x-bind:disabled="true" hidden></option>
                    @foreach ($waterSourceTypes as $waterSourceType)
                        <option value="{{ $waterSourceType }}">
                            @if ($waterSourceType == 'Water source')
                                Other
                            @elseif ($waterSourceType == 'Fountain')
                                Decorative fountain
                            @else
                                {{ $waterSourceType }}
                            @endif
                        </option>
                    @endforeach
                </select>
                @error('type')
                    <div class="text-red-600 text-sm mt-2">
                        {{ $message }}
                    </div>
                @enderror
            </div>
        </div>
        <div class="w-full">
            <div class="mt-4 w-full">
                <label for="coordinates" class="text-sm font-semibold leading-6 text-gray-500 leading-6">
                    <span>Name <span class="text-gray-500">(optional)</span></span>
                </label>
                <div class="mt-1">
                    <input
                        wire:model="name" type="text" name="name" id="name"
                        class="font-medium block w-full rounded-lg border-0 py-2.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:leading-6"
                        >
                </div>
            </div>
        </div>



    </div>

    <div class="mt-4 pb-6">
        <div class="flex justify-start">
            <button type="submit"
                class="btn font-bold btn-primary btn-block"
                x-bind:disabled="saving"
                :class="{
                    btn-disabled: saving
                }"
            >
                {{ $spring->type ? 'Save changes' : 'Add Name and Type' }}
            </button>
        </div>
    </div>
</form>
