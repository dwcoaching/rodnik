<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
    <form wire:submit.prevent="store"
        x-data="{

        }"
    >

    <a x-data href="#" x-on:click.prevent="history.back();" class="block text-3xl font-bold text-blue-600 hover:text-blue-700"">
        <span class="mr-2 inline-flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 mb-6" width="36" height="36" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/>
            </svg>
        </span>
    </a>

    @guest
        <div class="bg-yellow-100 p-4 rounded-lg border border-yellow-400 mb-6  max-w-3xl">
            <div class="font-bold max-w-prose">
                Вы пишете анонимно
            </div>
            <div class="mt-2 max-w-prose">
                Так тоже можно, но лучше писать под своим именем — тогда у вас
                будет копиться история и будет возможность редактировать и удалять
                свои отчеты.
            </div>
            <div class="mt-4 max-w-prose">
                <a href="{{ route('register') }}" type="button" class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Зарегистрироваться</a>
                <a href="{{ route('login') }}" type="button" class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Войти</a>
            </div>
        </div>
    @endguest

    <div class="flex items-center justify-between">
        <div class="flex-1 min-w-0">
            <span class="block text-3xl font-bold">
                <span class="mr-2 inline-flex items-center">
                    @if ($spring->id)
                        {{ $spring->type }}
                    @else
                        Новый источник воды
                    @endif
                </span>
            </span>
        </div>
    </div>

    <div class="mt-4 max-w-lg"
        x-data="{
            type: @entangle('spring.type').defer,
        }"
    >
        <x-chip-radio name="💧 Родник" key="type" value="Родник" />
        <x-chip-radio name="🪣 Колодец" key="type" value="Колодец" />
        <x-chip-radio name="🚰 Кран" key="type" value="Кран" />
        <x-chip-radio name="🐳 Другое" key="type" value="Источник воды" />

        <div class="mt-2 relative border border-gray-300 rounded-md bg-white px-3 py-2 focus-within:z-10 focus-within:ring-1 focus-within:ring-blue-600 focus-within:border-blue-600">
            <label for="coordinates" class="block text-sm font-light text-gray-600 mb-1">Широта, долгота</label>
            <input wire:model.defer="coordinates" type="text" name="coordinates" id="coordinates" class="block w-full border-0 p-0 text-gray-900 placeholder-gray-500 focus:ring-0 sm:text-sm">
        </div>

        <div class="mt-2 border border-gray-300 rounded-md bg-white px-3 py-2 focus-within:z-10 focus-within:ring-1 focus-within:ring-blue-600 focus-within:border-blue-600">
            <label for="name" class="block text-sm font-light text-gray-600 mb-1">Название</label>
            <input wire:model.defer="spring.name" type="text" name="name" id="name" class="block w-full border-0 p-0 text-gray-900 placeholder-gray-500 focus:ring-0 sm:text-sm" placeholder="">
        </div>


    </div>

    <div class="mt-4 pb-6">
        <div class="flex justify-start">
          <input type="submit" value="{{ $spring->id ? 'Сохранить изменения' : 'Добавить источник' }}" class="cursor-pointer inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-full shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" />
        </div>
    </div>
  </form>
</div>
