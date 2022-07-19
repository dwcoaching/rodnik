<x-app-layout navbar>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div x-data=""
          class="py-5 border-b border-gray-200">

          <div class="flex items-center hidden">
              <!-- Enabled: "bg-indigo-600", Not Enabled: "bg-gray-200" -->
              <button type="button" class="bg-indigo-600 relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" role="switch" aria-checked="false" aria-labelledby="annual-billing-label">
                <!-- Enabled: "translate-x-5", Not Enabled: "translate-x-0" -->
                <span aria-hidden="true" class="translate-x-5 pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200"></span>
              </button>
              <span class="ml-3" id="annual-billing-label">
                <span class="text-sm font-regular text-gray-700">Я здесь был</span>
              </span>
            </div>

          <div class="-ml-4 -mt-4 flex justify-between items-center flex-wrap sm:flex-nowrap hidden">
            <div class="ml-4 mt-4">
              <h3 class="text-xl leading-6 font-semibold text-gray-900">Информация</h3>
            </div>
            <div class="hidden ml-4 mt-4 flex-shrink-0">
              <button @click="showNewComment = true; $nextTick(() => $refs.comment.focus())" type="button" class="relative inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Добавить</button>
            </div>
          </div>
            <div wire:key="review-for-spring-{{ $spring->id }}">
                <div>
                    <div x-show="showNewComment" class="bg-white shadow-xl rounded-lg p-4 mt-4">
                        <form wire:submit.prevent="storeReview">
                            <div>
                                <div class="flex items-center justify-between">
                                    <label for="comment" class="block">Новый комментарий</label>
                                    <div @click="showNewComment = false;" class="text-xl text-gray-400 mr-1 hover:text-red-600 cursor-pointer" >
                                        &times;
                                    </div>
                                </div>
                                <div class="mt-1">
                                    <textarea wire:model.defer="review.comment" x-ref="comment" rows="4" name="comment" id="comment" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full border-gray-300 rounded-md"></textarea>
                                </div>
                            </div>
                            <button class="mt-2 inline-flex items-center px-4 py-2 border border-transparent font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Добавить комментарий
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="mt-0">
            <livewire:reviews.create :spring="$spring">
        </div>
    </div>
</x-app-layout>
