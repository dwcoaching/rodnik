<div>
    <div>
      <label for="email" class="block text-sm font-medium text-gray-700">Дата посещения</label>
      <div class="mt-1">
        <input value="{{ now()->format('d.m.Y' )}}" type="email" name="email" id="email" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="you@example.com">
      </div>
    </div>
    <div class="mt-6">
      <label class="block text-sm font-medium text-gray-700">Вода есть?</label>
      <fieldset class="mt-1">
        <legend class="sr-only">Notification method</legend>
        <div class="space-y-2">
          <div class="flex items-center">
            <input id="email" name="notification-method" type="radio" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
            <label for="email" class="ml-3 block text-sm font-regular text-gray-700"> Воды нет </label>
          </div>

          <div class="flex items-center">
            <input id="sms" name="notification-method" type="radio" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
            <label for="sms" class="ml-3 block text-sm font-regular text-gray-700"> Есть, но мало </label>
          </div>

          <div class="flex items-center">
            <input id="push" name="notification-method" type="radio" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
            <label for="push" class="ml-3 block text-sm font-regular text-gray-700"> Вода есть </label>
          </div>
        </div>
      </fieldset>
    </div>
    <div class="mt-6">
      <label class="block text-sm font-medium text-gray-700">Субъективная оценка качества воды</label>
      <fieldset class="mt-1">
        <legend class="sr-only">Notification method</legend>
        <div class="space-y-2">
          <div class="flex items-center">
            <input id="email" name="notification-method" type="radio" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
            <label for="email" class="ml-3 block text-sm font-regular text-gray-700"> Вода плохая </label>
          </div>

          <div class="flex items-center">
            <input id="sms" name="notification-method" type="radio" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
            <label for="sms" class="ml-3 block text-sm font-regular text-gray-700"> Сложно сказать, не понятно </label>
          </div>

          <div class="flex items-center">
            <input id="push" name="notification-method" type="radio" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
            <label for="push" class="ml-3 block text-sm font-regular text-gray-700"> Вода отличная </label>
          </div>
        </div>
      </fieldset>
    </div>
    <div class="sm:col-span-6 mt-6">
      <label for="cover-photo" class="block text-sm font-medium text-gray-700"> Фото </label>
      <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
        <div class="space-y-1 text-center">
          <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <div class="flex text-sm text-gray-600">
            <label for="file-upload" class="relative cursor-pointer  rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
              <span>Выберите фото</span>
              <input id="file-upload" name="file-upload" type="file" class="sr-only">
            </label>
            <p class="pl-1">или перетащите сюда</p>
          </div>
          <p class="text-xs text-gray-500">PNG, JPG, GIF не более 10 Мб</p>
        </div>
      </div>
    </div>
    <div class="mt-6">
      <label for="comment" class="block text-sm font-medium text-gray-700">Комментарий</label>
      <div class="mt-1">
        <textarea rows="4" name="comment" id="comment" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"></textarea>
      </div>
    </div>

    <div class="pt-5">
        <div class="flex justify-end">
          <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Добавить отзыв</button>
        </div>
    </div>
</div>
