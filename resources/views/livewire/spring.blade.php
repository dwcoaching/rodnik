<div
    x-data="{}"
    x-on:spring-selected.window="$wire.setSpring($event.detail.id)"
    >
    <div class="text-xl font-bold">
        <img src="/logo.svg" class="h-16" />
    </div>
    @if ($spring)
        <div class="bg-white shadow-xl rounded-lg p-4 mt-4">
            <div class="text-4xl font-bold">
                {{ $spring->name }}
            </div>
            <div class="mt-3">
                {{ $spring->latitude }}, {{ $spring->longitude }}
            </div>
        </div>

        <div wire:key="update-for-spring-{{ $spring->id }}">
            <div
                x-data="{
                    showNewComment: @entangle('showNewComment')
                }">
                <div class="mt-8" x-show="! showNewComment">
                    <span @click="showNewComment = true; $nextTick(() => $refs.comment.focus())" class="text-xl font-semibold text-blue-600 border-b border-dotted border-blue-600 cursor-pointer">
                        Новый комментарий
                    </span>
                </div>
                <div x-show="showNewComment" class="bg-white shadow-xl rounded-lg p-4 mt-8">
                    <form wire:submit.prevent="storeUpdate">
                        <div>
                            <div class="flex items-center justify-between">
                                <label for="comment" class="block">Новый комментарий</label>
                                <div @click="showNewComment = false;" class="text-xl text-gray-400 mr-1 hover:text-red-600 cursor-pointer" >
                                    &times;
                                </div>
                            </div>
                            <div class="mt-1">
                                <textarea wire:model.defer="update.comment" x-ref="comment" rows="4" name="comment" id="comment" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full border-gray-300 rounded-md"></textarea>
                            </div>
                        </div>
                        <button class="mt-2 inline-flex items-center px-4 py-2 border border-transparent font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Добавить комментарий
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @foreach ($updates as $update)
            @include('updates.item')
        @endforeach
    @endif
</div>
